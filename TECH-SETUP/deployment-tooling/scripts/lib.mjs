import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { lstat, readdir, readFile, realpath } from 'node:fs/promises';
import path from 'node:path';
import { spawnSync } from 'node:child_process';

export const ALLOWED_EXTENSIONS = new Set([
  'html', 'js', 'css', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'ico',
  'txt', 'pdf', 'json', 'woff2', 'woff', 'ttf',
]);
export const MAX_FILE_BYTES = 15 * 1024 * 1024;
export const MAX_TOTAL_FILE_BYTES = 30 * 1024 * 1024;
export const MAX_FILES = 2000;

const SUPPORTED_SITES = new Set(['gulf-careers', 'online-courses', 'tally-prime-kerala']);
const SERVER_SITE_POLICIES = {
  'gulf-careers': { rootFiles: ['index.html', 'thank-you/index.html', 'tracking.js', 'site-config.js', 'favicon.ico', 'favicon.png', 'favicon.svg', 'robots.txt'], prefixes: ['assets/'] },
  'online-courses': { rootFiles: ['index.html', 'thank-you/index.html', 'tracking.js', 'site-config.js', 'favicon.ico', 'favicon.png', 'favicon.svg', 'robots.txt'], prefixes: ['assets/'] },
  'tally-prime-kerala': { rootFiles: ['index.html', 'favicon.ico', 'favicon.png', 'favicon.svg', 'icons.svg', 'robots.txt'], prefixes: ['assets/', 'brand/', 'brochures/', 'logos/', 'photos/'] },
};
const SECRET_PATTERNS = [
  /-----BEGIN(?: [A-Z0-9]+)* PRIVATE KEY-----/,
  /\bAKIA[0-9A-Z]{16}\b/,
  /\b(?:gh[pousr]_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,})\b/,
  /\bxox[baprs]-[A-Za-z0-9-]{10,}\b/,
  /\bsk_(?:live|test)_[A-Za-z0-9]{12,}\b/,
  /\bBearer\s+[A-Za-z0-9._-]{24,}\b/i,
  /\b(?:api[_-]?key|access[_-]?token|client[_-]?secret|password)\s*[:=]\s*["'][^"']{16,}["']/i,
];

export function assertSafeRelativePath(filePath) {
  assertSafePathComponents(filePath);
  const extension = path.posix.extname(filePath).slice(1).toLowerCase();
  if (!ALLOWED_EXTENSIONS.has(extension)) {
    throw new Error('A release file type is not allowed.');
  }
  if (filePath === 'deploy-version.json' || path.posix.basename(filePath).toLowerCase() === 'config.php') {
    throw new Error('Receiver-managed or PHP configuration paths are forbidden.');
  }
  return filePath;
}

function assertSafePathComponents(filePath) {
  if (typeof filePath !== 'string' || filePath.length === 0 || Buffer.byteLength(filePath) > 240) {
    throw new Error('A release path is invalid.');
  }
  if (filePath.startsWith('/') || filePath.includes('\\') || /[\0-\x1f\x7f]/.test(filePath) || /^[A-Za-z]:/.test(filePath)) {
    throw new Error('Absolute or control-character release paths are forbidden.');
  }
  const parts = filePath.split('/');
  for (const part of parts) {
    if (!part || part === '.' || part === '..' || part.startsWith('.') || !/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(part)) {
      throw new Error(`A release path contains a forbidden component: ${filePath}`);
    }
    if (['api', 'storage'].includes(part.toLowerCase())) {
      throw new Error('API and storage paths are forbidden.');
    }
  }
}

export function assertSitePathAllowed(site, filePath) {
  const policy = SERVER_SITE_POLICIES[site];
  if (!policy || (!policy.rootFiles.includes(filePath) && !policy.prefixes.some((prefix) => filePath.startsWith(prefix)))) {
    throw new Error(`Release path is outside the server-side site allowlist: ${filePath}`);
  }
}

export function assertNoSecretContent(bytes, label = 'release file') {
  const content = Buffer.isBuffer(bytes) ? bytes.toString('latin1') : String(bytes);
  for (const pattern of SECRET_PATTERNS) {
    if (pattern.test(content)) {
      throw new Error(`${label} matches a blocked secret pattern.`);
    }
  }
}

export function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

export async function scanTrackedSourceForSecrets(repoRoot) {
  let listing;
  try {
    listing = execFileSync('git', ['ls-files', '-z'], {
      cwd: repoRoot,
      encoding: 'buffer',
      maxBuffer: 32 * 1024 * 1024,
      stdio: ['ignore', 'pipe', 'ignore'],
    });
  } catch (error) {
    if (error?.status === 128) return { available: false, filesScanned: 0 };
    throw new Error('Could not list tracked source files for secret checks.');
  }
  const root = await realpath(repoRoot);
  let filesScanned = 0;
  const binaryExtensions = new Set(['png', 'jpg', 'jpeg', 'webp', 'ico', 'pdf', 'woff', 'woff2', 'ttf', 'mp4', 'mov', 'gif']);
  for (const nameBytes of listing.toString('utf8').split('\0')) {
    if (!nameBytes) continue;
    const relative = nameBytes.split(path.sep).join('/');
    const absolute = path.resolve(root, nameBytes);
    const relativeToRoot = path.relative(root, absolute);
    if (relativeToRoot.startsWith('..') || path.isAbsolute(relativeToRoot)) {
      throw new Error('Git returned a tracked path outside the repository.');
    }
    const metadata = await lstat(absolute).catch(() => null);
    if (metadata === null || metadata.isSymbolicLink() || !metadata.isFile()) continue;
    if (metadata.size > MAX_FILE_BYTES) {
      const extension = path.posix.extname(relative).slice(1).toLowerCase();
      if (!binaryExtensions.has(extension)) throw new Error(`Tracked text source exceeds the secret-scan size limit: ${relative}`);
      continue;
    }
    const content = await readFile(absolute);
    if (content.includes(0)) continue;
    assertNoSecretContent(content, relative);
    filesScanned++;
  }
  return { available: true, filesScanned };
}

export async function readSiteConfig(configPath = 'site.json') {
  const absoluteConfig = path.resolve(configPath);
  let raw;
  try {
    raw = await readFile(absoluteConfig, 'utf8');
  } catch {
    throw new Error(`Could not read site configuration: ${configPath}`);
  }
  let config;
  try {
    config = JSON.parse(raw);
  } catch {
    throw new Error(`Site configuration is not valid JSON: ${configPath}`);
  }
  if (!config || typeof config !== 'object' || Array.isArray(config)) {
    throw new Error('Site configuration must be a JSON object.');
  }
  if (!SUPPORTED_SITES.has(config.site)) {
    throw new Error('Site configuration has an unsupported site name.');
  }
  if (typeof config.artifactDir !== 'string' || config.artifactDir.length === 0 || path.isAbsolute(config.artifactDir)) {
    throw new Error('site.json must contain a relative artifactDir.');
  }
  assertSafeRelativeDirectory(config.artifactDir);
  if (config.artifactDir === '.' || config.artifactDir === '') throw new Error('artifactDir cannot be the repository root.');
  if (config.sourceDir !== undefined && config.sourceDir !== '.') assertSafeRelativeDirectory(config.sourceDir);
  if (!Array.isArray(config.include) || config.include.length === 0 || !config.include.every((value) => typeof value === 'string' && value.length > 0)) {
    throw new Error('site.json include must be a non-empty list of path patterns.');
  }
  if (config.expectedTags !== undefined && !Array.isArray(config.expectedTags)) {
    throw new Error('site.json expectedTags must be a list.');
  }
  if (config.urlMappings !== undefined && !Array.isArray(config.urlMappings)) {
    throw new Error('site.json urlMappings must be a list.');
  }
  for (const mapping of config.urlMappings ?? []) {
    if (!mapping || typeof mapping.prefix !== 'string' || !mapping.prefix.startsWith('/') || !mapping.prefix.endsWith('/') || typeof mapping.artifactPrefix !== 'string') {
      throw new Error('Each urlMappings entry needs a slash-delimited prefix and artifactPrefix.');
    }
    if (mapping.artifactPrefix !== '') {
      assertSafeRelativeDirectory(mapping.artifactPrefix);
    }
  }
  for (const pattern of config.include) {
    validateIncludePattern(pattern);
  }
  const repoRoot = path.dirname(absoluteConfig);
  const artifactPath = path.resolve(repoRoot, config.artifactDir);
  const relativeArtifactPath = path.relative(repoRoot, artifactPath);
  if (relativeArtifactPath.startsWith('..') || path.isAbsolute(relativeArtifactPath)) {
    throw new Error('artifactDir must stay inside the repository.');
  }
  const sourcePath = path.resolve(repoRoot, config.sourceDir ?? '.');
  const relativeSourcePath = path.relative(repoRoot, sourcePath);
  if (relativeSourcePath.startsWith('..') || path.isAbsolute(relativeSourcePath)) {
    throw new Error('sourceDir must stay inside the repository.');
  }
  return { config, configPath: absoluteConfig, repoRoot, artifactPath, sourcePath };
}

function assertSafeRelativeDirectory(directory) {
  if (typeof directory !== 'string' || directory.includes('\\') || path.isAbsolute(directory)) {
    throw new Error('An artifact directory path is invalid.');
  }
  for (const part of directory.split('/')) {
    if (!part || part === '.' || part === '..' || part.startsWith('.') || !/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(part)) {
      throw new Error('An artifact directory path contains a forbidden component.');
    }
  }
}

function validateIncludePattern(pattern) {
  if (pattern.startsWith('/') || pattern.includes('\\') || /[\0-\x1f\x7f]/.test(pattern)) {
    throw new Error(`Invalid include pattern: ${pattern}`);
  }
  for (const part of pattern.split('/')) {
    if (!part || part === '.' || part === '..' || part.startsWith('.') || !/^[A-Za-z0-9*][A-Za-z0-9*._-]*$/.test(part)) {
      throw new Error(`Invalid include pattern: ${pattern}`);
    }
    if (part.includes('**')) {
      throw new Error(`Recursive ** patterns are not supported: ${pattern}`);
    }
    if (['api', 'storage'].includes(part.toLowerCase())) {
      throw new Error('API and storage directories cannot be allowlisted.');
    }
  }
  if (pattern.toLowerCase() === 'deploy-version.json' || pattern.toLowerCase().endsWith('.php')) {
    throw new Error(`Receiver-managed or PHP paths cannot be allowlisted: ${pattern}`);
  }
}

function patternToRegExp(pattern) {
  const escaped = pattern.split('/').map((part) => part
    .split('*')
    .map((segment) => segment.replace(/[|\\{}()[\]^$+?.]/g, '\\$&'))
    .join('[^/]*'))
    .join('/');
  return new RegExp(`^${escaped}$`);
}

function isAllowedByPatterns(filePath, patterns) {
  return patterns.some((pattern) => patternToRegExp(pattern).test(filePath));
}

async function walkArtifactDirectory(artifactPath, current = artifactPath, output = []) {
  const entries = await readdir(current, { withFileTypes: true });
  entries.sort((a, b) => a.name.localeCompare(b.name));
  for (const entry of entries) {
    const absolute = path.join(current, entry.name);
    const relative = path.relative(artifactPath, absolute).split(path.sep).join('/');
    const metadata = await lstat(absolute);
    assertSafePathComponents(relative);
    if (entry.isSymbolicLink()) {
      throw new Error(`Symlinks are not allowed in the build artifact: ${relative}`);
    }
    if (metadata.isDirectory()) {
      await walkArtifactDirectory(artifactPath, absolute, output);
    } else if (metadata.isFile()) {
      output.push({ path: relative, absolute, size: metadata.size });
    } else {
      throw new Error(`Only regular files and directories are allowed in the artifact: ${relative}`);
    }
  }
  return output;
}

export async function collectSiteFiles(site) {
  const { config, artifactPath } = site;
  const artifactStat = await lstat(artifactPath).catch(() => null);
  if (artifactStat === null || !artifactStat.isDirectory() || artifactStat.isSymbolicLink()) {
    throw new Error(`Build artifact directory is missing or unsafe: ${config.artifactDir}`);
  }
  let artifactReal;
  try {
    artifactReal = await realpath(artifactPath);
  } catch {
    throw new Error(`Build artifact directory does not exist: ${config.artifactDir}`);
  }
  const repoReal = await realpath(site.repoRoot);
  if (!artifactReal.startsWith(`${repoReal}${path.sep}`)) {
    throw new Error('Build artifact directory resolves outside the repository.');
  }
  const walked = await walkArtifactDirectory(artifactReal);
  if (walked.length === 0 || walked.length > MAX_FILES) {
    throw new Error('Build artifact file count is empty or over the limit.');
  }
  const matchedPatterns = new Set();
  let totalBytes = 0;
  const files = [];
  for (const file of walked) {
    assertSitePathAllowed(config.site, file.path);
    const matchingPatterns = config.include.filter((pattern) => patternToRegExp(pattern).test(file.path));
    if (matchingPatterns.length === 0) {
      throw new Error(`Built file is outside site.json include allowlist: ${file.path}`);
    }
    matchingPatterns.forEach((pattern) => matchedPatterns.add(pattern));
    const content = await readFile(file.absolute);
    if (content.length !== file.size || content.length > MAX_FILE_BYTES) {
      throw new Error(`Built file changed during validation or exceeds the 15 MiB limit: ${file.path}`);
    }
    totalBytes += content.length;
    if (totalBytes > MAX_TOTAL_FILE_BYTES) {
      throw new Error('Built files exceed the 30 MiB total limit.');
    }
    assertNoSecretContent(content, file.path);
    files.push({ path: file.path, content, sha256: sha256(content), size: content.length });
  }
  const unmatched = config.include.filter((pattern) => !pattern.includes('*') && !matchedPatterns.has(pattern));
  if (unmatched.length > 0) {
    throw new Error(`site.json allowlist patterns matched no built files: ${unmatched.join(', ')}`);
  }
  if (!files.some((file) => file.path === 'index.html')) {
    throw new Error('Build artifact must include root index.html.');
  }

  await checkHtmlAndCssReferences(files, config.urlMappings ?? []);
  await checkExpectedTags(files, config.expectedTags ?? []);
  await checkJavaScriptSyntax(files);
  return { files, totalBytes };
}

async function checkHtmlAndCssReferences(files, urlMappings) {
  const byPath = new Set(files.map((file) => file.path));
  for (const file of files) {
    const content = file.content.toString('utf8');
    const references = [];
    if (file.path.endsWith('.html')) {
      const tagRegex = /<(script|link|img|source|video|audio|iframe|embed|object)\b[^>]*>/gi;
      for (const match of content.matchAll(tagRegex)) {
        const tagName = match[1].toLowerCase();
        const attributeName = tagName === 'object' ? 'data' : tagName === 'video' ? 'poster' : tagName === 'link' ? 'href' : 'src';
        if (tagName === 'link') {
          const rel = (readAttribute(match[0], 'rel') ?? '').toLowerCase().split(/\s+/);
          if (!rel.some((value) => ['stylesheet', 'icon', 'shortcut', 'preload', 'modulepreload', 'manifest', 'apple-touch-icon'].includes(value))) continue;
        }
        const attribute = readAttribute(match[0], attributeName);
        if (attribute) references.push(attribute);
        if (['img', 'source'].includes(tagName)) {
          const srcset = readAttribute(match[0], 'srcset');
          if (srcset && !srcset.trim().toLowerCase().startsWith('data:')) {
            for (const candidate of srcset.split(',')) {
              const candidateUrl = candidate.trim().split(/\s+/, 1)[0];
              if (candidateUrl) references.push(candidateUrl);
            }
          }
        }
      }
    } else if (file.path.endsWith('.css')) {
      const urlRegex = /url\(\s*(?:"([^"]*)"|'([^']*)'|([^)'"\s]+))\s*\)/gi;
      for (const match of content.matchAll(urlRegex)) references.push(match[1] ?? match[2] ?? match[3]);
      const importRegex = /@import\s+(?:url\()?\s*["']([^"']+)["']/gi;
      for (const match of content.matchAll(importRegex)) references.push(match[1]);
    }
    for (const reference of references) {
      const localPath = resolveLocalReference(reference, file.path, urlMappings);
      if (localPath === null) continue;
      assertSafeRelativePath(localPath);
      if (!byPath.has(localPath)) {
        throw new Error(`Local page asset reference is missing from the release: ${file.path} -> ${reference} (${localPath})`);
      }
    }
  }
}

function readAttribute(tag, name) {
  const matcher = new RegExp(`\\b${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i');
  const match = matcher.exec(tag);
  return match ? (match[1] ?? match[2] ?? match[3] ?? '').trim() : null;
}

function resolveLocalReference(reference, currentFile, mappings) {
  if (!reference || /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(reference)) return null;
  let pathname;
  try {
    pathname = decodeURIComponent(reference.split(/[?#]/, 1)[0]);
  } catch {
    throw new Error(`Asset reference has invalid URL encoding: ${reference}`);
  }
  if (pathname === '' || pathname.startsWith('data:') || pathname.startsWith('blob:')) return null;
  if (pathname.startsWith('/')) {
    const mapping = [...mappings].sort((a, b) => b.prefix.length - a.prefix.length).find((candidate) => pathname.startsWith(candidate.prefix));
    if (mapping) {
      const suffix = pathname.slice(mapping.prefix.length);
      return path.posix.normalize(path.posix.join(mapping.artifactPrefix, suffix));
    }
    return path.posix.normalize(pathname.replace(/^\/+/, ''));
  }
  return path.posix.normalize(path.posix.join(path.posix.dirname(currentFile), pathname));
}

async function checkExpectedTags(files, expectedTags) {
  const combined = files
    .filter((file) => ['html', 'js', 'css', 'json', 'txt'].includes(path.posix.extname(file.path).slice(1)))
    .map((file) => file.content.toString('utf8'))
    .join('\n');
  for (const expected of expectedTags) {
    const value = typeof expected === 'string' ? expected : expected?.value;
    const minimum = typeof expected === 'string' ? 1 : expected?.minCount ?? 1;
    if (typeof value !== 'string' || value.length === 0 || !Number.isInteger(minimum) || minimum < 1) {
      throw new Error('expectedTags entries must be strings or { value, minCount } objects.');
    }
    const count = combined.split(value).length - 1;
    if (count < minimum) {
      throw new Error(`Expected tracking/config marker was not found enough times: ${value}`);
    }
  }
}

async function checkJavaScriptSyntax(files) {
  for (const file of files.filter((item) => item.path.endsWith('.js'))) {
    const result = spawnSync(process.execPath, ['--check', '--input-type=module'], {
      input: file.content,
      encoding: 'utf8',
      maxBuffer: 1024 * 1024,
    });
    if (result.error || result.status !== 0) {
      throw new Error(`JavaScript syntax check failed for ${file.path}: ${(result.stderr || result.error?.message || 'unknown error').trim()}`);
    }
  }
}

export async function validateReleasePayload(payload, siteConfig) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new Error('Release payload must be a JSON object.');
  }
  const keys = Object.keys(payload).sort();
  if (keys.join(',') !== 'files,revision,site') {
    throw new Error('Release payload has an invalid shape.');
  }
  if (payload.site !== siteConfig.site) {
    throw new Error('Release site does not match site.json.');
  }
  if (typeof payload.revision !== 'string' || !/^[a-f0-9]{40}$/.test(payload.revision)) {
    throw new Error('Release revision must be a 40-character lowercase Git commit hash.');
  }
  if (!Array.isArray(payload.files) || payload.files.length === 0 || payload.files.length > MAX_FILES) {
    throw new Error('Release files must be a non-empty list within the file-count limit.');
  }
  const seen = new Set();
  let totalBytes = 0;
  let hasIndex = false;
  const decodedFiles = [];
  for (const file of payload.files) {
    if (!file || typeof file !== 'object' || Array.isArray(file) || Object.keys(file).sort().join(',') !== 'contentBase64,path,sha256') {
      throw new Error('A release file entry has an invalid shape.');
    }
    assertSafeRelativePath(file.path);
    assertSitePathAllowed(siteConfig.site, file.path);
    if (!isAllowedByPatterns(file.path, siteConfig.include)) {
      throw new Error(`Release file is outside site.json include allowlist: ${file.path}`);
    }
    if (seen.has(file.path)) throw new Error(`Duplicate release path: ${file.path}`);
    seen.add(file.path);
    if (typeof file.sha256 !== 'string' || !/^[a-f0-9]{64}$/.test(file.sha256) || typeof file.contentBase64 !== 'string') {
      throw new Error(`Release file metadata is invalid: ${file.path}`);
    }
    const content = Buffer.from(file.contentBase64, 'base64');
    if (content.toString('base64') !== file.contentBase64 || content.length > MAX_FILE_BYTES) {
      throw new Error(`Release file base64 or size is invalid: ${file.path}`);
    }
    totalBytes += content.length;
    if (totalBytes > MAX_TOTAL_FILE_BYTES) throw new Error('Release files exceed the 30 MiB total limit.');
    if (sha256(content) !== file.sha256) throw new Error(`Release file hash mismatch: ${file.path}`);
    assertNoSecretContent(content, file.path);
    if (file.path === 'index.html') hasIndex = true;
    decodedFiles.push({ path: file.path, content, size: content.length, sha256: file.sha256 });
  }
  if (!hasIndex) throw new Error('Release must include root index.html.');
  await checkHtmlAndCssReferences(decodedFiles, siteConfig.urlMappings ?? []);
  await checkExpectedTags(decodedFiles, siteConfig.expectedTags ?? []);
  await checkJavaScriptSyntax(decodedFiles);
}
