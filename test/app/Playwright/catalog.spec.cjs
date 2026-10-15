const path = require('node:path');
const { test, expect } = require(path.join(process.env.CATALYST_PLAYWRIGHT_ENGINE, 'node_modules/playwright/test'));

test('catalog selects the detected PHP and only executable reviewed recipes reach install select', async ({ page, context }, testInfo) => {
  if (!process.env.WSDD_TEST_PASSWORD) throw new Error('WSDD_TEST_PASSWORD is required.');
  await page.goto('/');
  await page.locator('[name=username]').fill('admin');
  await page.locator('[name=password]').fill(process.env.WSDD_TEST_PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  const sessionCookie = (await context.cookies()).find(cookie => cookie.name === 'WSDD_PHP_ADMIN');
  expect(sessionCookie).toBeDefined();
  expect(sessionCookie.httpOnly).toBe(true);
  expect(sessionCookie.sameSite).toBe('Strict');
  const state = await (await context.request.get('/?api=state')).json();
  const minor = testInfo.project.metadata.phpMinor;
  expect(state.runtime.php.version.startsWith(minor + '.')).toBe(true);
  expect(state.compatibility[minor].install_catalog).toBe('implemented');
  expect(Object.values(state.catalog).filter(entry => entry.recipe_available)).toHaveLength(21);
  for (const entry of Object.values(state.catalog)) {
    expect(entry.php_version).toBe(state.runtime.php.version);
    expect(entry.distribution).toBe('debian');
    if (entry.installable) {
      expect(entry.recipe_available).toBe(true);
      expect(entry.source_available).toBe(true);
      expect(entry.reason).toBeNull();
    }
  }
  expect(state.catalog.dba.source_available).toBe(true);
  expect(state.catalog.dba.recipe_available).toBe(false);
  expect(state.catalog.dba.installable).toBe(false);
  if (Number(minor) >= 8.4) expect(state.catalog.imap).toBeUndefined();
  else expect(state.catalog.imap.source_available).toBe(true);
  await page.goto('/?view=extensions');
  await expect(page.getByRole('heading', { name: `Extension catalog - PHP ${state.runtime.php.version}`, exact: true })).toBeVisible();
  await page.goto('/?view=operations');
  await page.locator('select[name=type]').selectOption('install_extension');
  const options = await page.locator('select[name=extension] option').evaluateAll(nodes => nodes.map(node => node.value).filter(Boolean));
  expect(options.sort()).toEqual([...state.operation_options.install_extension].sort());
  expect(options).not.toContain('dba');
  for (const name of options) {
    expect(state.catalog[name].installable).toBe(true);
    expect(state.catalog[name].loaded).toBe(false);
    expect(state.catalog[name].available).toBe(false);
  }
  await page.screenshot({ path: testInfo.outputPath('catalog-install-options.png'), fullPage: true });
});
