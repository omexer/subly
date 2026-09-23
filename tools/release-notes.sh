#!/usr/bin/env bash
# Prints a release's notes: its readme changelog entry, or else the commits since the previous tag.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

version="${1:?usage: tools/release-notes.sh MAJOR.MINOR.PATCH}"

if [ -f readme.txt ] && grep -q "^= ${version//./\\.} =$" readme.txt; then
	awk -v head="= $version =" '
		$0 == head { on = 1; next }
		on && (/^= [0-9]+\.[0-9]+\.[0-9]+ =$/ || /^== /) { exit }
		on { print }
	' readme.txt | sed '/./,$!d'
	exit 0
fi

# A plugin that is not on wordpress.org keeps its changelog here instead.
if [ -f CHANGELOG.md ] && grep -q "^## ${version//./\\.}$" CHANGELOG.md; then
	awk -v head="## $version" '
		$0 == head { on = 1; next }
		on && /^## / { exit }
		on { print }
	' CHANGELOG.md | sed '/./,$!d'
	exit 0
fi

ref="HEAD"
git rev-parse -q --verify "refs/tags/v$version" >/dev/null && ref="v$version"
previous="$(git describe --tags --abbrev=0 "$ref^" 2>/dev/null || true)"

if [ -n "$previous" ]; then
	echo "Changes since $previous:"
	echo
	git log --no-merges --pretty='- %s' "$previous..$ref"
else
	echo "The first tagged release. Most recent changes:"
	echo
	git log --no-merges -n 30 --pretty='- %s' "$ref"
fi
