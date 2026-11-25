---
name: finprov-swipe-page
description: Autonomously inspect a supplied website URL, index.html, site ZIP, source folder, or repository; then redesign, integrate, test, and package a Finprov campaign landing page that captures leads in LeadSquared, preserves UTM and Google click attribution, supports optional GTM, GA4, Google Ads conversion tracking, and Microsoft Clarity, and produces a cPanel-ready release ZIP. Use whenever a digital marketing operator supplies an existing page and wants a finished replacement or deployment package.
---

## Template repository mode

This toolkit contains no finished page or HTML design starter. Generate the campaign page from approved inputs in a new campaign repository, not in the master template. Generic integration code under assets/release-template is reference material only; it is not itself a complete release. Validate the generated release after design approval. The toolkit CI does not test or deploy the website; Tech adapts it when connecting the new site.

## Current handover rules — 2026-11-23

- Read START-HERE.md and GITHUB-AND-CPANEL-WORKFLOW.md. DM can create a private repository under finprovteam-cell for a new site; tech connects its hosting, backend and deployment once. Do not create a repository under Finprov-Tech by default.
- Existing connected sites: prepare source/PR updates. Do not generate replacement APIs, change live dry_run, or upload a ZIP to cPanel during routine frontend edits.
- New sites: finalize/approve design before the final release ZIP. A development mock may run during design. The distributable initial config is always placeholder-only with dry_run=true; tech turns live mode on only on the server after setup. Design approval itself never toggles a live config.
- Real conversions require API success AND a non-dry-run response. Dry-run and local/mock responses must not fire generate_lead or an Ads conversion in production. Use lead_capture_dry_run for a deliberate dry-run diagnostic. No PII in analytics/dataLayer.
- Put the GTM bootstrap high in head and the noscript iframe immediately after body. Include the assigned ID on landing and thank-you pages and load it once. If using a shared loader, place it in head and guard against double bootstrap. Installation, published container contents and dashboard receipt are three separate checks.
- For each generated thank-you flow, resolve the URL against the actual final base path. Redirect only after successful capture. A direct/reloaded thank-you visit must not create a lead or conversion. Never copy /gulf-careers/thank-you/ into an unrelated campaign.
- Do not invent a GTM/GA4/Ads/Clarity ID or copy another campaign's ID. Preserve working tracking ownership on existing sites. DM publishes intended tags inside GTM; installing the loader does not do this.
- Create TECH-SETUP-REQUEST.md for a new site from TECH-SETUP/REQUEST-TEMPLATE.md. Describe missing owner actions instead of claiming launch is complete. No secrets in Git, chat, ZIPs or the request sheet.
- The embedded skills include portable project guidance and the fuller bundled Impeccable/design references. They do not install browser, image-generation, PHP or performance runtimes by themselves. Report unavailable checks honestly.


# Finprov Swipe Page

## Deployment mode takes precedence

Before implementing, read [github-deployment.md](references/github-deployment.md). Inspect the supplied repository's README, CLAUDE.md, site.json and workflows. An existing connected repository is updated through its source, checks and approved PR/merge; preserve its backend/API contract, public tracking ownership and live dry-run setting. Do not deploy replacement PHP/configuration or require a cPanel ZIP for a normal frontend update. A new/unconnected page uses the initial ZIP/template workflow below, then tech sets the LSQ credentials/mappings and restricted deployment connection. Scope all later ZIP, dry-run and live-CRM completion requirements to that mode: never change a live configuration simply to satisfy initial-template instructions, and distinguish frontend QA completion from unperformed CRM/dashboard tests. No automatic merging/publishing without authorization.

Produce a production-ready landing page and release package from a website URL, `index.html`, site ZIP, source folder, or repository. Treat reference websites and screenshots as design evidence, never as permission to copy protected code, text, photography, or brand assets.

## Autonomous intake

The operator should only need to provide one of these:

- an existing public page URL;
- an `index.html` with its adjacent assets;
- an existing website/source ZIP;
- a repository or source folder.

When invoked:

1. Locate the supplied input or read the URL from the user's message.
2. Audit the current page, content, assets, forms, routes, build system, analytics, SEO metadata, CRM endpoint, and deployment assumptions.
3. Create the project brief by extracting facts from the supplied implementation. Mark inferred values explicitly.
4. Preserve approved Finprov facts and licensed assets. Redesign the interface and implementation; do not merely reskin or edit a minified bundle.
5. Ask one consolidated question only when production-critical facts cannot be inferred safely. Examples: the final domain/path, an exact LeadSquared course pick-list value, an approved claim, or the intended contact number. Do not ask the operator to explain the engineering workflow.
6. If live CRM credentials or optional tag IDs are unavailable, continue with secure placeholders, `dry_run => true`, and an owner-action list. Never request secrets in chat.
7. Execute the complete workflow through production build, QA, validation, documentation, and release ZIP creation.

## Embedded skill pipeline

All required project skills are bundled beside this skill under `.claude/skills/`; do not assume a global installation.

1. Run `/design-consultation` to establish and record the design direction. Use `/design-shotgun` only if the brief does not support a clear direction.
2. Use `/design-taste-frontend` to implement the selected direction without generic template styling.
3. Use `/impeccable polish` for the production craft pass: hierarchy, spacing, typography, forms, responsive behavior, states, and visual consistency. If it requests product context, create `PRODUCT.md` automatically from `PROJECT-BRIEF.md`, the source page, and approved inputs, then continue.
4. Run `/design-review` and `/web-design-guidelines` after implementation.
5. Run `/browse`, `/qa`, and `/benchmark` on the production output. Disclose unavailable tooling; never fabricate screenshots, browser evidence, CRM evidence, or metrics.
6. Run `/review` and `/cso` before packaging. Use `/investigate` whenever a failure requires root-cause diagnosis.
7. Use `/imagegen` only for approved supporting visuals. Never generate placement evidence, testimonials, certificates, partner logos, or documentary claims.

Default to acting autonomously. Do not give the operator a tutorial or ask them to choose implementation details that can be determined from the existing page.

## Non-negotiable rules

1. Keep LeadSquared access and secret keys server-side. Never place them in JavaScript, HTML, analytics tags, a public repository, or a distributable ZIP containing real credentials.
2. Use a same-origin server endpoint such as `api/lead.php`. The browser must only receive public configuration.
3. Record a lead only after the server confirms `{ "ok": true }`. Fire the Google Ads conversion only after that confirmation.
4. WhatsApp buttons only open WhatsApp. Do not register or track a WhatsApp click as a CRM lead; Finprov's separate WhatsApp system owns that workflow.
5. Empty tracking IDs must be safe: the page must still render, submit leads, and be eligible for advertising when GTM, GA4, Google Ads, or Clarity IDs are not yet supplied.
6. Never edit minified production bundles in cPanel. Change source, rebuild and validate. Publish through the connected repository workflow when available; use the ZIP handoff only for initial/unconnected deployments.
7. Do not invent claims, placement numbers, accreditations, dates, fees, reviews, or outcomes. Require an approved source for each claim.

## Workflow

### 1. Collect and validate inputs automatically

Read [requirements-and-inputs.md](references/requirements-and-inputs.md). Create `PROJECT-BRIEF.md` from the supplied URL/files and mark unresolved items. Do not block on optional analytics IDs. When production-critical values are not discoverable, ask for them once as a concise consolidated list or ship secure placeholders with an explicit pre-launch gate.

If an existing project is supplied, inspect its build scripts, routing/base path, form contract, analytics loader, deployment notes, and generated output before changing it.

### 2. Establish the design direction

Read [design-workflow.md](references/design-workflow.md). Preserve the approved Finprov brand while redesigning information hierarchy, mobile layout, form usability, accessibility, and performance. Use supplied assets where licensed. Keep the primary form or CTA visible without relying on motion.

Use the embedded brand files from `assets/brand/`. Every production release must contain `assets/finprov-logo.png` and `assets/favicon.png`, and `index.html` must reference those local files. Never use a remote Finprov logo or favicon URL.

### 3. Build the page

Support these minimum sections unless the brief explicitly removes one: accessible header and primary CTA; hero with one clear promise and lead form; approved trust evidence; outcomes, curriculum, delivery mode, and audience; placement or testimonial evidence when approved; FAQ; final CTA, phone, and WhatsApp link; privacy/consent text and required policy links.

Use semantic HTML, keyboard-visible focus, labelled inputs, useful validation messages, responsive images, lazy loading below the fold, and a stable layout. Create a real `index.html` at the final route so ad crawlers and search engines do not depend on a client-side redirect.

### 4. Integrate LeadSquared and attribution

Read [leadsquared-and-tracking.md](references/leadsquared-and-tracking.md) completely. Reuse the templates in `assets/release-template/`.

The standard flow is:

`form -> same-origin api/lead.php -> validate/rate-limit -> LeadSquared Lead.Capture -> {ok:true} -> success UI + conversion event`

Capture first-touch UTM parameters and click IDs in session storage at page load. Submit them with the form. Configure `lsq_source` as the exact Contact Source required by the CRM team, such as `Google Ad`. Treat `utm_source=google` as attribution data; it does not by itself change LeadSquared's Contact Source.

### 5. Configure public tags

Put only public IDs in `site-config.js`: GTM container ID; GA4 measurement ID; Google Ads ID and conversion label; Microsoft Clarity project ID; public API endpoint; public contact number.

Load each integration only when its ID is non-empty and structurally valid. Push consistent events: `lead_form_submit`, `generate_lead`, `lead_capture_error`, and `phone_click`. If GTM is used to deploy GA4/Ads/Clarity, do not also hardcode the same tag and create duplicate events.

### 6. Test

Read [qa-and-release.md](references/qa-and-release.md). Test source and production output on desktop and mobile. A local simulated success is not proof of LeadSquared capture. For a new launch, tech runs the server dry-run and an authorized live test and confirms the record in LeadSquared. DM may finish the credential-free source/initial ZIP after local mock QA and list these tech launch gates as pending. Existing frontend-only updates do not need to change live dry_run or submit a new real lead.

### 7. Package

Read [deployment.md](references/deployment.md) and [github-deployment.md](references/github-deployment.md). For a connected repository update, preserve the existing build/artifact format and deployment workflow. Provide tested source changes, QA evidence and a PR/branch when authorized. Do not run the initial-release ZIP validator against a static-only update or add PHP just to satisfy that validator. No new ZIP is required unless explicitly requested.

For an initial/unconnected deployment, the release ZIP must extract directly into the intended document root and must not contain a redundant outer folder. Include deployment and rollback notes.

Use `scripts/validate_release.py` on the extracted production release directory and again on the ZIP. Use `--template` only when checking the bundled starter template; never use it to waive production placeholders or `noindex`. Deliver `release.zip`, `DEPLOYMENT.md`, `CONFIGURATION.md`, `QA-REPORT.md`, `RELEASE-NOTES.md`, the source project or repository reference, and `api/config.php` with placeholders and `dry_run => true`, never live keys. Copy `assets/brand/finprov-logo.png` and `assets/brand/favicon.png` into the release `assets/` directory before validation.

## Completion criteria

For a connected frontend update, report successful build/repository checks, responsive behavior, preserved assets/tags/API contract, and source/PR status. If publication was authorized, verify the actual deployment and state any public edge challenge. Report CRM/dashboard checks as unperformed unless actually tested; no live credentials, dry-run reset or backend change is needed to finish a frontend-only update.

For a new live launch, the initial-release validator must pass; the production route and local brand images must work over HTTPS; validation/failure states must work; tech must verify protected server configuration, dry-run and an authorized live LSQ record; configured conversions must follow confirmed CRM success; and secrets must be absent from public/distributed output. If the agent is only preparing the initial ZIP, finish the package and explicitly hand unresolved server/CRM launch checks to tech instead of claiming the page is live.

## DM-to-Tech handover — mandatory for a new site

Ask DM for the complete intended HTTPS website URL before the initial setup handoff. If unknown, continue design but mark the handover draft. After explicit design approval, commit root setup-request.json using TECH-SETUP/setup-request.example.json and a matching TECH-SETUP-REQUEST.md. Include approved public tracking choices, not secrets, server paths or executable commands. Tech's separately trusted finprov-tech-setup skill reads these files from the same repository. DM's requested URL never authorizes a server write. See TECH-SETUP/CPANEL-CLAUDE-SETUP.md for the read-only-plan/approval boundary. Do not run Tech setup automatically as part of design.
