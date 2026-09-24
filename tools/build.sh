#!/usr/bin/env bash
# Builds dist/<slug>-<version>.zip from the committed tree, with a SHA-256 checksum beside it.
# Usage: tools/build.sh [--allow-dirty] [--out DIR]
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

allow_dirty=0
out="$root/dist"
while [ $# -gt 0 ]; do
	case "$1" in
		--allow-dirty) allow_dirty=1 ;;
		--out) out="${2:?--out needs a directory}"; shift ;;
		*) echo "build: unknown option $1" >&2; exit 2 ;;
	esac
	shift
done

fail() { echo "build: $*" >&2; exit 1; }

main="$(grep -l '^ \* Plugin Name:' ./*.php 2>/dev/null | head -1 || true)"
[ -n "$main" ] || fail "no main plugin file with a Plugin Name header in $root"
main="$(basename "$main")"
slug="${main%.php}"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$main" | head -1 | tr -d '[:space:]')"
[ -n "$version" ] || fail "no Version header in $main"

# The zip comes from HEAD, so uncommitted edits would silently be missing from it.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
	[ "$allow_dirty" = 1 ] || fail "uncommitted changes to tracked files; commit them, or pass --allow-dirty to build HEAD anyway"
	echo "build: warning - uncommitted changes are NOT in this zip, which is built from HEAD" >&2
fi

bash tools/check-version.sh >/dev/null

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
git archive --format=tar --prefix="$slug/" HEAD | tar -x -C "$stage"
pkg="$stage/$slug"

[ -f .distignore ] || fail ".distignore is missing"
while IFS= read -r line || [ -n "$line" ]; do
	line="${line%%#*}"
	line="$(printf '%s' "$line" | tr -d '[:space:]')"
	[ -n "$line" ] || continue
	case "$line" in *..*) fail ".distignore entry '$line' leaves the plugin" ;; esac
	rm -rf "${pkg:?}/${line#/}"
done < .distignore

# Tests live beside the source they cover, so they are removed by shape, not by path.
find "$pkg" -type d \( -name test -o -name tests -o -name __tests__ \) -prune -exec rm -rf {} +
find "$pkg" -type f \( -name '*.test.js' -o -name '*.spec.js' -o -name '*.test.jsx' -o -name '*.spec.jsx' \) -delete

# An allow list as well as a deny list: a new top-level file stops the build instead of shipping by accident.
allowed=" $main includes assets build src templates languages readme.txt CHANGELOG.md index.php uninstall.php LICENSE license.txt package.json webpack.config.js tailwind.config.js postcss.config.js "
for entry in "$pkg"/* "$pkg"/.[!.]*; do
	[ -e "$entry" ] || continue
	name="$(basename "$entry")"
	case "$allowed" in
		*" $name "*) ;;
		*) fail "unexpected '$name' at the top of the zip - exclude it in .distignore, or add it to the allow list in tools/build.sh to ship it" ;;
	esac
done

# src/ ships on purpose: wordpress.org wants the readable source of the bundles in build/.
forbidden="$(find "$pkg" \( -name node_modules -o -name .git -o -name .github -o -name docs -o -name .DS_Store -o -name '*.map' -o -name '.env*' -o -name '.wp-env*' -o -name test -o -name tests -o -name __tests__ -o -name '*.test.js' -o -name '*.spec.js' -o -name 'phpunit*' \) -print | sed -n '1,5p')"
[ -z "$forbidden" ] || fail "forbidden paths inside the zip: ${forbidden//$stage\//}"

for required in "$main" includes/autoload.php build; do
	[ -e "$pkg/$required" ] || fail "the zip is missing $required"
done
[ -n "$(ls -A "$pkg/build")" ] || fail "build/ is empty in the zip"

if command -v php >/dev/null 2>&1; then
	problems="$(find "$pkg" -name '*.php' -print0 | xargs -0 -n1 php -d error_reporting=E_ALL -d display_errors=stderr -l 2>&1 | grep -v '^No syntax errors detected' || true)"
	[ -z "$problems" ] || { printf '%s\n' "$problems" >&2; fail "PHP lint failed inside the zip"; }
else
	echo "build: warning - php not found, the zip was not linted" >&2
fi

mkdir -p "$out"
out="$(cd "$out" && pwd)"
zip_name="$slug-$version.zip"
rm -f "$out/$zip_name" "$out/$zip_name.sha256"
( cd "$stage" && zip -rqX "$out/$zip_name" "$slug" )

if command -v sha256sum >/dev/null 2>&1; then
	( cd "$out" && sha256sum "$zip_name" > "$zip_name.sha256" )
else
	( cd "$out" && shasum -a 256 "$zip_name" > "$zip_name.sha256" )
fi

files="$(find "$pkg" -type f | wc -l | tr -d ' ')"
echo "built $out/$zip_name ($(du -h "$out/$zip_name" | cut -f1 | tr -d '[:space:]'), $files files)"
echo "top level: $(ls -A "$pkg" | tr '\n' ' ')"

if [ -n "${GITHUB_OUTPUT:-}" ]; then
	{ echo "zip=$out/$zip_name"; echo "version=$version"; echo "slug=$slug"; } >> "$GITHUB_OUTPUT"
fi
