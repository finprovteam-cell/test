#!/usr/bin/env node
import { execFile } from 'node:child_process';
import { lstat, readFile } from 'node:fs/promises';
import path from 'node:path';
import { readSiteConfig, validateReleasePayload } from './lib.mjs';

const MAX_RELEASE_BYTES = 42 * 1024 * 1024;
const MAX_OUTPUT_BYTES = 1024 * 1024;

function readArgs() {
  const args = process.argv.slice(2);
  const mode = args.includes('--probe') ? 'probe' : args.includes('--dry-run') ? 'dry-run' : 'deploy';
  const filtered = args.filter((arg) => !['--probe', '--dry-run'].includes(arg));
  if (filtered.length > 1 || (mode === 'probe' && filtered.length !== 0) || (mode !== 'probe' && filtered.length !== 1)) {
    throw new Error('Usage: node scripts/deploy.mjs [release.json] [--dry-run] | node scripts/deploy.mjs --probe');
  }
  if (args.includes('--probe') && args.includes('--dry-run')) throw new Error('Choose one deployment operation.');
  return { mode, releasePath: filtered[0] };
}

function requiredEnvironment(name) {
  const value = process.env[name];
  if (!value) throw new Error(`Required environment variable is missing: ${name}`);
  return value;
}

async function checkPrivateKey(keyPath) {
  const metadata = await lstat(keyPath);
  if (!metadata.isFile() || metadata.isSymbolicLink()) throw new Error('DEPLOY_SSH_KEY_PATH must point to a regular file.');
  if ((metadata.mode & 0o077) !== 0) throw new Error('Deployment SSH key permissions must be 0600 or stricter.');
}

function validateHost(value) {
  if (!/^[A-Za-z0-9][A-Za-z0-9.-]*$/.test(value) || value.startsWith('-') || value.includes('..')) {
    throw new Error('DEPLOY_HOST must be a DNS name or IPv4 address.');
  }
  return value;
}

function validateUser(value) {
  if (!/^[A-Za-z0-9_-]{1,64}$/.test(value)) throw new Error('DEPLOY_USER contains invalid characters.');
  return value;
}

function validatePort(value) {
  const port = Number(value);
  if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('DEPLOY_PORT must be an integer from 1 to 65535.');
  return String(port);
}

async function runSsh({ host, user, port, keyPath, knownHostsPath, command, payload }) {
  const knownHostsStat = await lstat(knownHostsPath);
  if (!knownHostsStat.isFile() || knownHostsStat.isSymbolicLink() || knownHostsStat.size === 0) throw new Error('DEPLOY_KNOWN_HOSTS_PATH must point to a non-empty pinned known_hosts file.');
  const sshArgs = [
    '-F', '/dev/null',
    '-T',
    '-p', port,
    '-i', keyPath,
    '-o', 'BatchMode=yes',
    '-o', 'PreferredAuthentications=publickey',
    '-o', 'PasswordAuthentication=no',
    '-o', 'KbdInteractiveAuthentication=no',
    '-o', 'StrictHostKeyChecking=yes',
    '-o', `UserKnownHostsFile=${knownHostsPath}`,
    '-o', 'GlobalKnownHostsFile=/dev/null',
    '-o', 'UpdateHostKeys=no',
    '-o', 'IdentityAgent=none',
    '-o', 'IdentitiesOnly=yes',
    '-o', 'ClearAllForwardings=yes',
    '-o', 'ForwardAgent=no',
    '-o', 'ProxyCommand=none',
    '-o', 'ProxyJump=none',
    '-o', 'PermitLocalCommand=no',
    '-o', 'ConnectTimeout=15',
    '-o', 'ServerAliveInterval=15',
    '-o', 'ServerAliveCountMax=3',
    '-o', 'LogLevel=ERROR',
    `${user}@${host}`,
    command,
  ];
  const child = execFile('ssh', sshArgs, { stdio: ['pipe', 'pipe', 'pipe'], env: { ...process.env, SSH_AUTH_SOCK: '' } });
  let stdout = '';
  let stderr = '';
  let exceededOutput = false;
  child.stdout.setEncoding('utf8');
  child.stderr.setEncoding('utf8');
  child.stdout.on('data', (chunk) => {
    if (Buffer.byteLength(stdout) + Buffer.byteLength(chunk) > MAX_OUTPUT_BYTES) {
      exceededOutput = true;
      child.kill('SIGTERM');
      return;
    }
    stdout += chunk;
  });
  child.stderr.on('data', (chunk) => {
    if (Buffer.byteLength(stderr) + Buffer.byteLength(chunk) > MAX_OUTPUT_BYTES) {
      exceededOutput = true;
      child.kill('SIGTERM');
      return;
    }
    stderr += chunk;
  });
  child.stdin.on('error', () => {});
  child.stdin.end(payload);
  const exitCode = await new Promise((resolve, reject) => {
    child.once('error', reject);
    child.once('close', resolve);
  });
  if (exceededOutput) throw new Error('SSH receiver output exceeded the 1 MiB limit.');
  if (exitCode !== 0) {
    const detail = stderr.trim();
    throw new Error(`SSH deployment failed${detail ? `: ${detail}` : ` (exit ${exitCode})`}`);
  }
  let result;
  try {
    result = JSON.parse(stdout.trim());
  } catch {
    throw new Error('SSH receiver returned invalid JSON.');
  }
  if (!result || result.ok !== true) throw new Error('SSH receiver did not confirm success.');
  return result;
}

async function main() {
  const { mode, releasePath } = readArgs();
  const configPath = process.env.DEPLOY_SITE_CONFIG ?? 'site.json';
  const site = await readSiteConfig(configPath);
  const host = validateHost(requiredEnvironment('DEPLOY_HOST'));
  const user = validateUser(requiredEnvironment('DEPLOY_USER'));
  const port = validatePort(process.env.DEPLOY_PORT ?? '22');
  const keyPath = path.resolve(requiredEnvironment('DEPLOY_SSH_KEY_PATH'));
  const knownHostsPath = path.resolve(requiredEnvironment('DEPLOY_KNOWN_HOSTS_PATH'));
  await checkPrivateKey(keyPath);

  let payload;
  let payloadText = '';
  if (mode !== 'probe') {
    const resolvedRelease = path.resolve(releasePath);
    const releaseBytes = await readFile(resolvedRelease);
    if (releaseBytes.length > MAX_RELEASE_BYTES) throw new Error('Release artifact exceeds the 42 MiB limit.');
    payloadText = releaseBytes.toString('utf8');
    try {
      payload = JSON.parse(payloadText);
    } catch {
      throw new Error('Release artifact is not valid JSON.');
    }
    await validateReleasePayload(payload, site.config);
    if (process.env.GITHUB_SHA && payload.revision !== process.env.GITHUB_SHA.toLowerCase()) {
      throw new Error('Release revision does not match this GitHub Actions run.');
    }
  }

  const command = mode === 'probe' ? 'probe' : mode === 'dry-run' ? 'deploy --dry-run' : 'deploy';
  const result = await runSsh({ host, user, port, keyPath, knownHostsPath, command, payload: payloadText });
  process.stdout.write(`${JSON.stringify(result)}\n`);
}

main().catch((error) => {
  process.stderr.write(`${error.message}\n`);
  process.exitCode = 1;
});
