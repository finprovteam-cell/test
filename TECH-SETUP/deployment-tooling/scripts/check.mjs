#!/usr/bin/env node
import { collectSiteFiles, readSiteConfig, scanTrackedSourceForSecrets } from './lib.mjs';

async function main() {
  const configPath = process.argv[2] ?? 'site.json';
  if (process.argv.length > 3) throw new Error('Usage: node scripts/check.mjs [site.json]');
  const site = await readSiteConfig(configPath);
  const sourceScan = await scanTrackedSourceForSecrets(site.repoRoot);
  const { files, totalBytes } = await collectSiteFiles(site);
  process.stdout.write(`${JSON.stringify({ ok: true, site: site.config.site, files: files.length, totalBytes, sourceScanAvailable: sourceScan.available, trackedTextFilesScanned: sourceScan.filesScanned, paths: files.map((file) => file.path) })}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
