---
name: investigate
description: Diagnose missing leads, blank pages, stale assets, tag failures, or cPanel deployment faults before changing code.
---

# Investigation

Do not guess. Collect the exact symptom, inspect the deployed HTML and headers, trace the relevant code and configuration, and reproduce safely. Form a specific hypothesis and test it before editing. For missing CRM leads, separate browser validation, API response, server logs, LeadSquared response, schema/pick-list rejection, and UI filtering. For stale assets, verify both the asset URL and the live HTML reference before blaming cache. Apply the smallest root-cause fix, repeat the original reproduction, and document evidence and rollback notes.
