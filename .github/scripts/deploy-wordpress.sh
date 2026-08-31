#!/usr/bin/env bash
#
# Deploy a tagged release to the WordPress.org plugin SVN repository.
# Run by the release workflow on a version tag. Publishes the built plugin to
# trunk, syncs the marketing assets from .wordpress-org/ when present, then tags.
#
# Skips gracefully (exit 0) when WordPress.org credentials are absent, so a
# GitHub-only release still succeeds.
#
set -euo pipefail

if [[ -z "${GITHUB_WORKFLOW:-}" ]]; then
	echo "This script is only meant to run in GitHub Actions." >&2
	exit 1
fi

# Tags in this repo are prefixed with "v" (e.g. v3.19.18); WordPress.org tags
# and the plugin version are the bare number.
SCRIPT_TAG=${GITHUB_REF##*/}
VERSION="${SCRIPT_TAG#v}"

if [[ "$SCRIPT_TAG" == *"beta"* ]]; then
	echo "Tag $SCRIPT_TAG is a beta; skipping WordPress.org deploy."
	exit 0
fi

if [[ -z "${WORDPRESS_USERNAME:-}" || -z "${WORDPRESS_PASSWORD:-}" ]]; then
	echo "WordPress.org credentials are not set; skipping SVN deploy."
	echo "Set the WORDPRESS_USERNAME and WORDPRESS_PASSWORD repository secrets to enable it."
	exit 0
fi

PLUGIN=${PLUGIN:-12-step-meeting-list}
MAINFILE=${MAINFILE:-12-step-meeting-list.php}
BUILD_DIR=${BUILD_DIR:-build}
ZIP_FILE="$BUILD_DIR/$PLUGIN.$VERSION.zip"

if ! command -v svn >/dev/null 2>&1; then
	echo "Installing subversion..."
	sudo apt-get update -y
	sudo apt-get install -y subversion
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# ── Version consistency ──────────────────────────────────────────────────────
# The plugin header Version, the TSML_VERSION constant (source of truth for the
# Makefile), the readme Stable tag, and the pushed tag must all agree.
PLUGINVERSION=$(grep -i "Version:" "$MAINFILE" | head -1 | awk -F' ' '{print $NF}' | tr -d '\r')
CONSTVERSION=$(sed -nE "s/^define\('TSML_VERSION', '([^']+)'\).*/\1/p" "$MAINFILE" | tr -d '\r')
READMEVERSION=$(grep -i "Stable tag:" readme.txt | awk -F' ' '{print $NF}' | tr -d '\r')
echo "$MAINFILE Version: $PLUGINVERSION | TSML_VERSION: $CONSTVERSION | readme.txt: $READMEVERSION | tag: $VERSION"
if [[ "$PLUGINVERSION" != "$VERSION" || "$CONSTVERSION" != "$VERSION" || "$READMEVERSION" != "$VERSION" ]]; then
	echo "Version mismatch: header ($PLUGINVERSION), TSML_VERSION ($CONSTVERSION), readme.txt ($READMEVERSION), tag ($VERSION). Aborting." >&2
	exit 1
fi

# ── Don't redeploy an existing tag ───────────────────────────────────────────
if svn ls "https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION" >/dev/null 2>&1; then
	echo "Tag $VERSION already exists on WordPress.org. Aborting." >&2
	exit 1
fi

# ── Assemble a clean plugin tree ─────────────────────────────────────────────
# The zip was produced by `make build` (git archive, honouring export-ignore),
# so it already excludes dev-only files. It unpacks to $WORK/$PLUGIN/.
if [[ ! -f "$ZIP_FILE" ]]; then
	echo "Build artifact $ZIP_FILE not found. Run 'make build' first." >&2
	exit 1
fi
WORK=$(mktemp -d)
unzip -q -o "$ZIP_FILE" -d "$WORK"

# ── Check out the WordPress.org working copies ───────────────────────────────
svn co "https://plugins.svn.wordpress.org/$PLUGIN/trunk" svn/trunk

# Marketing assets (icon, banner, screenshots) live in .wordpress-org/ if used.
HAVE_ASSETS=0
if [[ -d .wordpress-org ]] && svn co "https://plugins.svn.wordpress.org/$PLUGIN/assets" svn/assets 2>/dev/null; then
	HAVE_ASSETS=1
fi

# ── Sync plugin files into trunk (mirror; prune removed files) ────────────────
rsync -rc --delete --exclude='.svn' "$WORK/$PLUGIN/" svn/trunk/

# ── Sync marketing assets ────────────────────────────────────────────────────
if [[ "$HAVE_ASSETS" == "1" ]]; then
	rsync -rc --delete --exclude='.svn' .wordpress-org/ svn/assets/
fi

# ── Stage adds and deletes ───────────────────────────────────────────────────
COMMIT_TARGETS=(svn/trunk)
[[ "$HAVE_ASSETS" == "1" ]] && COMMIT_TARGETS+=(svn/assets)
for d in "${COMMIT_TARGETS[@]}"; do
	# grep exits 1 when there is nothing to add or nothing to delete, which is the
	# normal case — without swallowing it, `set -o pipefail` fails the deploy.
	svn stat "$d" | { grep '^?' || true; } | awk '{print $2}' | xargs -r -I x svn add x@
	svn stat "$d" | { grep '^!' || true; } | awk '{print $2}' | xargs -r -I x svn rm --force x@
done

svn stat "${COMMIT_TARGETS[@]}"

# ── Commit trunk (+ assets) and tag ──────────────────────────────────────────
echo "Committing to trunk..."
svn ci --no-auth-cache --username "$WORDPRESS_USERNAME" --password "$WORDPRESS_PASSWORD" \
	"${COMMIT_TARGETS[@]}" -m "Deploy version $VERSION"

echo "Waiting for the SVN server to synchronise..."
sleep 3

echo "Tagging version $VERSION from trunk..."
svn cp "https://plugins.svn.wordpress.org/$PLUGIN/trunk" \
	"https://plugins.svn.wordpress.org/$PLUGIN/tags/$VERSION" \
	--no-auth-cache --username "$WORDPRESS_USERNAME" --password "$WORDPRESS_PASSWORD" \
	-m "Tagging version $VERSION"

rm -rf svn "$WORK"
echo "Deployed $PLUGIN $VERSION to WordPress.org."
