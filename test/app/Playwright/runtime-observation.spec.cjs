const path = require('node:path');
const { test, expect } = require(path.join(process.env.CATALYST_PLAYWRIGHT_ENGINE, 'node_modules/playwright/test'));

test('live overview and operations follow Apache without restarting the internal web', async ({ page, context }, testInfo) => {
  // Two operations may each need the full verification window, plus UI recovery and cleanup.
  test.setTimeout(180000);
  const password = process.env.WSDD_TEST_PASSWORD;
  if (!password) throw new Error('WSDD_TEST_PASSWORD is required.');
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/');
  await page.locator('[name=username]').fill('admin');
  await page.locator('[name=password]').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.locator('main.content h1')).toHaveText('Overview');
  const initial = await (await context.request.get('/?api=state')).json();
  expect(initial.runtime.extensions.loaded).not.toContain('calendar');
  expect(initial.runtime.observations.source).toBe('apache-probe');
  const count = page.locator('.metric').filter({ has: page.locator('span', { hasText: /^Extensions$/ }) }).locator('strong');
  const before = Number(await count.textContent());
  const control = await context.newPage();
  control.on('pageerror', error => errors.push(error.message));
  let csrf;
  let removed = false;

  async function waitResult(id) {
    await expect.poll(async () => {
      const queue = await (await context.request.get('/?api=operations')).json();
      return queue.items.find(item => item.operation.id === id)?.result?.status;
    }, { timeout: 40000 }).toBe('success');
  }

  async function submit(type) {
    await control.locator('select[name=type]').selectOption(type);
    await expect(control.locator('select[name=extension] option[value=calendar]')).toHaveCount(1, { timeout: 10000 });
    await control.locator('select[name=extension]').selectOption('calendar');
    const response = control.waitForResponse(response => response.url().includes('api=operations_create') && response.request().method() === 'POST');
    await control.locator('[data-operation-form] button[type=submit]').click();
    const operation = await (await response).json();
    await waitResult(operation.id);
    return operation;
  }

  try {
    await control.goto('/?view=operations');
    csrf = await control.locator('[name=csrf_token]').inputValue();
    await submit('enable_extension');
    await page.bringToFront();
    await expect(count).toHaveText(String(before + 1), { timeout: 12000 });
    const state = await (await context.request.get('/?api=state')).json();
    expect(state.runtime.observations.cli.extensions).toContain('calendar');
    expect(state.runtime.observations.apache.extensions).toContain('calendar');
    expect(state.reconciliation.issue_count).toBe(0);
    await page.screenshot({ path: testInfo.outputPath('enabled-desktop.png'), fullPage: true });
    await control.bringToFront();
    await submit('remove_extension');
    removed = true;
    await page.bringToFront();
    await expect(count).toHaveText(String(before), { timeout: 12000 });
    const documentUrl = page.url();
    await page.route(documentUrl, route => route.abort());
    await expect(page.locator('[data-runtime-refresh-error]')).toBeVisible({ timeout: 12000 });
    await page.unroute(documentUrl);
    // Allow the 5s refresh interval plus queued requests on the single-process legacy server.
    await expect(page.locator('[data-runtime-refresh-error]')).toHaveCount(0, { timeout: 30000 });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: testInfo.outputPath('removed-mobile.png'), fullPage: true });
    expect(errors).toEqual([]);
  } finally {
    if (csrf && !removed) {
      const response = await context.request.post('/?api=operations_create', { form: { type: 'remove_extension', extension: 'calendar', csrf_token: csrf } });
      const operation = await response.json();
      // Repeated remove may be skipped when the successful UI path already removed the INI.
      await expect.poll(async () => {
        const queue = await (await context.request.get('/?api=operations')).json();
        return queue.items.find(item => item.operation.id === operation.id)?.result?.status;
      }, { timeout: 40000 }).toMatch(/^(success|skipped)$/);
    }
    await control.close();
  }
});
