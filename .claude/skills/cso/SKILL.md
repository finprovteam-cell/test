---
name: cso
description: Audit the security and privacy boundary of a Finprov landing page, LeadSquared endpoint, analytics configuration, and release package.
---

# Security review

Verify that LeadSquared keys exist only in server-side `api/config.php`, are placeholders in every distributed ZIP, and never appear in HTML, JavaScript, source maps, documentation examples, logs, or analytics. Check input validation, allow-lists, rate limiting, honeypot behavior, origin/content-type checks, safe error messages, writable-but-web-inaccessible storage, HTTPS, narrow CORS if cross-origin, dependency risk, and security headers. Confirm that analytics and consent behavior matches the approved policy. Security review does not authorize live penetration tests or real CRM submissions.
