# Embedded landing-page skill bundle

Portable project skills for the Finprov redesign workflow are stored locally under `.claude/skills/`. Opening the package root in Claude Code makes them available without a separate installation.

| Skill | Required use |
|---|---|
| `finprov-swipe-page` | Master workflow from supplied URL/files through LeadSquared integration, tracking, QA, and cPanel ZIP. |
| `design-consultation` | Establish the conversion hierarchy and design direction. |
| `design-shotgun` | Compare materially different concepts only when direction is undecided. |
| `design-taste-frontend` | Implement a distinctive responsive frontend without generic AI-template styling. |
| `impeccable` | Production design craft, responsive adaptation, audit, and final polish. |
| `design-review` | Screenshot-based desktop/mobile visual review and correction. |
| `web-design-guidelines` | Accessibility, semantics, form usability, and interface conventions. |
| `imagegen` | Permitted supporting visuals or a precise asset brief; never fabricated evidence. |
| `browse` | Real-browser interaction and runtime verification when a browser tool exists. |
| `qa` | Functional, responsive, accessibility, LeadSquared dry-run, and tag test matrix. |
| `benchmark` | Page weight, layout stability, and performance checks. |
| `investigate` | Root-cause diagnosis for missing leads, blank pages, cache, tags, and deployment defects. |
| `review` | Release-blocking source/configuration review. |
| `cso` | Secret, endpoint, privacy, and release-security review. |

## Execution order

1. `/finprov-swipe-page`
2. `/design-consultation`
3. `/design-shotgun` only when required
4. `/design-taste-frontend`
5. `/impeccable polish`
6. `/design-review`
7. `/web-design-guidelines`
8. `/browse`, `/qa`, and `/benchmark`
9. `/review` and `/cso`
10. Existing connected page: prepare source and an authorized PR. New/unconnected page: validate and generate `OUTPUT/release.zip` only after design approval.

If Impeccable requests product context, Claude creates `PRODUCT.md` from the project brief, supplied page, and approved inputs and continues autonomously.

Use `/investigate` whenever a failure appears. Use `/imagegen` only when approved supporting imagery is missing.

## Tool-dependent behavior

The instructions are embedded, but some actions still depend on capabilities available in the team's Claude Code environment. Browser automation, image generation, Lighthouse, PHP, and network access must never be claimed as completed when unavailable. Each embedded skill specifies a safe fallback and requires the limitation to be recorded in `QA-REPORT.md`.

## Brand assets

The package includes:

```text
BRAND-ASSETS/finprov-logo.png
BRAND-ASSETS/favicon.png
```

The master release template contains matching copies. Every generated release must ship them as:

```text
assets/finprov-logo.png
assets/favicon.png
```

The release validator rejects a package that omits them or leaves the HTML pointing to an external Finprov WordPress image.

## Bundle provenance and scope

The 15 skills below are copied from the prior complete Finprov handover. Several are portable workflow adaptations, not an installed full gstack browser daemon or a promise of external service access. Impeccable and the design references remain included. Do not require the operator to install unrelated global tools; use available tools or document the missing capability. Each skill's description explains its purpose. This package does not include credentials, an authenticated browser session or paid media-generation access.

See RUNTIME-REQUIREMENTS.md for required/optional tools and the distinction between instructions and installed runtimes.

Template edition: no HTML page is included. Create campaign HTML/source from the brief. The integration reference contains config.example.php only; Tech creates protected config.php server-side. For an initial distributable ZIP, generate a placeholder-only config.php with dry_run=true. Do not run final release validation on the incomplete reference directory.

## finprov-tech-setup (Tech only)

Initial setup of one approved new campaign: reads the committed URL handover, independently verifies hosting, prepares a read-only plan, then performs only authorized backend/deployment setup. Excludes redesign, routine updates, other-site/server administration and automatic credential/contact changes. Install a reviewed trusted copy on cPanel separately; see TECH-SETUP/CPANEL-CLAUDE-SETUP.md.
