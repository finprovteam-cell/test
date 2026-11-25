# Tech setup — one time per NEW website

DM can create and own the private repo under finprovteam-cell. Tech gets authorized access, reviews source/build/QA and performs server/CRM/deployment setup. Existing connected sites do not need this repeated.

## 1. Hosting and LeadSquared

Confirm final URL and cPanel document root, DNS/TLS, PHP 7.4+ with cURL for the API (the deployment receiver needs PHP CLI 8+), and same-origin endpoint. Do not assume a new directory inherits another page's config. Reuse a backend only after inspecting its config loader and field contract. Keep source for the PHP endpoint with tech; marketing frontend deployment cannot replace it.

The initial template is at `.claude/skills/finprov-swipe-page/assets/release-template/`. Install it only for a new compatible integration, never over an existing live api folder. Tech edits api/config.php on the server through a secure method:

| Config entry | Tech / CRM action |
|---|---|
| lsq_host | Confirm the account's API region. The supplied IN21 host is an example for this deployment, not a universal default. |
| lsq_access_key / lsq_secret_key | Enter real values only on the server. Distributable examples retain REPLACE_ON_SERVER. |
| lsq_source | Exact approved CRM Contact Source, e.g. Google Ad. It is distinct from campaign/UTM values. |
| courses | Map each submitted slug to its exact existing LSQ pick-list value. Never guess from a course title. |
| course_field | Confirm schema name, e.g. mx_Courses. |
| allowed_values | Match submitted profile/enquiryType/formLocation options exactly. |
| field_map | Use confirmed schema names. Leave optional entries blank if unavailable. Confirm mx_Enquiry_Detail before use; blank it if it is not valid. |
| allowed_origins | Default same-origin behavior; only add explicitly required approved origins. |
| activity_code | Leave 0 unless a valid activity integration is required and confirmed. |
| rate_limit / retention / storage_dir | Review limits, access and retention; storage must be protected from HTTP access. |
| dry_run | true during initial validation; false only after server setup for an authorized live test. A dry-run success is not a CRM lead. |

Protect config and storage using the included Apache rules or the host's equivalent. Verify they cannot be downloaded. Never commit real config.php, log contents or live credentials. Confirm the actual lead exists in LSQ with correct source/course/attribution before launch. Do not delete test contacts unless separately authorized and understood.

## 2. Connect GitHub Actions to the fixed site folder

The included deployment-tooling and deploy.workflow.example.yml are reference implementations matching the current static-only receiver approach. They contain no deploy credentials and do not connect a newly created repository automatically.

1. Review the new site's build command, final base path, local references and output. Merge the needed scripts/dependencies into its project; keep framework builds for React/Vite rather than replacing them with the static-copy build. The generic build-static.mjs is covered by tooling tests; build-static-site-example.mjs is the current site/ -> dist/ cache-versioning example and is only suitable after adapting that layout. Adapt site.json with a unique site ID, productionUrl, artifactDir, narrowly allowed static include paths, URL mappings and expected public tracking markers. Do not use root, parent or recursive paths to bypass isolation.
2. On the server, add an explicit fixed site-ID -> document-root policy to the server-owned receiver and tests. The bundled receive.php knows the existing THREE sites only; a new site requires a reviewed code/policy change. Preserve existing policies. Do not replace a production receiver blindly with the bundled snapshot. Install outside public_html with protected locks/backups; never expose it as an HTTP upload endpoint.
3. Create a unique restricted deploy identity for the new site. Keep the private key only in the repo's DEPLOY_SSH_KEY secret and approved tech storage. Pin the independently verified host key in DEPLOY_KNOWN_HOSTS. Do not copy an existing campaign key or disable StrictHostKeyChecking.
4. Authorize its public key with restrict and a forced receiver command fixing the new site ID. The identity must have no general shell or port forwarding. Test a connection probe and that arbitrary commands/other-site/API/PHP/config writes are rejected.
5. Use DEPLOY_ENABLED=false/unset while preparing. Set only the two repository secrets above, reviewed host/port/user, and the exact fixed site.json. The current example uses accountingcourse at finprov.smartserver247.com:9895; confirm those values for the intended server. No LSQ keys are GitHub Actions secrets for this frontend-only workflow.
6. Run build/check, mobile/desktop mock-only smoke tests, release validation and server dry run. Keep API/storage/PHP/hidden server config excluded from static deployment. A one-time backend install is separate from this artifact.
7. After approval, set DEPLOY_ENABLED=true, run deploy.yml on main, and verify the authenticated receiver reports the expected site/revision plus public HTTPS page/assets. Verify the actual correct design, not only HTTP status. Record test evidence and unresolved Cloudflare challenges honestly.

Each successful main deployment retains backups outside public_html. Omitted files are not mirrored/deleted. Failure before deployment stops publication; post-deploy failure needs investigation and may require rollback. Revert the content commit to redeploy a prior version. Set DEPLOY_ENABLED=false to pause future deployments.

## 3. Tracking belongs to a separate verification layer

DM provides public IDs, controls intended GTM container tags/triggers and publishes them. Tech ensures code loads once on the correct page(s). Use the main GTM script high in head and noscript immediately after body; noscript in body is correct. An installed container is not a configured or published container. Existing Tally/Gulf direct GA4/Clarity need no duplicate tags in GTM.

Confirm successful real submissions are counted once, dry-run/failure/WhatsApp/direct thank-you visits are not leads, and no personal data is sent to tracking. Do not claim Google Ads eligibility or attribution-dashboard receipt from CI alone.

## 4. Routine handover to DM

Return the repo URL, production URL, branch/deploy procedure, exact public tracking IDs and owners, and first live CRM verification result. Keep keys/logs private. After this setup DM previews, opens PRs and merges approved changes into main; Actions checks and deploys without a cPanel upload.

Template edition: no HTML page is included. Create campaign HTML/source from the brief. The integration reference contains config.example.php only; Tech creates protected config.php server-side. For an initial distributable ZIP, generate a placeholder-only config.php with dry_run=true. Do not run final release validation on the incomplete reference directory.
