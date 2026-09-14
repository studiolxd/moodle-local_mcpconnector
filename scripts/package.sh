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

# Sintaxis PHP antes de empaquetar (si hay php en la máquina).
if command -v php >/dev/null 2>&1; then
  find . -name '*.php' -not -path './scripts/*' -print0 | xargs -0 -n1 php -l >/dev/null
fi

staging=$(mktemp -d)
trap 'rm -rf "$staging"' EXIT
mkdir -p "$staging/local_mcpconnector"

# Never ship dev/junk files; the directory prechecker rejects them. .github
# carries the CI workflow, which only means anything in the public repo.
# `*.zip`: los paquetes ya construidos viven en la raíz del repo, así que sin
# esta exclusión cada zip se lleva dentro a los de las versiones anteriores —y
# el de moodle.org, al construirse después, al de esta misma versión (visto el
# 2026-09-14 preparando la 1.3.0: el zip de la 1.2.0 publicado lleva dentro el
# de la 1.1.0).
excludes=(--exclude "TESTING.md" --exclude ".DS_Store" --exclude ".gitignore" --exclude ".github" --exclude "*.zip")
if $moodleorg; then
  # Directory build: English only.
  excludes+=(--exclude "lang/es/")
fi
rsync -a "${excludes[@]}" --exclude "scripts/" --exclude ".git" ./ "$staging/local_mcpconnector/"
(cd "$staging" && zip -qr "$OLDPWD/$out" local_mcpconnector)
echo "✓ $out"
