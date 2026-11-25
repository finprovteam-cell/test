#!/usr/bin/env python3
"""Validate bundled resources. This is NOT generated-website or production QA."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
errors=[]
skills=list((root/'.claude/skills').glob('*/SKILL.md'))
if len(skills)!=15: errors.append(f'Expected 15 skills; found {len(skills)}')
for skill in skills:
    text=skill.read_text()
    if not text.startswith('---\n') or not re.search(r'^name: .+',text,re.M) or not re.search(r'^description: .+',text,re.M):
        errors.append(f'Invalid skill metadata: {skill.relative_to(root)}')
for asset in ['finprov-logo.png','favicon.png']:
    brand=root/'BRAND-ASSETS'/asset
    if not brand.is_file(): errors.append(f'Missing brand asset: {asset}')
    else:
        for other in [root/'.claude/skills/finprov-swipe-page/assets/brand'/asset,root/'.claude/skills/finprov-swipe-page/assets/release-template/assets'/asset]:
            if not other.is_file() or other.read_bytes()!=brand.read_bytes(): errors.append(f'Brand asset differs: {other.relative_to(root)}')
example=root/'.claude/skills/finprov-swipe-page/assets/release-template/api/config.example.php'
text=example.read_text()
for key in ['lsq_access_key','lsq_secret_key']:
    value=re.search(r"'"+key+r"'\s*=>\s*'([^']*)'",text)
    if not value or value.group(1) not in ['', 'REPLACE_ON_SERVER']: errors.append(f'Unexpected {key} in reference config')
if not re.search(r"'dry_run'\s*=>\s*true",text): errors.append('Initial reference must stay dry_run=true')
if errors: raise SystemExit('\n'.join(errors))
print('PASS: 15 skill entrypoints, matching brand assets, placeholder-only integration reference.')
print('This does not test a generated page, hosted CRM, tags or deployment.')
