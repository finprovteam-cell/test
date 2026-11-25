---
name: qa
description: Run release-focused functional, responsive, accessibility, CRM dry-run, and tracking QA for a Finprov landing page.
---

# Landing-page QA

Build a test matrix covering:

- page load and asset paths at the final base URL;
- 1440px, 1024px, 768px, 430px, 390px, and 320px layouts;
- keyboard focus, labels, errors, contrast, reduced motion, and tap targets;
- phone, WhatsApp, navigation, privacy, and CTA links;
- required/invalid/valid form states and duplicate-submit prevention;
- same-origin API health and `dry_run => true` storage evidence;
- first-touch UTM plus `gclid`, `gbraid`, and `wbraid` transport;
- public tags only when configured and one conversion only after `{ "ok": true }`;
- absence of live secrets, source maps with secrets, logs, and server storage files in the release.

Never treat a mocked browser success as proof of LeadSquared capture. Record commands, viewports, evidence, failures, owner actions, and limitations in `QA-REPORT.md`.
