# QA and release checklist

> Deployment scope: read GITHUB-AND-CPANEL-WORKFLOW.md at the package root first. ZIP/backend-install instructions in this reference apply only to a NEW/unconnected installation. Existing connected pages use their repository checks and an approved PR/main deployment; preserve their live API/config and dry_run. DM completes local/mock QA and hands missing server dry-run/live CRM/dashboard checks to the responsible tech/DM owner. A draft or prepared handoff is not a verified live launch.


Apply [github-deployment.md](github-deployment.md) first. Existing connected updates use the repository's static artifact checks and local mock tests; preserve the live backend/dry-run setting and report unperformed CRM/dashboard checks honestly. The initial ZIP/configuration and dry-run-to-live checklist below applies to new/unconnected installations. A frontend-only task does not authorize a live CRM submission or configuration reset.

## Functional

- All navigation, phone, WhatsApp, CTA, policy, and download links work.
- Required/optional fields and allowed choices match the brief.
- Valid, invalid, duplicate, rapid, offline, timeout, and server-error submissions show correct states.
- Submit is disabled while pending and double submission is prevented.
- Success appears only after `{ok:true}`.
- WhatsApp opens the approved number and message but creates no CRM lead.

## CRM and tracking

- Dry-run log works; authorized live test appears under the expected source and course.
- Campaign, medium, content, referrer, and configured click IDs are correct.
- Duplicate phone behavior is accepted by the CRM owner.
- No tag loads when its ID is blank.
- `lead_form_submit` fires once when submission begins.
- `generate_lead` and Ads conversion fire once after CRM success; neither fires after a 4xx/5xx response.
- Consent-mode/cookie requirements follow the organization's privacy policy.

## Browser, responsive, and accessibility

- Latest Chrome, Safari, Firefox, and Edge; current iOS Safari and Android Chrome.
- 320, 375, 768, 1024, and 1440 CSS-pixel widths.
- Keyboard-only operation, logical headings, visible focus, labels, contrast, alt text, reduced motion, and 200% zoom.
- No horizontal scrolling, clipped controls, layout shifts, or essential text baked into images.

## Performance and crawlability

- Final URL is public HTTPS, not localhost or preview-only.
- Final route returns 200 and has a real HTML document.
- Title, description, canonical, OG image, favicon, and indexing policy are correct.
- Images are resized/compressed; non-critical media lazy-loads.
- No unintended source maps or oversized unused libraries in production.

## Release contents

- No `.git`, `node_modules`, `.env`, macOS metadata, source secrets, or historical lead logs.
- `api/config.php` contains placeholders only and `dry_run => true`.
- Server rules protect configuration and storage.
- `assets/finprov-logo.png` and `assets/favicon.png` exist, decode as images, and are referenced locally by `index.html`.
- No Finprov logo or favicon is hotlinked from `finprov.com`, SwipePages, or another external host.
- Deployment, configuration, QA, release notes, and rollback instructions are present.
- ZIP extracts directly into the intended document root.
- `MANIFEST.json` records source reference, UTC build time, base path, tool versions, release files, and SHA-256 checksum.
- Root `robots.txt` and sitemap ownership is documented; a course subdirectory does not overwrite the parent site's crawl files.
