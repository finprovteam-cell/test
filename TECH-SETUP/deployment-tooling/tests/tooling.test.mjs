import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { assertSitePathAllowed, collectSiteFiles, readSiteConfig, scanTrackedSourceForSecrets, sha256, validateReleasePayload } from '../scripts/lib.mjs';
import { spawnSync } from 'node:child_process';

const toolingRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('allows the exact confirmation page without allowing unrelated nested HTML', () => {
  for (const site of ['gulf-careers', 'online-courses']) {
    assert.doesNotThrow(() => assertSitePathAllowed(site, 'thank-you/index.html'));
    assert.throws(() => assertSitePathAllowed(site, 'other/index.html'));
  }
});

test('CSS data SVG with nested url and single quotes is not mistaken for a local asset', async () => {
  const root = await fixture();
  try {
    await writeFile(path.join(root, 'assets', 'style.css'), `body{background:url("data:image/svg+xml,%3Csvg filter='url(%23n)'/%3E")}`);
    await buildFixture(root);
    await collectSiteFiles(await readSiteConfig(path.join(root, 'site.json')));
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

async function fixture() {
  const root = await mkdtemp(path.join(os.tmpdir(), 'landing-tooling-test-'));
  await mkdir(path.join(root, 'assets'), { recursive: true });
  await writeFile(path.join(root, 'site.json'), JSON.stringify({
    site: 'gulf-careers',
    sourceDir: '.',
    artifactDir: 'dist',
    include: ['index.html', 'assets/*'],
    urlMappings: [{ prefix: '/gulf-careers/', artifactPrefix: '' }],
    expectedTags: ['GTM-TEST123'],
  }));
  await writeFile(path.join(root, 'index.html'), '<!doctype html><script src="/gulf-careers/assets/app.js"></script><!-- GTM-TEST123 -->');
  await writeFile(path.join(root, 'assets', 'app.js'), 'export const ready = true;');
  return root;
}

async function buildFixture(root) {
  const result = spawnSync(process.execPath, [path.join(toolingRoot, 'scripts/build-static.mjs'), path.join(root, 'site.json')], { encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
}

test('collects allowlisted files and checks local references, tags, and JS syntax', async () => {
  const root = await fixture();
  try {
    const site = await readSiteConfig(path.join(root, 'site.json'));
    await buildFixture(root);
    const result = await collectSiteFiles(site);
    assert.deepEqual(result.files.map((file) => file.path), ['assets/app.js', 'index.html']);
    assert.ok(result.totalBytes > 0);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test('builds static files only from the checked include list', async () => {
  const root = await fixture();
  try {
    const result = spawnSync(process.execPath, [path.join(toolingRoot, 'scripts/build-static.mjs'), path.join(root, 'site.json')], { encoding: 'utf8' });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(await readFile(path.join(root, 'dist/index.html'), 'utf8'), '<!doctype html><script src="/gulf-careers/assets/app.js"></script><!-- GTM-TEST123 -->');
    assert.equal(await readFile(path.join(root, 'dist/assets/app.js'), 'utf8'), 'export const ready = true;');
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test('validates exact release paths and hashes against site policy', async () => {
  const root = await fixture();
  try {
    const site = await readSiteConfig(path.join(root, 'site.json'));
    await buildFixture(root);
    const files = [
      { path: 'index.html', contentBase64: Buffer.from('<title>GTM-TEST123</title>').toString('base64'), sha256: sha256('<title>GTM-TEST123</title>') },
      { path: 'assets/app.js', contentBase64: Buffer.from('export const ready = true;').toString('base64'), sha256: sha256('export const ready = true;') },
    ];
    await validateReleasePayload({ site: 'gulf-careers', revision: '0123456789abcdef0123456789abcdef01234567', files }, site.config);
    await assert.rejects(() => validateReleasePayload({ site: 'gulf-careers', revision: '0123456789abcdef0123456789abcdef01234567', files: [{ ...files[0], path: '../api/config.php' }] }, site.config));
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test('builds a release payload tied to the supplied commit without adding files outside the manifest', async () => {
  const root = await fixture();
  try {
    await buildFixture(root);
    const releasePath = path.join(root, 'release.json');
    const result = spawnSync(process.execPath, [path.join(toolingRoot, 'scripts/build-release.mjs'), '--config', path.join(root, 'site.json'), '--out', releasePath], {
      encoding: 'utf8',
      env: { ...process.env, GITHUB_SHA: '0123456789abcdef0123456789abcdef01234567' },
    });
    assert.equal(result.status, 0, result.stderr);
    const payload = JSON.parse(await readFile(releasePath, 'utf8'));
    assert.equal(payload.site, 'gulf-careers');
    assert.equal(payload.revision, '0123456789abcdef0123456789abcdef01234567');
    assert.deepEqual(payload.files.map((file) => file.path), ['assets/app.js', 'index.html']);
    const site = await readSiteConfig(path.join(root, 'site.json'));
    await validateReleasePayload(payload, site.config);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test('deploy probe fails closed before opening SSH when credentials are absent', async () => {
  const root = await fixture();
  try {
    const result = spawnSync(process.execPath, [path.join(toolingRoot, 'scripts/deploy.mjs'), '--probe'], {
      encoding: 'utf8',
      env: {
        ...process.env,
        DEPLOY_SITE_CONFIG: path.join(root, 'site.json'),
        DEPLOY_HOST: '',
        DEPLOY_USER: '',
        DEPLOY_SSH_KEY_PATH: '',
        DEPLOY_KNOWN_HOSTS_PATH: '',
      },
    });
    assert.notEqual(result.status, 0);
    assert.match(result.stderr, /DEPLOY_HOST/);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test('scans tracked text sources and rejects committed secret-like content', async () => {
  const root = await fixture();
  try {
    for (const args of [['init', '-q'], ['add', 'site.json', 'index.html', 'assets/app.js']]) {
      const result = spawnSync('git', args, { cwd: root, encoding: 'utf8' });
      assert.equal(result.status, 0, result.stderr);
    }
    const safeResult = await scanTrackedSourceForSecrets(root);
    assert.equal(safeResult.available, true);
    assert.ok(safeResult.filesScanned >= 3);

    await writeFile(path.join(root, '.env'), `ACCESS_TOKEN=${'ghp_' + 'abcdefghijklmnopqrstuvwxyz012345'}\n`);
    const addSecret = spawnSync('git', ['add', '.env'], { cwd: root, encoding: 'utf8' });
    assert.equal(addSecret.status, 0, addSecret.stderr);
    await assert.rejects(() => scanTrackedSourceForSecrets(root), /blocked secret pattern/);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});
