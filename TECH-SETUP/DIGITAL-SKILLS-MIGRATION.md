# Digital Skills migration — completed 23 November 2026

Production: https://finprovdigitalskills.in/
Current private repo: https://github.com/finprovteam-cell/Swipe-Pages-DM
Verified owner: finprovteam-cell.

The fetched production bundle matched the original local September release almost exactly (the live text removed “12-week” from one fragment). This repository had zero Actions workflows at inspection. It is separate from the three connected accountingcourse.in campaign repositories.

The supplied release.zip is an HTML/CSS/JS redesign, replacing the prior React/Vite frontend. Its public config uses FINPROV_SITE_CONFIG; the old live page uses FINPROV_CONFIG. All new ZIP tracking IDs are empty. The ZIP's PHP config has placeholder LSQ keys/course mapping and dry_run=true. Its courseSlug digital-marketing-specialist-program differs from the old source's digital-marketing-specialist. Do not blindly extract the entire ZIP over production or retain an old config without verifying this new contract.

Safe work sequence:
1. Transfer the original private repository if desired; verify canonical owner/permissions after acceptance. Keep existing history and any uncommitted local work safe.
2. Import the new design on a separate branch/PR using a clean checkout. Preserve the original design/history and take an authenticated server backup before changing production. Exclude real api/config.php and logs from Git and artifacts.
3. Tech inspects the ACTUAL server backend/config and reconciles payload, submitted course slug, exact LSQ course value, schema fields and origin. Either adapt the new frontend to the working backend or separately review/install the new backend. Static deployment must never silently replace it.
4. Obtain approved Digital Skills GTM/GA4/Ads/Clarity IDs from DM; do not reuse Tally, Gulf or Online campaign IDs. Existing live public config was also blank when inspected. Configure one owner per integration; verify GTM published tags separately from script loading.
5. Validate desktop/mobile, relative assets, form mock success/failure/dedup, thank-you flow, no dry-run conversions, privacy links and correct canonical. Then Tech performs authorized live CRM and server checks. An HTTP503 seen on one health request is inconclusive and needs investigation; do not claim CRM failure or success from it.
6. Connect a new unique Digital Skills static deployment identity and FIXED document-root policy after confirming the domain's actual cPanel document root. The existing receiver only recognizes the other three sites. Do not reuse their keys or invent a destination path.
7. After approval, deploy the exact reviewed build, verify live design/form/tracking and record rollback evidence.

## Completed setup and current operating contract

PR https://github.com/finprovteam-cell/Swipe-Pages-DM/pull/1 merged the supplied static design after adapting it to the existing backend. The old React source remains in Git history. Edit site/ in the campaign repo. Workflow run https://github.com/finprovteam-cell/Swipe-Pages-DM/actions/runs/45123937415 passed both check and deploy. Verified deployed revision: e72dfe9e07479d0d4857b55a916d85f6e3485092. Production HTML matched the checked build, and the page, thank-you route, logo, favicon and API health returned HTTP200.

The backend was not replaced by the ZIP. Its shared-config loader had pointed at nonexistent public_html/api/config.php; this was corrected to the existing server-only public_html/courses/api/config.php after a private backup. The site config retains source Google Ad, course value Digital Marketing and field mx_Courses. Health now reports live/configured; no real CRM submission was performed, so CRM delivery remains unverified.

The frontend sends course digital-marketing-specialist, enquiryType Demo class, profile values accepted by the old backend, and formLocation hero/popup. Blank tracking IDs remain intentionally unconfigured at the owner's request. Mobile/desktop tests used mocked success, failure and dry-run responses; dry runs and thank-you reloads do not convert.

A separate receiver /home/accountingcourse/landing-deploy/receive-digital.php accepts only fixed site digital-skills and the verified document root /home/accountingcourse/public_html/finprovdigitalskills.in. The other three sites' receiver/policies were left unchanged. A unique restricted key, pinned host key, repo secrets and DEPLOY_ENABLED=true connect main-only deployment. Static artifacts exclude PHP, API/config and storage; arbitrary SSH shell commands were verified rejected. Server backups are private and outside public_html.

Future edits: work in this campaign repo, open a PR, review checks, merge after approval, then verify Actions and production. Do not use this blank template to replace the existing backend. Add approved tracking IDs only when DM supplies them, and verify container publication/dashboard receipt separately. A real authorized CRM test remains a launch/operations check, not proof supplied by mock CI.

This master template still contains no campaign HTML/design code. The preceding inspection notes document the migration hazards; use this completed-status section and the campaign repository README for current operation.
