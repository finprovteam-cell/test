# Requirements and inputs

Create a brief before implementation. Record owner and approval status for each item.

## Required business inputs

- Campaign/page name, course name, URL slug, intended domain and path.
- Audience, primary promise, CTA, course mode/location, duration, and approved curriculum.
- Approved copy, placement figures, reviews, logos, accreditations, photographs, and legal disclaimers.
- Phone and WhatsApp numbers, privacy-policy URL, terms URL, and consent wording.
- Reference pages or screenshots, with a note identifying what may be reused and what is inspiration only.

## Required LeadSquared inputs

- Correct LeadSquared regional API host.
- Server-side access key and secret key, provided through an approved secret channel.
- Exact Contact Source value (`lsq_source`), for example `Google Ad`.
- Exact course pick-list value and schema name, normally `mx_Courses`.
- Required custom-field schema names and allowed values.
- Whether phone submissions update an existing contact (`SearchBy=Phone`).
- Optional activity code and owner/routing automation expectations.
- A test-contact convention and person responsible for CRM verification.

## Optional public analytics inputs

- GTM container ID.
- GA4 measurement ID.
- Google Ads ID and conversion label.
- Microsoft Clarity project ID.
- Approved campaign UTM naming convention.

These may be added after the page is built. Blank IDs must never break the site or form.

## Hosting inputs

- Platform: cPanel/Apache/PHP, another PHP host, or static host plus a separate API.
- PHP version and extensions (`curl`; `mbstring` when used).
- Document root and final public URL.
- Base path if deployed below a directory such as `/courses/`.
- DNS/SSL owner, cache/CDN owner, upload owner, and rollback owner.

## Stop conditions

Do not publish when the URL is still localhost, the final route is unknown, the form posts to a fake local handler, claims are unapproved, or live secrets are embedded in deliverables.

