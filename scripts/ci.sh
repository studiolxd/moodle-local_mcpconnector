#!/usr/bin/env bash
#
# Local CI for the Moodle plugin — mirrors the `phpcs` job of
# plugin/.github/workflows/moodle-plugin-ci.yml so you can get the real verdict
# without pushing. Routine workflow:
#
#   scripts/ci-plugin.sh          # report violations, non-zero exit if any
#   scripts/ci-plugin.sh --fix    # run phpcbf over plugin/, then report
#   scripts/ci-plugin.sh --tests  # + PHPUnit, against the Moodle checkout
#
# Why it needs a Moodle checkout at all: several moodle-cs sniffs
# (LangFilesOrdering, MoodleInternal, RequireLogin) look up the enclosing Moodle
# version before doing anything, and stay silent when they can't find one.
# Running phpcs over plugin/ with no Moodle in sight hides 185 real violations
# and reports a clean run — so this script fails loudly instead of scanning
# without one.
#
# Requires: phpcs with the `moodle` standard (composer global require
# moodlehq/moodle-cs) and a Moodle checkout — override its location with
# MOODLE_ROOT=/path/to/moodle. --tests additionally needs that checkout to have
# local/mcpconnector pointing here and its PHPUnit environment initialised.
set -euo pipefail
cd "$(dirname "$0")/.."

FIX=false
TESTS=false
case "${1:-}" in
  --fix) FIX=true ;;
  --tests) TESTS=true ;;
  "") ;;
  *) echo "usage: ${0##*/} [--fix|--tests]" >&2; exit 2 ;;
esac

MOODLE_ROOT="${MOODLE_ROOT:-$HOME/Dev/studiolxd/learn/moodle}"

step() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }
ok()   { printf '\033[1;32m✓ %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m⚠ %s\033[0m\n' "$1"; }

# phpcs is usually a composer global install, which is not on PATH by default.
phpcs=""
for candidate in phpcs "$HOME/.composer/vendor/bin/phpcs" "$HOME/.config/composer/vendor/bin/phpcs"; do
  if command -v "$candidate" >/dev/null 2>&1; then
    phpcs=$(command -v "$candidate")
    break
  fi
done
if [[ -z "$phpcs" ]]; then
  echo "error: phpcs not found — composer global require moodlehq/moodle-cs" >&2
  exit 1
fi
phpcbf="${phpcs%phpcs}phpcbf"

if ! "$phpcs" -i | grep -q '\bmoodle\b'; then
  echo "error: the 'moodle' standard is not installed in $phpcs" >&2
  echo "       composer global require moodlehq/moodle-cs" >&2
  exit 1
fi

if [[ ! -f "$MOODLE_ROOT/config-dist.php" ]]; then
  echo "error: no Moodle checkout at $MOODLE_ROOT (no config-dist.php)" >&2
  echo "       set MOODLE_ROOT=/path/to/moodle" >&2
  exit 1
fi

# moodle-cs validates its moodleRoot option by requiring version.php and
# config-dist.php side by side, but Moodle 5.1+ serves from public/ and only
# version.php moved there. Stitch a root that satisfies both: symlinks to every
# public/ entry plus config-dist.php from the checkout root. Cheap, read-only,
# and it leaves the real checkout untouched — which matters because the dev
# install already symlinks local/mcpconnector back to this repo's plugin/.
webroot="$MOODLE_ROOT"
[[ -f "$MOODLE_ROOT/public/version.php" ]] && webroot="$MOODLE_ROOT/public"

if [[ ! -f "$webroot/version.php" ]]; then
  echo "error: no version.php under $webroot" >&2
  exit 1
fi

moodleroot="$webroot"
if [[ ! -f "$webroot/config-dist.php" ]]; then
  moodleroot=$(mktemp -d)
  trap 'rm -rf "$moodleroot"' EXIT
  for entry in "$webroot"/*; do
    ln -s "$entry" "$moodleroot/$(basename "$entry")"
  done
  ln -s "$MOODLE_ROOT/config-dist.php" "$moodleroot/config-dist.php"
fi

release=$(sed -n "s/.*\$plugin->release[^']*'\([^']*\)'.*/\1/p" plugin/version.php)
moodle=$(sed -n "s/.*\$release *= *'\([^']*\)'.*/\1/p" "$webroot/version.php" | head -1)
printf 'local_mcpconnector %s against Moodle %s\n' "${release:-dev}" "${moodle:-?}"

run_phpcs() { "$phpcs" --standard=moodle --runtime-set moodleRoot "$moodleroot" "$@"; }

if $FIX; then
  step "phpcbf (auto-fixing)"
  # phpcbf exits 1 when it fixed something and 2 when violations remain
  # unfixable — neither is a failure here, the phpcs report below is the gate.
  "$phpcbf" --standard=moodle --runtime-set moodleRoot "$moodleroot" plugin/ || true
fi

step "phpcs (Moodle coding style)"
status=0
run_phpcs --report=full plugin/ || status=$?

if [[ $status -ne 0 ]]; then
  run_phpcs --report=source plugin/ || true
  $FIX || warn "some of these are auto-fixable — rerun with --fix"
  exit 1
fi
ok "phpcs: no violations"

step "php -l (syntax)"
./scripts/lint-plugin.sh

if $TESTS; then
  step "PHPUnit"
  # Moodle 5.2's dependencies cap out at PHP 8.4, so a newer default php would
  # fail before running a single test.
  phpbin="php"
  for versioned in /opt/homebrew/opt/php@8.4/bin/php /usr/local/opt/php@8.4/bin/php; do
    [[ -x "$versioned" ]] && phpbin="$versioned" && break
  done
  (cd "$MOODLE_ROOT" && "$phpbin" vendor/bin/phpunit --testsuite local_mcpconnector_testsuite)
fi
