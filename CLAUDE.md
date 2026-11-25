## Role routing takes precedence

For a Tech initial-setup request, use only finprov-tech-setup and its setup contract; the DM design pipeline below does not apply. For page building/redesign, use finprov-swipe-page and leave server setup to Tech. A repo link alone permits inspection and a plan, not production publication or new security access. Neither role may expand into the other automatically.

# Template repository mode — takes precedence

Read README.md and START-HERE.md. This repository is a reusable builder, not an existing website. If the origin is named finprov-landing-page-template, maintain the toolkit only: ask the operator to use the template for a new campaign before generating a page here. In a generated campaign repo, build original website source from the supplied brief/URL/files. No HTML/design starter is supplied. Generic integration code under the skill assets is reference material, not a complete release. Create the HTML, style, routes and framework files appropriate to the brief. Keep project-local skills and instructions for future edits.

For no laptop installation use Claude Code on the web with this campaign repo connected; Codespaces is optional for browser-based preview. No authenticated service, paid tool or external browser runtime is bundled.

The included .github workflow checks only the builder toolkit. It never deploys. Tech must adapt CI to the generated website and configure hosting/LSQ/restricted deployment once per new campaign. Do not claim build/CRM/deployment success from toolkit checks alone.

# Finprov landing-page automation workspace

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


@MASTER-HANDOVER-FOR-CLAUDE.md

## Deployment mode: read first

Read `GITHUB-AND-CPANEL-WORKFLOW.md` before acting. For an existing connected repository, work in that repository, preserve its own instructions/workflow and server backend, and deliver tested source changes plus a branch/PR. A routine frontend update must not generate or deploy replacement PHP/configuration, reset live `dry_run`, or require ZIP upload. For a new or unconnected page, follow the original initial ZIP workflow below; tech configures LSQ keys/mappings and the deployment connection once. This mode distinction takes precedence over unconditional ZIP/configuration clauses elsewhere in this package. Local previews and mock tests require no live credentials. Do not merge or publish without authorization.

Before doing any page work, read and follow `MASTER-HANDOVER-FOR-CLAUDE.md` completely. It is the authoritative end-to-end contract for design, LeadSquared integration, tags, QA, packaging, deployment boundaries, and required outputs. Use the project skill `/finprov-swipe-page`; its complete references, PHP/tracking templates, release validator, Finprov logo, and favicon are under `.claude/skills/finprov-swipe-page/`.

The complete supporting skill bundle is embedded under `.claude/skills/`. Use it rather than assuming global skills are installed. The required pipeline is: `/design-consultation` (and `/design-shotgun` only if direction is undecided), `/design-taste-frontend`, `/impeccable polish`, `/design-review`, `/web-design-guidelines`, `/browse`, `/qa`, `/benchmark`, `/review`, and `/cso`. Use `/investigate` for defects and `/imagegen` only for permitted non-evidentiary supporting visuals. If Impeccable requests product context, create `PRODUCT.md` yourself from `PROJECT-BRIEF.md`, the supplied page, and approved inputs, then continue without asking the operator technical questions.

When the user supplies a public landing-page URL, `index.html`, website ZIP, source folder, or repository, immediately use the project skill `/finprov-swipe-page` and complete the entire workflow.

The user is a digital marketing operator, not the implementation engineer. Do not teach them the build process or ask them to choose technical architecture. Inspect the supplied page, infer safe values, redesign and rebuild it, integrate the provided LeadSquared and analytics templates, test the production output, and return a validated deployment ZIP.

Ask one consolidated question only for facts that cannot safely be inferred and would make production incorrect. Never request live LeadSquared secrets in chat. Use placeholder server configuration and `dry_run => true` until the authorized server owner inserts credentials.

For initial/unconnected releases, required outputs go in `OUTPUT/`: `release.zip`, `PROJECT-BRIEF.md`, `CONFIGURATION.md`, `DEPLOYMENT.md`, `QA-REPORT.md`, `RELEASE-NOTES.md`, `MANIFEST.json`, and a SHA-256 checksum. For connected updates, use the repository's existing artifact/check workflow and supply the source diff/PR, QA evidence and applicable documentation updates instead.

Do not declare completion unless the release contains local `assets/finprov-logo.png` and `assets/favicon.png`, the HTML references those local files, and the release validator passes.

Treat each extracted builder folder as one campaign workspace. Before starting, check `INPUT/` and `OUTPUT/` for artifacts from a different campaign and stop rather than silently mixing projects.

## DM-to-Tech handover — mandatory for a new site

Ask DM for the complete intended HTTPS website URL before the initial setup handoff. If unknown, continue design but mark the handover draft. After explicit design approval, commit root setup-request.json using TECH-SETUP/setup-request.example.json and a matching TECH-SETUP-REQUEST.md. Include approved public tracking choices, not secrets, server paths or executable commands. Tech's separately trusted finprov-tech-setup skill reads these files from the same repository. DM's requested URL never authorizes a server write. See TECH-SETUP/CPANEL-CLAUDE-SETUP.md for the read-only-plan/approval boundary. Do not run Tech setup automatically as part of design.
