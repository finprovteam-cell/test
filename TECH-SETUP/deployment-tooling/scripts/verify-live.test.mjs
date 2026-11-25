import test from 'node:test';
import assert from 'node:assert/strict';
import { assertProbeMatches, verifyPublicResource } from './verify-live.mjs';

const release = { site: 'gulf-careers', revision: 'a'.repeat(40) };
test('requires authenticated proof of the exact site and revision', () => {
  assert.doesNotThrow(() => assertProbeMatches({ ok: true, ...release }, release));
  for (const proof of [{ ok: false, ...release }, { ok: true, ...release, revision: 'b'.repeat(40) }, { ok: true, ...release, site: 'other' }]) {
    assert.throws(() => assertProbeMatches(proof, release));
  }
});
test('only labels an explicit Cloudflare challenge as an unverified public check', async () => {
  const result = await verifyPublicResource('https://example.invalid/', () => true, {
    retries: 1, fetchImpl: async () => new Response('', { status: 403, headers: { server: 'cloudflare', 'cf-mitigated': 'challenge' } }),
  });
  assert.equal(result, 'cloudflare-challenge');
});
test('ordinary HTTP failures and wrong revisions still fail verification', async () => {
  for (const status of [200, 403, 404, 500]) {
    await assert.rejects(() => verifyPublicResource('https://example.invalid/', () => false, {
      retries: 1, fetchImpl: async () => new Response('', { status }),
    }));
  }
});
test('successful public content is verified', async () => {
  assert.equal(await verifyPublicResource('https://example.invalid/', () => true, {
    retries: 1, fetchImpl: async () => new Response('OK'),
  }), 'verified');
});
