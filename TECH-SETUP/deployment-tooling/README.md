> TECH REFERENCE SNAPSHOT. These policies cover only the existing three site IDs. Adding a new site requires a reviewed server-owned policy and restricted identity, not copying another site's configuration. Compare against the installed receiver before changing it.

# Static landing-page deployment tooling

This directory contains shared release tooling for the three landing-page repositories. It deploys a bounded static release through a site-specific forced SSH command. The receiver has a second, server-owned path policy so a changed GitHub workflow or manifest cannot select another cPanel directory.

## Files

- `receive.php` — PHP 8.0 compatible forced-command receiver. It accepts one JSON release on stdin, validates every path/hash/size, backs up replaced files outside `public_html`, stages files beside their destinations, and promotes HTML last.
- `scripts/build-static.mjs` — optional copy build for static repositories. It copies only files matching `site.json`'s allowlist into `artifactDir`.
- `scripts/check.mjs` — validates the built file set, extensions, secrets, local HTML/CSS references, expected tracking markers, and JavaScript syntax.
- `scripts/build-release.mjs` — creates the receiver payload `release.json` from `artifactDir` and `GITHUB_SHA` (or the current Git commit).
- `scripts/deploy.mjs` — validates a release artifact and sends it over SSH with strict host-key checking. It exposes only deploy, dry-run, and probe operations.
- `sites/*.site.json` — examples to copy and adapt into each repository's checked-in `site.json`.
- `templates/static-site.package.json` — script template for static Gulf/Online repositories. Merge these scripts into each repository's existing `package.json`; keep its current dependencies and lockfile.

## Site configuration

`site.json` is a reviewed, checked-in allowlist. `include` patterns are relative to `artifactDir`; `*` matches one path segment and recursive `**` patterns are rejected. The server also requires each path to fit its independent site policy:

- Gulf and Online: root `index.html`, `tracking.js`, `site-config.js`, favicon files, `robots.txt`, and files under `assets/`.
- Tally: root `index.html`, favicon/icons, `robots.txt`, and files under `assets/`, `brand/`, `brochures/`, `logos/`, and `photos/` (including its learner/convocation subdirectories).

The example `urlMappings` translate root-relative page asset URLs to files in the local build artifact. Add a mapping for each site URL prefix used by HTML/CSS. Keep Tally bundles under its own `assets/` directory so its key cannot write to the shared `/courses/assets` tree.

Example repository workflow:

```json
{
  "site": "gulf-careers",
  "sourceDir": ".",
  "artifactDir": "dist",
  "include": ["index.html", "tracking.js", "site-config.js", "assets/*"],
  "urlMappings": [{ "prefix": "/gulf-careers/", "artifactPrefix": "" }],
  "expectedTags": [{ "value": "GTM-REPLACE-ME", "minCount": 1 }]
}
```

`expectedTags` values are public marker strings such as GTM or GA4 IDs; do not put credentials in static configuration. Empty `expectedTags` is allowed when a site has no stable tracking marker. `check.mjs` scans tracked text files in the current checkout and the shipped artifact for common private-key/token formats and secret-like key/value assignments. It does not scan previous Git history and is not a substitute for keeping credentials out of browser-delivered files.

The optional static build removes and recreates only the configured `artifactDir`; use a disposable output directory such as `dist`. Vite/other compiled sites should keep their normal `npm run build` and use only `check.mjs` and `build-release.mjs`.

## Receiver installation

Install `receive.php` outside the web root, for example at `/home/accountingcourse/landing-deploy/receive.php`. Create the shared lock file and backup parent outside `public_html` before enabling keys:

```sh
mkdir -p /home/accountingcourse/landing-deploy/backups
touch /home/accountingcourse/landing-deploy/deploy.lock
chmod 700 /home/accountingcourse/landing-deploy /home/accountingcourse/landing-deploy/backups
chmod 600 /home/accountingcourse/landing-deploy/deploy.lock
```

Ensure the account can write to its three exact site roots and the backup directory. The receiver is configured for:

- `/home/accountingcourse/public_html/gulf-careers`
- `/home/accountingcourse/public_html/courses/online-courses`
- `/home/accountingcourse/public_html/courses/tally-prime-kerala`

Run PHP 8.0 or later from CLI. Each authorized key must have its own fixed site argument and forced command, such as:

```text
restrict,command="/usr/local/bin/php /home/accountingcourse/landing-deploy/receive.php gulf-careers" ssh-ed25519 AAAA... gulf-careers deploy
```

Use the host's actual PHP CLI executable. If its OpenSSH version does not support `restrict`, apply the equivalent `no-pty`, `no-agent-forwarding`, `no-port-forwarding`, `no-X11-forwarding`, and `no-user-rc` restrictions. Do not install these keys with a general shell command. The receiver accepts only the exact original SSH commands `deploy`, `deploy --dry-run`, and `probe`.

The shared `deploy.lock` serializes all three sites. Backups are versioned at `landing-deploy/backups/<site>/<timestamp>-<revision>-<random>/`; each contains only replaced files and a hash manifest. No omitted live files are deleted. If promotion fails, the receiver restores replaced files and removes only newly created files from that transaction. Old hashed assets remain available for cached pages and rollback.

## GitHub Actions environment

`scripts/deploy.mjs` reads:

- `DEPLOY_HOST` — cPanel SSH host name or IPv4 address.
- `DEPLOY_USER` — restricted cPanel account name.
- `DEPLOY_PORT` — optional numeric port, default `22` (set `9895` for the configured cPanel listener).
- `DEPLOY_SSH_KEY_PATH` — path to the runner-created private key file; mode must be `0600` or stricter.
- `DEPLOY_KNOWN_HOSTS_PATH` — non-empty pinned `known_hosts` file. Never create it with an unverified runtime `ssh-keyscan`.
- `DEPLOY_SITE_CONFIG` — optional path to `site.json`, default `site.json`.

For a non-default SSH port, the pinned `known_hosts` entry uses `[host]:port`. The client disables global host-key files, agent forwarding, and all forwarding. The current workflow uses two repository Actions secrets per site: DEPLOY_SSH_KEY and DEPLOY_KNOWN_HOSTS. Environment-scoped secrets are an alternative only if the workflow explicitly declares that environment. Give each site its own restricted SSH key. CI should build and test without deploy secrets, then deploy only a previously validated artifact after CI succeeds on the trusted branch.

Commands:

```sh
node scripts/check.mjs
node scripts/build-release.mjs --out release.json
node scripts/deploy.mjs --probe
node scripts/deploy.mjs release.json --dry-run
node scripts/deploy.mjs release.json
```

`probe` reports only the fixed site and receiver-generated version marker. Dry-run reads and validates the complete payload but does not create backups, stage files, alter the marker, or write to the site root. The receiver rejects uploads over 42 MiB of JSON, more than 30 MiB decoded total, more than 15 MiB per file, or more than 2,000 files.

## Tests

From this directory:

```sh
npm test
```

The tests use temporary local folders only. They cover allowlist checks, static copying, local asset references, JS syntax, dry-run with no filesystem writes, safe deployment, receiver marker/probe, backup creation, rollback after a simulated mid-promotion failure, traversal/API/storage/PHP rejection, and secret-pattern rejection. They do not contact cPanel, a browser, analytics, or a CRM.
