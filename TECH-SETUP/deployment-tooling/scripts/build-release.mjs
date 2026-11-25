#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { collectSiteFiles, readSiteConfig, scanTrackedSourceForSecrets } from './lib.mjs';

function getOption(name, fallback) {
  const index = process.argv.indexOf(name);
  if (index === -1) return fallback;
  if (index + 1 >= process.argv.length) throw new Error(`${name} requires a value.`);
  return process.argv[index + 1];
}

function currentRevision() {
  const fromEnvironment = process.env.GITHUB_SHA;
  if (fromEnvironment) return fromEnvironment.toLowerCase();
  try {
    return execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim().toLowerCase();
  } catch {
    throw new Error('Set GITHUB_SHA or run this command in a Git checkout.');
  }
}

async function main() {
  const configPath = getOption('--config', 'site.json');
  const outPath = path.resolve(getOption('--out', 'release.json'));
  const site = await readSiteConfig(configPath);
  await scanTrackedSourceForSecrets(site.repoRoot);
  const artifactReal = await readFile(path.join(site.artifactPath, 'index.html')).then(() => path.resolve(site.artifactPath));
  const outRelative = path.relative(artifactReal, outPath);
  if (outRelative === '' || (!outRelative.startsWith('..') && !path.isAbsolute(outRelative))) {
    throw new Error('Release output cannot be written inside artifactDir.');
  }
  const revision = currentRevision();
  if (!/^[a-f0-9]{40}$/.test(revision)) throw new Error('Revision must be a 40-character lowercase Git commit hash.');
  const { files } = await collectSiteFiles(site);
  const payload = {
    site: site.config.site,
    revision,
    files: files.map((file) => ({ path: file.path, sha256: file.sha256, contentBase64: file.content.toString('base64') })),
  };
  const output = `${JSON.stringify(payload)}\n`;
  const tempPath = `${outPath}.tmp-${process.pid}`;
  await mkdir(path.dirname(outPath), { recursive: true });
  await writeFile(tempPath, output, { flag: 'wx', mode: 0o600 });
  await rename(tempPath, outPath);
  process.stdout.write(`${JSON.stringify({ ok: true, site: payload.site, revision, files: files.length, output: outPath })}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
