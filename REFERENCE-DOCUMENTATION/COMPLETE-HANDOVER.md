# Finprov swipe-page redesign and lead-capture handover

> Deployment scope: read GITHUB-AND-CPANEL-WORKFLOW.md at the package root first. ZIP/backend-install instructions in this reference apply only to a NEW/unconnected installation. Existing connected pages use their repository checks and an approved PR/main deployment; preserve their live API/config and dry_run. DM completes local/mock QA and hands missing server dry-run/live CRM/dashboard checks to the responsible tech/DM owner. A draft or prepared handoff is not a verified live launch.


Current GitHub workflow: read `../GITHUB-AND-CPANEL-WORKFLOW.md` first. Connected repository updates use checked GitHub deployment and preserve the working server API/config/storage and live dry-run setting. New/unconnected pages follow the initial ZIP workflow below, with tech responsible for credentials, mappings, live verification and the deployment connection. This mode distinction supersedes unconditional ZIP/configuration instructions in this reference.

> This handover embeds the complete landing-page skill bundle under `.claude/skills/`, including Impeccable, design direction/implementation/review, accessibility, browser/QA/performance, investigation, release review, security, and image-asset guidance. Approved local Finprov logo and favicon files are included under `BRAND-ASSETS/` and in the release template.

**Audience:** SEO, digital marketing, CRM administration, and deployment teams  
**Outcome:** DM gives Claude a URL/files and approved changes. Claude delivers tested source and a PR for a connected site, or a new private repository and approved initial ZIP/request sheet for a new site. Tech performs initial hosting, CRM and restricted deployment setup; routine approved main merges then deploy automatically.

## 1. What is being handed over

This package has three complementary parts:

1. `CLAUDE.md` — the automatically loaded project instruction for Claude Code.
2. `MASTER-HANDOVER-FOR-CLAUDE.md` — the full autonomous build and acceptance contract.
3. `.claude/skills/` — the complete embedded skill bundle, including the master `finprov-swipe-page` skill, secure LeadSquared/PHP templates, local brand files, QA rules, and the release validator.

The digital marketing operator opens the extracted package root in Claude Code and supplies the URL or files. No separate skill installation or prompt pasting is required.

## 2. Standard operating model

| Stage | Owner | Required result |
|---|---|---|
| Brief and approvals | Digital marketing/SEO | Approved course facts, copy, assets, URL, CTA, and contact details |
| CRM mapping | LeadSquared administrator | Exact source, course value, schema names, key delivery, and test owner |
| Design and build | Claude Code/build agent | Accessible source implementation and production build |
| Tracking setup | Marketing analytics owner | Public IDs, event rules, UTM convention, and tag verification |
| QA and packaging | Build agent + DM | Local/mock QA; PR for connected sites or validated initial ZIP for new sites |
| New private repository | DM / authorized Claude | New campaign repo under finprovteam-cell; no real credentials |
| Initial live CRM verification | Tech / CRM owner | Confirm actual successful lead and approved mappings before launch |
| First hosting/deploy connection | Tech | Fixed destination, protected backend, restricted key/CI, live checks and rollback readiness |

Do not repair minified files in cPanel. Connected changes go through source/PR/main deployment. An initial unconnected handoff may use a validated ZIP; do not repeat backend/template installation for routine updates.

## 3. Inputs the team prepares

- One completed page brief: course name/slug, audience, goal, promise, CTA, delivery mode, locations, duration, curriculum, FAQs, and final domain/path.
- Approved claims and evidence. Placement figures, logos, awards, reviews, photographs, prices, dates, and testimonials need an owner and approval.
- Finprov brand assets and policy URLs.
- Approved phone/WhatsApp numbers and WhatsApp message. WhatsApp remains chat-only and is not registered by this page as a CRM lead.
- Reference URLs/screenshots plus a note identifying what may be reused. References guide design principles, not copying.
- LeadSquared mapping sheet: host, Contact Source, exact course value and field, optional custom schemas, allowed choices, activity/routing rules, and test verifier.
- Public tracking IDs when available: GTM, GA4, Google Ads/conversion label, and Clarity.
- Hosting facts: document root, final HTTPS URL, base path, PHP version/extensions, DNS/SSL/CDN owners.

Do not send live LeadSquared credentials to an AI chat. CRM/deployment staff place them in server `api/config.php` or server environment through an approved secret channel.

## 4. Initial build and launch workflow (tech live steps are separate gates)

1. Open the package root in Claude Code, provide the inputs, and invoke `/finprov-swipe-page` when needed; `CLAUDE.md` also loads the workflow automatically.
2. The agent creates `PROJECT-BRIEF.md`, audits existing source, and records any real blockers.
3. The team approves a design direction. The agent implements source code, not cPanel bundle edits.
4. The agent connects the form to same-origin `api/lead.php`, configures first-touch attribution, and adds optional runtime tags.
5. The agent builds production output at the correct base path and tests that output.
6. On a staging/server host, `dry_run => true` proves the request reaches protected storage without contacting LeadSquared.
7. CRM staff insert real server keys, confirm mappings, set `dry_run => false`, and authorize one live test.
8. The CRM owner confirms the contact, source, course, attribution, and routing. The analytics owner confirms one conversion after success.
9. The release validator passes on both extracted output and the ZIP. `MANIFEST.json` records the source reference, build time, base path, versions, file list, and checksum. Owners approve `QA-REPORT.md`.
10. The uploader backs up the current site, uploads the new ZIP, configures server secrets, runs smoke tests, and retains rollback files.

## 5. LeadSquared design

The safe path is browser form → same-origin PHP → LeadSquared. The access and secret keys never enter HTML or JavaScript. The server validates the submission, rate-limits requests, maps fields, and posts an attribute array to LeadSquared `Lead.Capture`.

`lsq_source` is the stable CRM Contact Source. If marketing needs “Google Ad,” the server configuration must use that exact value, subject to LeadSquared acceptance. UTMs answer a different question: which campaign, ad, medium, or keyword delivered the visit. Use both.

The templates default to `dry_run => true`. They contain placeholders, not working keys. This is intentional. A distributable ZIP with live keys is a security incident, not a complete handover.

### Default form and attribution contract

The page submits name, Indian mobile, optional email, approved profile/segmentation fields, course slug, enquiry details, form location, an empty honeypot, and first-touch attribution. Attribution includes UTMs, Google click IDs, landing URL, and referrer. Only approved LeadSquared fields are sent.

### Success and failure

The page shows success and fires `generate_lead`/Google Ads conversion only after `{ "ok": true }` with `dryRun !== true`. Validation errors, timeouts, 4xx/5xx responses, WhatsApp clicks, and local simulation must not count as conversions.

## 6. Tracking and tags

`site-config.js` contains public IDs only and may be safely edited between environments. A blank ID means “do not load this integration.” The page must work and remain ad-ready without tags.

Choose one tag owner:

- **GTM-owned:** GTM deploys GA4, Google Ads, and Clarity; direct loader IDs remain blank.
- **Direct-owned:** the page loads configured GA4/Ads/Clarity IDs; GTM must not deploy duplicates.

Standard events are `lead_form_submit`, `generate_lead`, `lead_capture_error`, and `phone_click`. Cookie/consent requirements remain an organizational legal/privacy decision and must be implemented consistently.

Suggested Google Ads final URL:

```text
https://FINAL-DOMAIN/PATH/?utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_content={creative}&utm_term={keyword}
```

Google Ads may append `gclid` when auto-tagging is enabled. UTM parameters provide attribution; they do not install a Google tag and do not themselves change the CRM Contact Source.

## 7. Skills used in the redesign pipeline

| Skill | Why the team uses it |
|---|---|
| `design-consultation` | Establish the design system and page direction from the brief and brand. |
| `design-shotgun` | Compare multiple directions before committing when the layout is undecided. |
| `design-taste-frontend` | Build an original, production-quality responsive page. |
| `impeccable` | Polish spacing, hierarchy, copy clarity, interaction, and responsive details. |
| `design-review` | Inspect the implemented page visually and correct inconsistencies. |
| `web-design-guidelines` | Check accessibility, semantics, form usability, and interface conventions. |
| `imagegen` | Produce original supporting visuals when approved assets are unavailable; never fake evidence. |
| `browse` / `qa` | Test real interactions, breakpoints, links, and failure states. |
| `benchmark` | Check load performance, layout stability, and asset weight. |
| `investigate` | Diagnose missing leads, blank pages, caching, or deployment faults before fixing them. |
| `review` | Perform the pre-release code/configuration review. |
| `cso` | Check secret handling, endpoint exposure, dependencies, and operational security. |

The Version 2 builder embeds every required skill locally. A runtime may still lack an external capability such as browser automation, PHP, Lighthouse, or image generation; in that case the relevant skill requires a disclosed fallback and forbids fabricated evidence.

## 8. Release structure

The top of `release.zip` should contain:

```text
index.html
assets/
images/
site-config.js
.htaccess
api/
  lead.php
  config.php              # placeholders only; dry_run true
  config.example.php
  .htaccess
  storage/
    .htaccess
DEPLOYMENT.md
CONFIGURATION.md
QA-REPORT.md
RELEASE-NOTES.md
MANIFEST.json
```

Do not include `.git`, `node_modules`, `.env`, real keys, historical lead logs, macOS metadata, or a redundant outer folder. Keep the source separately in a repository or source archive. If the course is under an existing `/courses/` site, coordinate root `robots.txt` and sitemap ownership with the parent WordPress/domain owner instead of placing conflicting crawl files inside the course directory.

## 9. Uploader checklist

1. Confirm the ZIP validator passed and owners signed off QA.
2. Back up the current document root and existing server config.
3. Extract the ZIP to a staging directory; inspect the top level.
4. Fill or restore the server-only `api/config.php` securely; start in dry-run.
5. Confirm PHP/cURL, writable protected storage, correct base paths, SSL, permissions, and cache behavior.
6. Move the release into the document root and open the final HTTPS URL.
7. Complete dry-run, authorized live LeadSquared, tag, and mobile smoke tests.
8. Purge cache only after references are correct. Retain last known-good files for rollback.

## 10. Known boundaries

- A static host such as a basic Vercel static deployment cannot execute PHP; it needs a compatible API host or equivalent serverless implementation.
- Apache `.htaccess` protections do not apply to every platform; reproduce them with platform controls.
- A local development success may be simulated and is not evidence of LeadSquared capture.
- Google Ads acceptance is decided by Google. This workflow produces a technically suitable public destination but cannot guarantee policy approval.
- LeadSquared schemas and permissions differ by account; the CRM administrator must verify exact field names and accepted values.

## 11. Package map

- `CLAUDE.md` — automatically loaded project instruction.
- `MASTER-HANDOVER-FOR-CLAUDE.md` — complete autonomous build contract.
- `.claude/skills/finprov-swipe-page/SKILL.md` — reusable master workflow.
- `.claude/skills/finprov-swipe-page/references/` — requirements, design, CRM/tracking, QA, and deployment guidance.
- `.claude/skills/finprov-swipe-page/assets/release-template/` — safe PHP/config/public-tag templates with local Finprov logo and favicon.
- `.claude/skills/` — embedded design, Impeccable, QA, investigation, review, and security skills.
- `.claude/skills/finprov-swipe-page/scripts/validate_release.py` — validates a release directory or ZIP and checks secret, path, placeholder, brand-asset, and package hygiene.
