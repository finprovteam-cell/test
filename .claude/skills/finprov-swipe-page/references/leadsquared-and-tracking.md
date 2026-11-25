# LeadSquared, attribution, and tag contract

> Deployment scope: read GITHUB-AND-CPANEL-WORKFLOW.md at the package root first. ZIP/backend-install instructions in this reference apply only to a NEW/unconnected installation. Existing connected pages use their repository checks and an approved PR/main deployment; preserve their live API/config and dry_run. DM completes local/mock QA and hands missing server dry-run/live CRM/dashboard checks to the responsible tech/DM owner. A draft or prepared handoff is not a verified live launch.


For existing connected pages, preserve the deployed endpoint, payload, tracking ownership and server configuration. Read [github-deployment.md](github-deployment.md). Reuse the template below only for a new installation or a separately authorized backend change; do not assume a new page automatically inherits credentials. Tech owns keys/mappings and dry-run-to-live setup. Frontend updates need no LSQ secrets and must not reset live mode.

## Architecture

The browser posts JSON to a same-origin PHP endpoint. PHP validates and rate-limits the request, builds the LeadSquared attribute array, and calls the regional LeadSquared API. Credentials remain on the server.

Do not call LeadSquared directly from browser JavaScript. A public browser request would reveal both keys.

## Standard request

```json
{
  "name": "Test Lead",
  "phone": "9876543210",
  "email": "test@example.com",
  "profile": "Student / fresher",
  "course": "digital-marketing-specialist",
  "enquiryType": "Demo class",
  "enquiryDetail": "Landing page enquiry",
  "formLocation": "hero",
  "website": "",
  "attribution": {
    "source": "google", "medium": "cpc", "campaign": "course_search",
    "content": "headline_a", "term": "digital marketing course",
    "gclid": "...", "gbraid": "...", "wbraid": "...",
    "landingPage": "https://example.com/?utm_source=google",
    "referrer": "https://www.google.com/"
  }
}
```

## LeadSquared mapping

Minimum attributes are `FirstName`, `Phone`, `Source`, and the configured course field. Add `EmailAddress` when present. `Source` is taken from server config (`lsq_source`), not from the UTM query string.

| Page data | LeadSquared attribute |
|---|---|
| approved course value | `mx_Courses` or configured course field |
| campaign | `SourceCampaign` |
| medium | `SourceMedium` |
| content | `SourceContent` |
| referrer | `SourceReferrerURL` |
| profile/enquiry/form location/click IDs | approved custom schema names |

LeadSquared accounts can expose different schema names and read-only Source fields. Confirm names in LeadSquared before launch. If a Source attribution field is rejected as read-only, remove only the rejected attribution field, log the event, and retry the core lead capture.

## Source versus UTM

- `lsq_source = Google Ad` labels the CRM Contact Source exactly as requested.
- `utm_source=google&utm_medium=cpc` describes how the visitor arrived.
- `utm_campaign`, `utm_content`, and `utm_term` distinguish campaigns, ads, and keywords.
- `gclid`, `gbraid`, and `wbraid` are Google click identifiers.

Both layers are useful. The server source gives the CRM team a stable label; UTMs and click IDs preserve campaign detail. Recommended Google Ads final URL:

```text
https://FINAL-DOMAIN/PATH/?utm_source=google&utm_medium=cpc&utm_campaign={campaignid}&utm_content={creative}&utm_term={keyword}
```

Google Ads may add `gclid` automatically when auto-tagging is enabled.

## Test sequence

1. Deploy with `dry_run => true`; submit a test and verify `storage/dry-run.jsonl`.
2. Confirm the request and field mapping contain no secrets in the browser.
3. Set `dry_run => false` on the server only.
4. Submit one clearly named live test.
5. Verify the contact and expected fields in LeadSquared.
6. Verify the browser receives `{ "ok": true }` and then fires one `generate_lead`/Ads conversion.
7. Test an intentional API failure; no conversion may fire.

The server allow-lists `course`, `profile`, `enquiryType`, and `formLocation`. Configure `courses` and `allowed_values` in `api/config.php` to exactly match the form options before testing. Keep optional custom schema names blank until the LeadSquared owner confirms them. An empty `allowed_origins` array means same-origin requests only; add exact origins only when the page and endpoint genuinely use different origins.

## Analytics ownership

Choose exactly one strategy with `tagMode` in `site-config.js`. Use `tagMode: 'gtm'` when GTM owns GA4, Google Ads, and Clarity; the page then loads only GTM. Use `tagMode: 'direct'` when the page owns those tags; GTM must not deploy duplicates. For new sites choose one owner for each measurement integration. Existing Gulf/Tally deliberately have an installed GTM container beside directly owned GA4/Clarity; preserve that contract and do not add duplicate measurement tags in GTM.

Recommended events are `lead_form_submit`, `generate_lead`, `lead_capture_error`, and `phone_click`. Do not emit `generate_lead` from a button click, page load, WhatsApp click, or client-only simulated success.

## Security and privacy

- HTTPS only in production.
- Apply a request-size limit, allow-lists, honeypot, and rate limiting.
- Protect `config.php` and `storage/` at the web server.
- Avoid logging plaintext contact details unless operationally necessary; define retention and access.
- Add Origin validation or CSRF protection when the endpoint is exposed to meaningful cross-site abuse.
- Use a restrictive CORS policy; same-origin requires no permissive CORS header.

## GTM placement and dry-run signals

Use the official head bootstrap and body noscript pattern. A GTM ID in config or gtm.js network load proves installation only, not tags/triggers or publication. On both landing and thank-you pages, test one container load, correct ID, no duplicate pageviews and success-only triggers.

In this package starter, a dry-run response sends lead_capture_dry_run, not generate_lead, and never fires the direct Google Ads conversion. GTM conversion triggers must also exclude dry_run=true. A real API failure and a direct thank-you visit fire no conversion.

For config.php field-by-field guidance, read TECH-SETUP/README.md in the extracted package root. Exact regional host, course pick-list values and custom schema names are confirmed by the CRM owner, not inferred from display names.
