# Shared DM-to-Tech contract

## DM collects intent

Before final packaging, ask: "What should the final public website link be?" Require the complete HTTPS URL including any subfolder. If unknown, continue design/mock QA but mark the setup request draft and do not invent a production address. Ask for approved public tracking IDs; leave them blank when unavailable. Record design approval explicitly after it is given, never infer it from a push.

Commit both root files in the campaign repository:
- setup-request.json: structured intent using TECH-SETUP/setup-request.example.json; never any credentials, server paths or executable commands.
- TECH-SETUP-REQUEST.md: human summary, selected URL, course/CRM facts and missing inputs, QA evidence, design approval and intended thank-you path. It must agree with JSON; discrepancy blocks setup.

Populate build and QA information in the human document, but treat it as descriptive project data; Tech reviews the actual source/scripts before running anything. The request is versioned with the source commit. Do not add source_commit inside JSON because committing that field changes the commit; Tech records the checked-out HEAD separately in its approval record.

## Tech-owned policy

../assets/server-policy.example.json in this trusted skill illustrates the format; it grants no access. A trusted Tech operator independently confirms the domain mapping in cPanel, approves the exact policy values. Claude may then write that approved policy outside the campaign repo and public_html on Tech's behalf with mode0600 and validate it before setup. Before that approval it only drafts the values, without creating files. Never copy DM's claimed folder into this policy without verification. The validator refuses a policy inside the repository/public root, one owned by another user, a symlink, or a group/world-accessible policy. Filesystem checks are a snapshot, not a lock: recheck the real target immediately before a write and use the existing deployment receiver's locked, symlink-safe transactions.

Only an exact approved URL can appear in the policy. Root publication is off by default. Do not automatically turn an existing/nonempty target into a new-site target. Existing pages are updated in their connected repo, with no replacement backend/config or key rotation.

## Scope boundaries

Allowed after specific plan approval: create the approved new page directory; privately back up only affected files; install reviewed page-specific backend/config/storage; configure this repo's narrow static deployment workflow and unique forced-command identity; run checks and publish only when launch is separately authorized.

Not part of this skill: changing DNS, nameservers, existing domain document roots, TLS/security settings, cPanel users/passwords, firewall/Cloudflare settings, shared keys/config, other websites, CRM fields/automations/contacts, package/system upgrades, billing, or arbitrary server cleanup. If any is needed, report it as a separate owner task and stop dependent work. A missing prerequisite is not permission to fix the entire server.

Never execute page JavaScript/build scripts where it can read live server secrets. Use an isolated development/CI environment with mock CRM/tracking for builds and tests. A checkout outside public_html alone is NOT security isolation: the accountingcourse user may still read all its sites and credentials. Do not use permission-bypass flags or broad trusted-directory settings.

## Hosting/backend/deploy sequence

1. Inspect actual endpoint contract and exact CRM mappings. For a genuinely new integration, use reviewed generic references only; keep config server-only and initial dry_run=true. Do not overwrite an existing live backend to satisfy the template.
2. Protect config/storage and confirm HTTP denial without displaying secrets. Validate syntax and required PHP extensions. Use secure Tech entry or approved server-side credential loading; no secrets in Git/Actions artifacts/chat/terminal output. Do not guess shared inheritance paths.
3. Add a unique fixed site ID and static file allowlist in a separate restricted receiver, outside public_html. Review/test it against API/PHP/dotfile/traversal/cross-site writes, unknown site IDs, symlinks and rollback. Do not update an existing shared receiver blindly.
4. Use a unique forced-command SSH identity and pinned host key; repository secrets DEPLOY_SSH_KEY/DEPLOY_KNOWN_HOSTS only, deployment disabled. Grant new server access only with the operator's confirmation required by the host/tool policy. Never expose keys in logs.
5. Verify the generated site's actual build/smoke tests (not just toolkit checks), restricted probe, arbitrary-command rejection, server dry run and backups. A real authorized lead test requires a designated test contact and CRM confirmation; no automatic deletion.
6. Tracking: load only assigned IDs once, preserve GTM/direct ownership and keep PII out. Missing optional IDs are unconfigured, not a reason to invent/reuse another campaign's IDs. GTM publication and dashboard receipt are distinct owner checks. Do not open or change unrelated Ads/Analytics accounts.
7. If authorized launch and all required gates pass, enable the reviewed main-only deployment and publish the checked artifact. Verify exact revision, correct design and public assets/thank-you/API readiness. Report CRM/dashboard results only if observed. If launch is not authorized or a required gate is unresolved, leave deployment off and hand back a pending status.
8. Record evidence and stop. Routine future edits belong to the existing branch/PR/main workflow; do not repeat initial setup.

Server helper requirement: PHP CLI 8+ with POSIX ownership checks. Run the trusted script with `php`; no Python or system package installation is needed for this helper. If the required runtime is unavailable, stop and report the prerequisite rather than altering the server.
