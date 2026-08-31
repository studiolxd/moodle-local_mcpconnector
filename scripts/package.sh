#!/usr/bin/env bash
# Builds the installable Moodle plugin zip: local_mcpconnector-<release>.zip
# with the plugin under a local_mcpconnector/ root folder (what Moodle's
# "Install plugins" upload expects).
#
#   scripts/package-plugin.sh              # full zip (en + es, for your own use)
#   scripts/package-plugin.sh --moodleorg  # directory zip: lang/en only, no junk
#
# The moodle.org plugins directory accepts ONLY lang/en (other languages go
# through AMOS after approval) and rejects hidden/junk files.
set -euo pipefail
cd "$(dirname "$0")/.."

moodleorg=false
[[ "${1:-}" == "--moodleorg" ]] && moodleorg=true

# Parse the release string directly — executing version.php outside Moodle
# fails on core constants like MATURITY_STABLE.
release=$(sed -n "s/.*\$plugin->release[^']*'\([^']*\)'.*/\1/p" version.php)
release=${release:-dev}
suffix=""
$moodleorg && suffix="-moodleorg"
out="local_mcpconnector-${release}${suffix}.zip"

./scripts/lint-plugin.sh

staging=$(mktemp -d)
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/local_mcpconnector"

# Never ship dev/junk files; the directory prechecker rejects them. .github
# carries the CI workflow, which only means anything in the public repo.
excludes=(--exclude "TESTING.md" --exclude ".DS_Store" --exclude ".gitignore" --exclude ".github")
if $moodleorg; then
  # Directory build: English only.
  excludes+=(--exclude "lang/es/")
fi
rsync -a "${excludes[@]}" --exclude "scripts/" --exclude ".git" ./ "$staging/local_mcpconnector/"
(cd "$staging" && zip -qr "$OLDPWD/$out" local_mcpconnector)
echo "✓ $out"
