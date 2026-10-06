#!/usr/bin/env bash
# Sets Subly's version everywhere it is written.
# Usage: tools/bump-version.sh MAJOR.MINOR.PATCH
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

python3 - "$@" <<'PY'
import re, sys

if len(sys.argv) != 2 or not re.fullmatch(r'\d+\.\d+\.\d+', sys.argv[1]):
    sys.exit('usage: tools/bump-version.sh MAJOR.MINOR.PATCH')

new = sys.argv[1]
old = re.search(r'^ \* Version:\s+(\S+)', open('subly.php').read(), re.M).group(1)

if new == old:
    print(f'already {new}')
    sys.exit(0)
if tuple(map(int, new.split('.'))) < tuple(map(int, old.split('.'))):
    sys.exit(f'refusing to move {old} back to {new}')

o = re.escape(old)
required = {
    'subly.php': [
        (rf'^( \* Version:\s+){o}$', rf'\g<1>{new}'),
        (rf"^define\( 'SUBLY_VERSION', '{o}' \);$", f"define( 'SUBLY_VERSION', '{new}' );"),
    ],
    'tools/phpstan-bootstrap.php': [
        (rf"^define\( 'SUBLY_VERSION', '{o}' \);$", f"define( 'SUBLY_VERSION', '{new}' );"),
    ],
    'readme.txt': [
        (rf'^Stable tag: {o}$', f'Stable tag: {new}'),
    ],
}
# Documentation lines that name the free version; "Subly Pro" versions are left alone.
optional = {
    path: [(rf'(?<!Pro )\bSubly {o}\b', f'Subly {new}'), (rf'\*\*Free {o} ·', f'**Free {new} ·')]
    for path in ('docs/USER-GUIDE.md', 'docs/FEATURES.md')
}

for path, rules in {**required, **optional}.items():
    try:
        text = open(path).read()
    except FileNotFoundError:
        continue
    total = 0
    for pattern, repl in rules:
        text, n = re.subn(pattern, repl, text, flags=re.M)
        if path in required and n == 0:
            sys.exit(f'{path}: no "{old}" to replace for {pattern}')
        total += n
    if total:
        open(path, 'w').write(text)
        print(f'{path}: {total} change(s)')

print(f'{old} -> {new}')
if not re.search(rf'^= {re.escape(new)} =$', open('readme.txt').read(), re.M):
    print(f"next: write the '= {new} =' changelog entry at the top of readme.txt's Changelog")
PY
