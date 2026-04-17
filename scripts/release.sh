#!/usr/bin/env bash
# =============================================================================
# release.sh — Lescopr PHP SDK release script
#
# Usage:
#   ./scripts/release.sh 0.2.0          # stable release
#   ./scripts/release.sh 0.2.0-beta.1   # pre-release
#
# What it does:
#   1. Validates version format (semver)
#   2. Updates CHANGELOG.md [Unreleased] → [X.Y.Z]
#   3. Commits, tags and pushes → GitHub Actions takes over for Packagist
# =============================================================================

set -euo pipefail

VERSION="${1:-}"

# ── Validate ──────────────────────────────────────────────────────────────────
if [[ -z "$VERSION" ]]; then
  echo "❌  Usage: $0 <version>   e.g. $0 0.2.0"
  exit 1
fi

if ! echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$'; then
  echo "❌  Invalid version format '$VERSION'. Expected semver: MAJOR.MINOR.PATCH[-pre]"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SDK_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$SDK_DIR"

# ── Check git is clean (scope : ce répertoire SDK uniquement) ───────────────
if [[ -n "$(git status --porcelain .)" ]]; then
  echo "❌  Working tree is not clean. Commit or stash your changes first."
  git status --short .
  exit 1
fi

# ── Run tests ─────────────────────────────────────────────────────────────────
echo "🧪  Running tests..."
LESCOPR_DAEMON_MODE=true composer test:ci
echo "✅  All tests pass."

# ── Update CHANGELOG ──────────────────────────────────────────────────────────
DATE=$(date +%Y-%m-%d)
YEAR=$(date +%Y)
echo "📝  Updating CHANGELOG.md: [Unreleased] → [$VERSION] — $DATE"

# Replace the first occurrence of "## [Unreleased]" with the versioned header
# and insert a fresh [Unreleased] placeholder above it
sed -i '' \
  "s/^## \[Unreleased\]/## [Unreleased]\n\n---\n\n## [$VERSION] — $DATE/" \
  CHANGELOG.md

# Update the comparison links at the bottom
# Replace existing [Unreleased] link
sed -i '' \
  "s|^\[Unreleased\]: .*|\[Unreleased\]: https://github.com/Lescopr/lescopr-php/compare/v$VERSION...HEAD\n[$VERSION]: https://github.com/Lescopr/lescopr-php/releases/tag/v$VERSION|" \
  CHANGELOG.md

# ── Update LICENSE year ───────────────────────────────────────────────────────
echo "📄  Updating LICENSE year to 2024-$YEAR..."
sed -i '' \
  "s/Copyright (c) 2024-[0-9]*/Copyright (c) 2024-$YEAR/" \
  LICENSE

# ── Commit & tag ──────────────────────────────────────────────────────────────
echo "🔖  Committing and tagging v$VERSION..."
git add CHANGELOG.md LICENSE
git commit -m "chore: release v$VERSION"
git tag -a "v$VERSION" -m "Release v$VERSION"

# ── Push ─────────────────────────────────────────────────────────────────────
echo "🚀  Pushing to origin..."
git push origin main
git push origin "v$VERSION"

echo ""
echo "✅  Released v$VERSION"
echo "   → GitHub Actions will create the GitHub Release and notify Packagist automatically."
echo "   → Watch: https://github.com/Lescopr/lescopr-php/actions"

