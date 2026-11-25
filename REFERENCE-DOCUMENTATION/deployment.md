# Deployment and rollback

> Deployment scope: read GITHUB-AND-CPANEL-WORKFLOW.md at the package root first. ZIP/backend-install instructions in this reference apply only to a NEW/unconnected installation. Existing connected pages use their repository checks and an approved PR/main deployment; preserve their live API/config and dry_run. DM completes local/mock QA and hands missing server dry-run/live CRM/dashboard checks to the responsible tech/DM owner. A draft or prepared handoff is not a verified live launch.


Read [github-deployment.md](github-deployment.md) first. Its mode distinction takes precedence: connected frontend updates use the existing checked repository deployment and preserve backend/config/storage. The cPanel upload and `dry_run` setup below are for initial/unconnected installations by tech, not routine marketing updates. A replacement credential template must never overwrite a working live configuration.

## cPanel / Apache / PHP

1. Back up the current document root as a dated ZIP.
2. Extract the release into a staging directory and inspect its top level.
3. Replace the placeholders in `api/config.php` through a secure channel on the server, keep `dry_run => true`, and confirm permissions.
4. Confirm PHP 7.4+ and cURL. Make `api/storage` writable by PHP but inaccessible over HTTP.
5. Move the validated release contents into the target document root.
6. Run the documented health check, open the public HTTPS URL, and complete a dry-run test.
7. Set `dry_run => false`, run one authorized live test, and verify LeadSquared.
8. Purge CDN/server/browser cache only after the files and HTML references are correct.

## Other hosts

Static platforms cannot execute PHP. Host `lead.php` on a supported server or replace it with a serverless endpoint with equivalent secret handling, validation, logging, and rate limiting. If the endpoint is cross-origin, configure a narrow CORS allow-list and handle preflight requests.

## Base paths

The frontend build base must equal the public directory. For `/courses/example/`, asset and API URLs must resolve from that path. Test generated output directly, not only the development server.

## Rollback

Keep the last known-good release and server config separately. Roll back by restoring prior public files while preserving the correct server-only configuration and operational logs. Record who performed the rollback, why, and the time.

## Ownership after handover

- Digital marketing/SEO owns the brief, copy, assets, UTM convention, public tag IDs, and content approval.
- CRM administration owns LeadSquared keys, schema, pick-list values, source/routing rules, and live-record verification.
- The build agent owns implementation, tests, release notes, and the validated ZIP.
- The deployment operator owns backups, server secrets, upload/extraction, permissions, SSL/cache checks, smoke tests, and rollback.

