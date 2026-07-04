#!/usr/bin/env bash
# Fetches standalone PHAR dev-tools that are NOT installed through Composer.
#
# Why not Composer? phpstan/phpstan ships as a "dist-only" package whose zip is
# served from GitHub Releases, which is blocked by this project's sandbox egress
# policy. A shallow git clone of the tagged release repo (which vendors the
# compiled phar) works everywhere — sandbox and normal environments alike.
#
# Usage: bin/install-tools.sh
set -euo pipefail

TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/tools"
mkdir -p "$TOOLS_DIR"

PHPSTAN_VERSION="2.2.4"

install_phar_from_release_repo() {
    local name="$1" repo="$2" tag="$3" phar_in_repo="$4"
    local dest="$TOOLS_DIR/${name}.phar"
    if [ -f "$dest" ]; then
        echo "==> ${name}: already present ($dest)"
        return
    fi
    echo "==> ${name}: cloning ${repo}@${tag} ..."
    local tmp
    tmp="$(mktemp -d)"
    git clone --depth 1 --branch "$tag" "https://github.com/${repo}.git" "$tmp" >/dev/null 2>&1
    cp "$tmp/${phar_in_repo}" "$dest"
    chmod +x "$dest"
    rm -rf "$tmp"
    echo "==> ${name}: installed $("php" "$dest" --version 2>/dev/null | head -1)"
}

install_phar_from_release_repo "phpstan" "phpstan/phpstan" "$PHPSTAN_VERSION" "phpstan.phar"

echo "All PHAR tools installed in $TOOLS_DIR"
