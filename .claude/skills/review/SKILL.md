---
name: review
description: Perform a release-blocking code and configuration review of a Finprov landing-page package before ZIP creation.
---

# Release review

Review the source and generated release for functional defects, broken relative paths, incorrect base URLs, unsafe secret handling, placeholder leakage, hardcoded claims, unreachable form states, duplicate tags, CRM success shown before server confirmation, wrong LeadSquared mappings, and files that should not ship. Prioritize findings by user impact and block release for broken forms, exposed credentials, missing local brand assets, invalid HTML references, or `dry_run => false` in the distributable template. Re-run the validator after corrections.
