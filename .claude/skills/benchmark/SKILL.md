---
name: benchmark
description: Check landing-page performance, Core Web Vitals risks, asset weight, and layout stability before release.
---

# Performance benchmark

Measure the production build when tooling is available; otherwise perform a static asset audit and disclose the limitation. Check total transfer size, largest images, render-blocking resources, font loading, lazy loading below the fold, image dimensions, caching, compression, and layout-shift risks. Target a fast mobile ad landing page: avoid unnecessary frameworks, ship optimized local assets, and keep the hero stable. Put measurements, budgets, and regressions in `QA-REPORT.md`. Do not hide a failing metric by deleting useful content.
