const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { test, expect } = require(path.join(process.env.CATALYST_PLAYWRIGHT_ENGINE, 'node_modules/playwright/test'));

test('post-rebuild read-only UI agrees with CLI and Apache', async ({ page, context }, testInfo) => {
  const password = process.env.WSDD_TEST_PASSWORD;
  if (!password) throw new Error('WSDD_TEST_PASSWORD is required.');
  const { appRoot: root, container } = testInfo.project.metadata;
  const docker = args => execFileSync('docker', ['exec', container, ...args], { encoding: 'utf8' }).trim();
  const beforeHash = docker(['sha256sum', '/var/lib/wsdd-php-admin/profile.json']);
  const cli = JSON.parse(docker(['php', `${root}/bin/wsdd-php-admin`, 'detect']));
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/');
  await page.locator('[name=username]').fill('admin');
  await page.locator('[name=password]').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.locator('main.content h1')).toHaveText('Overview');
  const state = await (await context.request.get('/?api=state')).json();
  expect(state.runtime.observations.source).toBe('apache-probe');
  expect(state.runtime.observations.errors).toEqual([]);
  expect(state.reconciliation.issue_count).toBe(0);
  expect(state.permissions.current_process_is_root).toBe(false);
  expect(state.permissions.can_write_managed_ini).toBe(false);
  expect(state.permissions.can_use_operations_queue).toBe(true);
  expect(state.runtime.extensions.loaded).toEqual(state.runtime.observations.apache.extensions);
  const normalize = values => values.map(value => value.toLowerCase())
    .filter(value => !['readline', 'apache2handler'].includes(value)).sort();
  expect(normalize(state.runtime.extensions.loaded)).toEqual(normalize(cli.extensions.loaded));
  for (const [extension, metadata] of Object.entries(state.profile.profile.extensions)) {
    const loaded = metadata.desired_state === 'enabled';
    expect(cli.extensions.loaded.includes(extension)).toBe(loaded);
    expect(state.runtime.extensions.loaded.includes(extension)).toBe(loaded);
  }
  const metric = name => page.locator('.metric').filter({ has: page.locator('span', { hasText: new RegExp(`^${name}$`) }) }).locator('strong');
  await expect(metric('Extensions')).toHaveText(String(state.runtime.extensions.loaded_count));
  await expect(metric('Reconciliation')).toHaveText('0 issue(s)');
  const refresh = page.waitForResponse(response => response.url() === page.url() && response.request().method() === 'GET');
  await refresh;
  await expect(page.locator('[data-runtime-refresh-error]')).toHaveCount(0);
  await page.screenshot({ path: testInfo.outputPath('rebuild-overview.png'), fullPage: true });
  for (const view of ['runtime', 'extensions', 'profile', 'operations', 'permissions', 'reconciliation']) {
    await page.goto(`/?view=${view}`);
    await expect(page.locator('main.content h1')).toBeVisible();
    if (view === 'extensions') {
      const visible = await page.locator('.extension-list span').allTextContents();
      expect(visible.sort()).toEqual([...state.runtime.extensions.loaded].sort());
    }
    if (view === 'reconciliation') {
      await expect(page.locator('.section-title')).toContainText('0 issue(s)');
    }
  }
  const after = await (await context.request.get('/?api=state')).json();
  expect(after.operations.items.length).toBe(state.operations.items.length);
  expect(after.operations.items.filter(item => !item.result || item.result.status === 'processing')).toHaveLength(0);
  expect(docker(['sha256sum', '/var/lib/wsdd-php-admin/profile.json'])).toBe(beforeHash);
  expect(errors).toEqual([]);
});
