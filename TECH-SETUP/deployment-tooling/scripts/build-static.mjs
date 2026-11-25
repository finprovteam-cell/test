#!/usr/bin/env node
import { lstat, mkdir, readFile, readdir, realpath, rename, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { assertNoSecretContent, assertSafeRelativePath, assertSitePathAllowed, MAX_FILE_BYTES, MAX_FILES, MAX_TOTAL_FILE_BYTES, readSiteConfig } from './lib.mjs';

function patternToRegExp(pattern) {
  const escaped = pattern.split('/').map((part) => part
    .split('*')
    .map((segment) => segment.replace(/[|\\{}()[\]^$+?.]/g, '\\$&'))
    .join('[^/]*'))
    .join('/');
  return new RegExp(`^${escaped}$`);
}

async function walk(sourceRoot, artifactPath, current = sourceRoot, output = []) {
  const entries = await readdir(current, { withFileTypes: true });
  entries.sort((a, b) => a.name.localeCompare(b.name));
  for (const entry of entries) {
    if (['.git', 'node_modules', '.github', '.codex', '.agents'].includes(entry.name)) continue;
    const absolute = path.join(current, entry.name);
    const relative = path.relative(sourceRoot, absolute).split(path.sep).join('/');
    const resolvedArtifactRelative = path.relative(sourceRoot, artifactPath);
    if (relative === resolvedArtifactRelative || relative.startsWith(`${resolvedArtifactRelative}/`)) continue;
    if (entry.name.startsWith('.')) continue;
    const info = await lstat(absolute);
    if (info.isSymbolicLink()) throw new Error(`Symlinks cannot be used as static build inputs: ${relative}`);
    if (info.isDirectory()) {
      await walk(sourceRoot, artifactPath, absolute, output);
    } else if (info.isFile()) {
      output.push({ absolute, path: relative, size: info.size });
    }
  }
  return output;
}

async function main() {
  const configPath = process.argv[2] ?? 'site.json';
  if (process.argv.length > 3) throw new Error('Usage: node scripts/build-static.mjs [site.json]');
  const site = await readSiteConfig(configPath);
  const sourceReal = path.resolve(site.sourcePath);
  const sourceInfo = await lstat(sourceReal);
  if (!sourceInfo.isDirectory() || sourceInfo.isSymbolicLink()) throw new Error('sourceDir must be a real directory.');
  const repoReal = await realpath(site.repoRoot);
  const sourceCanonical = await realpath(sourceReal);
  if (sourceCanonical !== repoReal && !sourceCanonical.startsWith(`${repoReal}${path.sep}`)) throw new Error('sourceDir resolves outside the repository.');
  const artifactRelative = path.relative(site.repoRoot, site.artifactPath);
  const firstArtifactSegment = artifactRelative.split(path.sep)[0];
  if (['.git', 'node_modules', '.github', 'scripts', 'src', 'public', 'api', 'storage'].includes(firstArtifactSegment)) {
    throw new Error('artifactDir cannot replace a source, tooling, API, storage, or dependency directory.');
  }
  await mkdir(path.dirname(site.artifactPath), { recursive: true });
  const artifactParentReal = await realpath(path.dirname(site.artifactPath));
  if (artifactParentReal !== repoReal && !artifactParentReal.startsWith(`${repoReal}${path.sep}`)) throw new Error('artifactDir resolves outside the repository.');
  const sourceFiles = await walk(sourceReal, site.artifactPath);
  const patterns = site.config.include.map((pattern) => patternToRegExp(pattern));
  const selected = sourceFiles.filter((file) => patterns.some((pattern) => pattern.test(file.path)));
  if (selected.length === 0 || selected.length > MAX_FILES) throw new Error('No allowlisted static files were found, or the file count exceeds the limit.');

  let totalBytes = 0;
  for (const file of selected) {
    assertSafeRelativePath(file.path);
    assertSitePathAllowed(site.config.site, file.path);
    const content = await readFile(file.absolute);
    if (content.length !== file.size || content.length > MAX_FILE_BYTES) throw new Error(`Static build input changed or exceeds 15 MiB: ${file.path}`);
    file.content = content;
    totalBytes += content.length;
    if (totalBytes > MAX_TOTAL_FILE_BYTES) throw new Error('Static build inputs exceed the 30 MiB total limit.');
    assertNoSecretContent(content, file.path);
  }
  if (!selected.some((file) => file.path === 'index.html')) throw new Error('Static source must include root index.html.');

  if (sourceReal === path.resolve(site.artifactPath)) throw new Error('sourceDir cannot be the same directory as artifactDir.');
  const stagePath = `${site.artifactPath}.stage-${process.pid}`;
  await mkdir(path.dirname(stagePath), { recursive: true });
  await mkdir(stagePath);
  try {
    for (const file of selected) {
      const destination = path.join(stagePath, ...file.path.split('/'));
      await mkdir(path.dirname(destination), { recursive: true });
      await writeFile(destination, file.content, { flag: 'wx', mode: 0o644 });
    }
    const existing = await lstat(site.artifactPath).catch(() => null);
    if (existing !== null && (!existing.isDirectory() || existing.isSymbolicLink())) {
      throw new Error('artifactDir exists but is not a safe directory.');
    }
    if (existing !== null) await rm(site.artifactPath, { recursive: true });
    await rename(stagePath, site.artifactPath);
  } catch (error) {
    await rm(stagePath, { recursive: true, force: true });
    throw error;
  }
  process.stdout.write(`${JSON.stringify({ ok: true, site: site.config.site, files: selected.length, totalBytes, artifactDir: site.config.artifactDir })}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
