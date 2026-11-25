# Before the maintainer leaves

## Ownership and access

- This template and Digital Skills repository transferred to finprovteam-cell on 23 November 2026. The other three campaign repositories already belong to that account. Verify successor access and recovery before departure.
- Ensure an authorized successor controls the company GitHub account/recovery, hosting/cPanel/DNS/Cloudflare, LeadSquared, GTM/GA4/Ads and Clarity. Use individual collaborators and named owners; do not distribute one employee's personal login or tokens.
- Review collaborators and third-party GitHub Apps after transfer; connect the team's authorized Claude accounts to the required private repos. Template creation does not inherit secrets, production connections or permission settings.
- Verify existing Actions secrets/variables and forced-key server mapping with a probe and an approved deployment. Do not rotate working keys merely because a repo moved; review/rotate employee-controlled credentials through the company's approved secret manager and verify after rotation before removing old access.
- Record the successor/CRM/DM approvers privately. Never paste recovery codes, LSQ keys or deploy private keys into this repo, chat or handover ZIP.

## Every new site

DM creates a private campaign repo with Use this template, designs/tests with Claude, obtains design approval and supplies TECH-SETUP-REQUEST.md. Tech chooses the final path/domain, installs a compatible backend with protected server-only configuration, checks exact LSQ mappings, runs dry-run and an authorized live CRM test, adds the new fixed receiver policy and unique restricted key, and sets the repo secrets. Adapt the workflow to that website's actual build and smoke tests. Enable deployment only after review. No generic template or new GitHub repo automatically registers a site in cPanel.

## Routine updates

DM opens the existing campaign repo, previews changes, opens a PR, reviews checks and merges after approval. Connected main deploys static files only. Server api/config.php, PHP/backend and storage stay protected. Verify production design/assets and expected tracking afterward; a green toolkit check is not production verification.

## Current Digital Skills situation

See DIGITAL-SKILLS-MIGRATION.md. Digital Skills is now deployed with its own restricted connection. Tracking was intentionally left unconfigured and actual CRM delivery was not tested. Template creation alone does not configure any future website.
