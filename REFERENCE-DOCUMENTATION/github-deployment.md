# Publishing workflow: marketing and tech responsibilities

This workflow is part of the master handover and applies before the older ZIP-only release instructions. Claude should select the correct mode automatically from the supplied repository and deployment configuration.

## The key distinction

“No cPanel login for updates” means after tech has completed the one-time server setup. It does not mean that LeadSquared credentials or a PHP backend appear automatically.

| Situation | Marketing / Claude | Finprov tech / CRM owner |
|---|---|---|
| Update a page already connected to GitHub deployment | Edit the existing repository; preview and test; submit a pull request; merge the approved change | No routine cPanel upload; assist only if backend/server changes are needed |
| Create a new landing page | Use a fresh builder workspace; finalize design; deliver source and validated initial ZIP with placeholder-only server config | Create/map the server directory/domain; install the backend; set credentials and exact CRM mappings; test; connect the DM-created private repo using a restricted deployment identity |
| Rotate LSQ keys, change CRM schema/routing, move servers, or repair the backend | Supply the business requirement; preserve the working frontend contract | Update server configuration/backend securely; verify the integration |

## A. Existing connected page: normal marketing workflow

1. Obtain access to the correct private repository. Clone it locally and open that repository in Claude Code. Do not build an unrelated replacement folder from a screenshot when editable source exists.
2. To use the full embedded redesign skills from this handover, copy its `.claude/skills/` into the clone, merging folders without replacing unrelated project skills. Preserve the repository's own `CLAUDE.md`, README, deployment workflow, `site.json`, contact details and tracking settings. Supply this workflow/master handover to Claude as additional guidance. Do not copy the builder's root `CLAUDE.md` over the repository's file.
3. Tell Claude the requested change and existing URL. Claude reads the repository instructions, inspects the implementation and preserves the existing backend endpoint, payload, source/course mappings and tracking ownership. It previews the page locally; production credentials are not needed for local mock tests.
4. Finalize the design before publishing. Use a feature branch and pull request so checks run without changing production. Claude can perform the Git steps when authorized; operators do not need to learn the build internals.
5. Review the preview and check results. Merge an approved change into `main`. A plain feature-branch push does not publish the live page.
6. GitHub Actions installs dependencies, builds the production artifact, validates paths/JavaScript/secrets, runs mobile/desktop browser checks and local mock-only form tests where implemented, then deploys the checked artifact if the check job passes.
7. Open Actions and the live page. The workflow confirms the deployed revision. Real CRM receipt and analytics-dashboard receipt are separate checks; mocked tests do not prove them.

Routine connected updates do not require a ZIP, a cPanel login, a replacement `config.php`, or changing `dry_run`. Do not reset a working live configuration to dry-run mode.

## B. New landing page: DM creates the repository; tech connects hosting

1. DM creates a new **private** repository under **finprovteam-cell** for the campaign, or authorizes Claude to create it in that account. Open a fresh builder workspace with the URL, HTML/assets, source ZIP or brief. Keep campaigns separate; no existing repository, GTM ID, domain or deployment key is a default for the next site. Creating a repository alone does not publish the page.
2. Claude completes design, responsive implementation and local QA. After approval, it produces source plus an initial deployment ZIP and the required configuration/QA documents. Its initial `api/config.php` contains placeholders and `dry_run => true`; real keys must not enter the ZIP, repository or chat.
3. Tech confirms the actual production domain, document root, PHP/cURL support, TLS, storage protections and same-origin API route. Tech installs or explicitly reuses a compatible backend. A new directory or hostname does not automatically inherit another page's configuration.
4. Tech securely sets the LeadSquared regional host, access key, secret key, exact Contact Source, course field/pick-list values and approved custom-field mappings on the server. Where an existing backend deliberately loads a parent config, inspect that loader before relying on inheritance; do not assume it.
5. Tech runs a dry run and checks validation, failure behavior and protected storage. Then tech enables live mode (`dry_run => false`) and performs an authorized live submission, with CRM confirmation of the contact and intended source/course attribution. Leave live mode unchanged during later frontend deployments.
6. DM creates a new private repository under **finprovteam-cell**, commits the credential-free source and grants the tech team the access needed for setup. Tech reviews the build/base path and configures CI plus a new restricted server deployment identity. Deployment remains disabled until checks and server dry runs pass. Store deployment credentials as GitHub Actions secrets; LSQ keys remain in server-only configuration.
7. Tech authorizes the site-specific deployment key, enables deployment, performs the first checked deployment and verifies the live page and protected backend. After this one-time connection, use workflow A for future frontend changes.

Tech can perform setup through cPanel or another authorized server administration method. Someone must have server access initially; marketing does not need that access for normal changes afterward.

## What the current deployment protects

The three connected repositories update only allowlisted static page files in a fixed site directory. Their receiver excludes `api/`, `storage/`, PHP, `config.php`, hidden server files and unrelated site directories. It backs up replaced files outside `public_html`, installs assets before HTML, and rolls back partial promotion failures. It does not mirror/delete the entire document root.

LeadSquared keys stay in server configuration and are read by the existing PHP endpoint on a form submission. GitHub receives a separate restricted deployment key; that key is not a LeadSquared API key. Source-only frontend updates therefore do not require re-entering CRM credentials.

Do not overwrite the production `api/` folder with the builder template. Treat a backend implementation change as separate tech-reviewed work; the static-only workflow intentionally cannot deploy it. Treat `server-reference/` files as reference material, never as live configuration.

## Current private repositories (owner: finprovteam-cell)

| Page | Repository | Current URL | GTM |
|---|---|---|---|
| Tally Prime Kerala | https://github.com/finprovteam-cell/landing-tally-prime-kerala | https://accountingcourse.in/courses/tally-prime-kerala/ | GTM-WCRZ68QG |
| Gulf Careers | https://github.com/finprovteam-cell/landing-gulf-careers | https://accountingcourse.in/gulf-careers/ | GTM-PV2S2LGB |
| Online Courses | https://github.com/finprovteam-cell/landing-online-courses | https://accountingcourse.in/courses/online-courses/ | GTM-PX958352 |

These are examples and current connected sites, not defaults for a new campaign. Do not reuse their URLs, IDs, phone numbers or course mappings for an unrelated page. Other existing pages are not automatically connected merely because they are on the same server.

## Tracking and verification

Preserve the repository's actual tracking strategy. Gulf and Tally currently retain direct GA4/Clarity alongside a separately installed GTM container; Online uses an explicit head GTM bootstrap with duplicate loading prevented by tracking.js. Do not switch `tagMode` or add a second loader just because a GTM ID is populated. Publishing tags inside GTM is a separate marketing action. Coordinate any migration to prevent duplicate analytics/conversions.

GitHub runner HTTPS requests were challenged by Cloudflare during setup. The workflow first verifies the exact revision through the restricted authenticated receiver; an explicit Cloudflare challenge on the additional public check is reported as a warning that runner-based public reachability is unverified. Other HTTP errors and wrong revisions fail. A green deployment does not prove AdsBot approval, CRM receipt or analytics-dashboard receipt. Do not disable security protection simply to make CI green.

## Recovery and exceptions

Revert the problematic content commit through an approved pull request and merge it into `main` to redeploy prior content. If publishing must stop, tech sets repository variable `DEPLOY_ENABLED=false`. Server backups support tech-led recovery. A public verification failure after a completed transfer needs investigation; it does not automatically restore an older version.

If no connected workflow exists, do not claim automatic deployment. Deliver the approved ZIP/source to tech, identify the missing setup and stop before changing production. New CRM mappings, keys, PHP behavior, DNS, TLS, server rules and deployment access remain tech/CRM responsibilities.

## Short prompt for a connected repository

> Use the embedded Finprov skills to update this existing landing page: [URL]. Read this repository's CLAUDE.md, README, site.json and workflows first, then GITHUB-AND-CPANEL-WORKFLOW.md. Preserve its LeadSquared endpoint, form payload, server-only configuration, tracking IDs and ownership. Make the requested design/content changes, preview and test them, and prepare a feature branch and pull request. Do not reset dry_run, replace api/config.php, change deployment destinations or merge/publish without the user's authorization. Use the existing checked GitHub deployment after design approval; a routine update does not need a cPanel ZIP upload. State exactly which tests ran and whether production/CRM/dashboard verification occurred.

## Verified current state — 2026-11-23

Ownership and check/deploy workflows were verified after transfer to finprovteam-cell. All three remain private. Finprov-Tech retains write collaborator access; finprovteam-cell is owner. Public HTTPS pages returned HTTP 200 and the expected code revisions. These are historical installation/deployment checks, not a promise of future uptime or evidence of live CRM or conversion receipt.

- Tally check/deploy: https://github.com/finprovteam-cell/landing-tally-prime-kerala/actions/runs/41987358350
- Gulf check/deploy: https://github.com/finprovteam-cell/landing-gulf-careers/actions/runs/41987362067
- Online check/deploy: https://github.com/finprovteam-cell/landing-online-courses/actions/runs/41987366318

Tally sends the dataLayer event `finprov_lead` after successful API confirmation. Gulf and Online send `generate_lead`. Preserve these actual contracts when updating existing sites; do not standardize event names blindly. The current Gulf direct GA4 implementation already sends generate_lead, so an additional GTM GA4 event can double count it. The package starter suppresses real conversion events in dry run; do not claim this template behavior has been installed in existing production pages.

DM owns the actual container tags/triggers, Preview testing and publishing in GTM. An installed GTM ID is not evidence that the container contains published GA4/Ads/Clarity tags. Do not mark container configuration complete based only on HTML placement or the presence of gtm.js. New sites need their own approved public IDs; blanks mean not configured, not a successful integration.
