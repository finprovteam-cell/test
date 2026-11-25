import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

export function assertProbeMatches(probe, release) {
  assert(probe?.ok === true && probe.site === release.site && probe.revision === release.revision,
    'Authenticated receiver does not confirm the checked commit');
}

export async function verifyPublicResource(url, validate, options = {}) {
  const request = options.fetchImpl || fetch;
  const retries = options.retries || 3;
  let challengeOnly = true;
  for (let attempt = 0; attempt < retries; attempt++) {
    const response = await request(url, { signal: AbortSignal.timeout(15000), headers: { 'User-Agent': 'Finprov-Deployment-Check/1.0' } });
    const challenge = response.status === 403 && response.headers.get('cf-mitigated') === 'challenge'
      && /cloudflare/i.test(response.headers.get('server') || '');
    console.log(`Public check ${attempt + 1}: HTTP ${response.status}; Cloudflare challenge=${challenge}`);
    challengeOnly = challengeOnly && challenge;
    if (response.ok && await validate(response)) return 'verified';
    if (attempt + 1 < retries) await new Promise(resolve => setTimeout(resolve, options.delayMs ?? 3000));
  }
  if (challengeOnly) return 'cloudflare-challenge';
  throw new Error(`Public verification failed for ${new URL(url).pathname}`);
}

async function main() {
  const site = JSON.parse(await readFile('site.json', 'utf8'));
  const release = JSON.parse(await readFile(process.argv[2] || 'release.json', 'utf8'));
  const probe = JSON.parse(execFileSync(process.execPath, ['scripts/deploy.mjs', '--probe'], {
    encoding: 'utf8', timeout: 90000, stdio: ['ignore', 'pipe', 'pipe'],
  }));
  assertProbeMatches(probe, release);
  console.log('Authenticated server confirms the deployed site and checked revision');
  const markerUrl = new URL('deploy-version.json', site.productionUrl);
  markerUrl.searchParams.set('revision', release.revision);
  const marker = await verifyPublicResource(markerUrl, async response => (await response.json()).revision === release.revision);
  const pageUrl = new URL(site.productionUrl);
  pageUrl.searchParams.set('deploy', release.revision);
  const page = await verifyPublicResource(pageUrl, async response => {
    const html = await response.text();
    return /<html/i.test(html) && !/<title>.*(?:404|500|Not Found)/i.test(html);
  });
  if ([marker, page].includes('cloudflare-challenge')) {
    console.log('::warning::The authenticated server verified this deployment. Cloudflare challenged public checks from this GitHub runner; public reachability is not verified by this run. Check the page in a browser and review Cloudflare rules if crawlers are affected.');
  } else {
    console.log('Public live page and deployed revision verified');
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
