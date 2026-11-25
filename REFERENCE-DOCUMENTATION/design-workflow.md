# Design workflow and recommended skills

The required skills are embedded in this workspace under `.claude/skills/`. Use those local copies. If the runtime lacks a tool required by a skill, such as browser automation or image generation, follow that skill's fallback and document the limitation instead of skipping the stage silently.

| Skill | Use it for | Expected output |
|---|---|---|
| `design-consultation` | Translate the brief and references into a design direction, type scale, colors, spacing, layout, motion policy, and responsive behavior. | Approved design system and page direction. |
| `design-shotgun` | Explore several materially different page directions when the team has not chosen one. | Comparison board and selected direction. |
| `design-taste-frontend` | Implement a distinctive production frontend without generic template styling. | Responsive source implementation. |
| `impeccable` | Polish hierarchy, spacing, copy clarity, interaction states, mobile behavior, and visual consistency. | Refined interface. |
| `design-review` | Audit the live page visually and fix inconsistencies after implementation. | Before/after findings and fixes. |
| `web-design-guidelines` | Check accessibility, form usability, semantics, responsive patterns, and interface conventions. | Compliance checklist and issues. |
| `imagegen` | Create original supporting visuals only when licensed assets are unavailable. | Approved, optimized image assets; never fabricated testimonials. |
| `browse` or `qa` | Exercise forms, links, breakpoints, failure states, and live output. | Reproducible QA evidence. |
| `benchmark` | Measure load, layout stability, Core Web Vitals, and asset weight. | Performance baseline and comparison. |
| `investigate` | Diagnose a broken form, blank page, missing CRM lead, caching problem, or deployment issue before changing code. | Root cause and verified fix. |
| `review` | Perform a pre-release code and configuration review. | Release-blocking findings. |
| `cso` | Audit secret handling, endpoint exposure, dependencies, headers, and operational risks. | Security findings and mitigations. |

## Reference handling

Extract design principles—not copied implementation—from reference pages: visual rhythm, section order, density, CTA emphasis, and interaction patterns. Replace their copy, code, logos, people, and claims with approved Finprov material.

## Conversion design baseline

- One primary action per viewport.
- Short first form: name, mobile, optional email, and only the segmentation fields admissions actually needs.
- Mobile-first tap targets of at least 44 CSS pixels.
- Error messages next to the affected field, plus a form-level summary when appropriate.
- Success state only after server confirmation.
- No autoplay audio, forced popups, fake scarcity, or inaccessible carousels.
- Repeat the CTA after high-intent sections without creating competing conversion goals.

## Brand assets

The master skill includes approved local files at `assets/brand/finprov-logo.png` and `assets/brand/favicon.png`. Copy them into every generated release as `assets/finprov-logo.png` and `assets/favicon.png`. Reference those local paths from `index.html`; do not hotlink a Finprov logo or favicon from another website.
