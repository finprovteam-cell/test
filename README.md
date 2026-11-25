# Finprov landing-page template

Reusable skills and handover for the digital marketing team. **No existing page design or finished website is included.** There is no live deployment workflow in this template.

## Start a new website — no laptop installation

1. Click **Use this template → Create a new repository**. Choose **finprovteam-cell**, a unique campaign name, and **Private**. If you cannot choose that owner, ask the owner for access; do not make it public.
2. Open [Claude Code in the browser](https://claude.ai/code), connect the team's GitHub account and allow access to the **new campaign repository**. Select it, not this master template. Each operator needs their own authorized Claude account/access.
3. Paste the prompt in [START-HERE.md](START-HERE.md) with the existing page URL or input files and desired changes. Claude reads the bundled skills directly from the repo. No global skill installation is needed.
4. Review the mobile and desktop preview. Ask for changes until approved, then ask Claude to finish local/mock QA and prepare the initial release and Tech setup request. An unavailable browser/preview must be reported; use the optional Codespace below if needed.
5. Tech connects hosting, server-only LeadSquared settings and a unique restricted deployment identity **once for that new site**. Only after that connection and launch approval do approved main-branch changes deploy automatically.

**For an existing site:** open that site's existing repository. Do not create a new repo from this template for routine edits and do not replace its server API/configuration. See [the workflow](GITHUB-AND-CPANEL-WORKFLOW.md).

## Optional preview in the browser

GitHub **Code → Codespaces → Create codespace** opens a remote editor with Node 22, Python and PHP configured by `.devcontainer/`. Nothing is installed on the laptop. After Claude generates a website, use its documented preview command; ports 4173, 4174, 5173 and 8000 are forwarded **privately**. Codespaces quota/billing and the AI subscription are separate. The dev container does not install/sign in to Claude, install every optional browser/image tool, or connect cPanel automatically.

## Included

- 15 project-local skills, including Impeccable; [what each does](EMBEDDED-SKILLS.md).
- Finprov logo/favicon, master prompt, design/QA/security references.
- Generic LeadSquared PHP and public tracking reference code with placeholders, not a campaign page. There is intentionally no HTML page or React application to inherit.
- A setup-only `finprov-tech-setup` skill, a structured DM-to-Tech URL handover, and static-only deployment examples. They are references, not enabled production automation.
- Safe template CI: validates the toolkit, tracking mocks and PHP syntax. It does not deploy or prove generated-page quality; Tech adds each new website's real build/smoke/deployment checks.

See [TECH-SETUP/LEAVING-HANDOVER.md](TECH-SETUP/LEAVING-HANDOVER.md) before the current maintainer leaves. [Digital Skills migration](TECH-SETUP/DIGITAL-SKILLS-MIGRATION.md) records the completed Digital Skills connection and remaining tracking/CRM verification.

## Account ownership

This private template and `Swipe-Pages-DM` are now owned by **finprovteam-cell**; ownership was verified on 23 November 2026. Transferring a repo does not copy credentials, publish GTM tags, or connect a new server destination. Update access and links after acceptance.

## Official references

- [GitHub template repositories](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-template-repository)
- [Claude Code in the cloud](https://code.claude.com/docs/en/claude-code-on-the-web)
- [GitHub Codespaces](https://docs.github.com/en/codespaces/developing-in-a-codespace/creating-a-codespace-for-a-repository)
- [Codespaces billing](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces)

## Tech setup is deliberately bounded

DM's Claude asks for the final website link and commits setup-request.json plus TECH-SETUP-REQUEST.md. Tech's trusted server skill reads that exact repository, validates the request and prepares a read-only destination plan. Tech must verify/approve the actual destination and new access before setup; the skill stops after that site. It cannot safely remove the need for authorized access, secure credentials or launch approval. See [cPanel Claude setup](TECH-SETUP/CPANEL-CLAUDE-SETUP.md). Updating this template does not install Claude/skills on cPanel or retrofit existing campaign repositories.
