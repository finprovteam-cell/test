# New campaign: give Claude this prompt

First use this repository as a template to create a **new private repo under finprovteam-cell**. Connect/select that new repository in Claude Code on the web. Do not build campaign pages in the master template.

```text
Read CLAUDE.md, MASTER-HANDOVER-FOR-CLAUDE.md and the bundled finprov-swipe-page skill.
Build/redesign this page: [URL, or files I supply].
Approved content and requested changes: [brief].
Ask me for the intended final HTTPS website link if I have not supplied it.
Use the included design skills, Impeccable, Finprov logo and favicon.
This repository starts with no page code: generate original responsive page source;
do not copy another campaign or its IDs, claims, API config or deploy keys.
Show me a mobile and desktop preview and let me approve the design before final packaging.
Use mocked CRM responses for development; do not submit a real lead or claim CRM delivery.
After approval, finish appropriate QA, prepare source/PR, the credential-free initial
release ZIP, setup-request.json and matching TECH-SETUP-REQUEST.md.
Record explicit design approval and missing inputs. Preserve the skills for future edits.
Do not deploy or change any live server. Tech will connect hosting, LeadSquared and
restricted deployment once. Report exactly which launch checks remain for Tech/DM.
```

The repo already contains project-local skills; they do not need installing globally. If slash commands are unavailable, Claude must read `.claude/skills/finprov-swipe-page/SKILL.md` directly.

For an **existing website**, select its existing campaign repo instead, read its own instructions and workflow, and request a branch/PR. Preserve APIs, server secrets, tracking ownership and deployment paths. Do not copy this template's initial config over live files.

Browser/cloud access is the recommended no-local-install route; an AI subscription, authorized GitHub connection, and any cloud quota are still required. See README.md.
