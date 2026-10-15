const path = require('node:path');
const policy = require('../../../config/php-compatibility.json');
const engine = process.env.CATALYST_PLAYWRIGHT_ENGINE;
if (!engine) throw new Error('Use the workspace Playwright project runner.');
const { defineConfig } = require(path.join(engine, 'node_modules/playwright/test'));
const versions = process.env.WSDD_TEST_PHP_VERSIONS
  ? process.env.WSDD_TEST_PHP_VERSIONS.split(',').map(value => value.trim())
  : Object.keys(policy.targets).filter(version => policy.targets[version] !== 'pending');
for (const version of versions) {
  if (!(version in policy.targets)) throw new Error(`Unknown PHP target: ${version}`);
}

module.exports = defineConfig({
  testDir: __dirname,
  workers: 1,
  timeout: 60000,
  outputDir: path.join(engine, 'artifacts/wsdd-php-admin/runtime-observation', versions.map(version => version.replace('.', '')).join('-')),
  reporter: 'list',
  use: { browserName: 'chromium', screenshot: 'only-on-failure', trace: 'retain-on-failure' },
  projects: versions.map(version => ({
    name: `php${version.replace('.', '')}`,
    metadata: {
      phpMinor: version,
      appRoot: policy.dev_root_overrides[version] || policy.dev_root_template.replace('{minor_id}', version.replace('.', '')),
      container: policy.container_template.replace('{minor}', version),
    },
    use: { baseURL: policy.admin_url_template.replace('{minor_id}', version.replace('.', '')) },
  })),
});
