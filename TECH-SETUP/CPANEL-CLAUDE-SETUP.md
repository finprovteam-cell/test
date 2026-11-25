# One-time trusted Tech skill installation

This template includes a separate finprov-tech-setup skill. No server installation is performed by updating this repository.

The authorized Tech maintainer reviews the skill and copies only `.claude/skills/finprov-tech-setup/` from this trusted template to `/home/accountingcourse/.claude/skills/finprov-tech-setup/`. Keep the server copy controlled by Tech; do not let campaign repos overwrite it or import their hooks/settings into the server profile. Record the reviewed template commit. Updates require review again; do not auto-pull skill instructions from arbitrary campaign repositories.

Then start Claude from a dedicated workspace outside public_html and use:

```text
Use /finprov-tech-setup to inspect this repository: [HTTPS GitHub link].
Read its setup-request.json and TECH-SETUP-REQUEST.md.
Prepare a read-only plan first. Verify the requested URL against cPanel's actual
Domains mapping and the Tech-owned policy. Show me the exact target and proposed
changes before touching production. Do not redesign or change anything outside this site.
```

Claude checks that the requested link and handover are complete. Tech reviews the independently observed mapping and the exact plan. If a policy is missing, Claude proposes its values without writing them; after Tech approval, Claude may create the protected policy outside the checkout/public_html on Tech's behalf and validate it. Tech need not type JSON or each setup command. The read-only validator reports existing collisions and a request hash; it never installs, deploys or authorizes a key. It cannot prove the policy is correct or create an OS sandbox.

Tech still supplies authorized GitHub/cPanel/CRM access, approves new server access and publication, securely handles missing credentials, and verifies any real CRM test. No need to manually type every setup command when those prerequisites are satisfied. Do not promise zero oversight or automatically grant all permissions. Claude runs with the OS user's access; its skill/workspace alone does not isolate live credentials.

If a prerequisite needs DNS, system/security changes or an existing-site migration, stop and assign a separate task rather than extending the initial setup scope.

Server helper requirement: PHP CLI 8+ with POSIX ownership checks. Run the trusted script with `php`; no Python or system package installation is needed for this helper. If the required runtime is unavailable, stop and report the prerequisite rather than altering the server.

Read-only validation command after cloning into the trusted workspace:

```bash
php /home/accountingcourse/.claude/skills/finprov-tech-setup/scripts/validate_setup_request.php /path/to/campaign/setup-request.json
```

After Tech independently creates the protected server policy, add `--policy /path/to/private/server-policy.json`. Replace paths with the actual reviewed locations. These commands only print a plan; they never execute setup. Keep Claude's server working directory trusted and do not automatically accept campaign hooks/settings. A skill is guidance, not OS access isolation.
