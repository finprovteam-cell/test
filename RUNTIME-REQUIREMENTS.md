# No local installation route

Use Claude Code on the web and connect the generated campaign repository. Project skills are read from `.claude/skills/` without global installation. The team's authorized AI access, GitHub connection and cloud environment/network permissions are still required. Browser availability varies: do not claim responsive/browser/CRM tests that could not be run.

Optional Codespaces provides a remote Node 22/Python/PHP editor with private forwarded preview ports. The Dockerfile configures these tools automatically on the cloud machine, not the laptop. Codespaces use may consume quota or incur charges; do not create a paid instance without the account owner's authorization. This delivery configures it but does not start one. It does not sign into Claude or provide AI/image service access.

# Runtime requirements for Claude Code

The handover includes instructions, references, assets and source templates. It does not include authenticated sessions, secret keys, cloud accounts or a browser daemon.

- Claude Code: open the entire extracted folder so CLAUDE.md and .claude/skills are discoverable. If slash-skill discovery is unavailable, tell Claude to read the master SKILL.md directly and follow its linked references.
- Node.js: use the website repository's version/lockfile. Current connected CI uses Node 22. Node 22+ runs the included JS verification scripts. Do not replace a supplied project's dependencies or build system unnecessarily.
- Python: Python 3.10+ for the initial release validator and package integrity helpers (standard library only).
- PHP: 7.4+ and cURL for the starter LeadSquared endpoint; PHP CLI 8+ for the included restricted deployment receiver. PHP syntax/local tests do not prove live CRM success.
- Git and GitHub: a signed-in account with the required access to finprovteam-cell repositories. Creating a private repo and pushing source do not establish hosting/deployment access.
- Browser QA: use the authorized browser tool already available in Claude, or a project-installed Playwright/Puppeteer tool. Isolate/stub outbound CRM and analytics for local automated tests. Record unavailable real-browser checks instead of claiming them.
- Impeccable: its optional advanced helpers may need puppeteer/playwright, htmlparser2, css-select, css-tree, domutils or @babel/parser. Inspect the specific helper before using it and install only the required dependency in an isolated tool/project workspace; do not bulk-install packages merely because bundled instructions mention them.
- Image generation and Lighthouse: external tools/access are optional. Use approved existing assets or prepare an asset brief if generation is unavailable. Measure performance with available tooling and disclose missing measurements.

No exact tool/model subscription is bundled or required by the instructions themselves. The receiving team supplies the tools it is authorized to use. Use mock tests without production credentials and leave real launch verification to tech/DM as assigned.

The initial release validator checks structure, placeholder/dry-run config, path hygiene and some common secret patterns. It is not a comprehensive secrets detector; inspect the entire source/history/artifact with the team's secret scanner before publishing. Never apply the initial API/config requirements to an existing static-only deployment.
