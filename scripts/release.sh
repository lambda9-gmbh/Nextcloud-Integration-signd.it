#!/usr/bin/env bash
set -euo pipefail

VERSION=$(grep -oP '<version>\K[^<]+' appinfo/info.xml)
TAG="v${VERSION}"

echo "Version from info.xml: ${VERSION}"
echo "Tag: ${TAG}"

# Check if tag already exists locally or on remote
if git rev-parse "${TAG}" >/dev/null 2>&1; then
    echo "Error: Tag ${TAG} already exists locally."
    exit 1
fi

if git ls-remote --tags origin "${TAG}" | grep -q "${TAG}"; then
    echo "Error: Tag ${TAG} already exists on remote."
    exit 1
fi

# Check for uncommitted changes
if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Error: You have uncommitted changes. Commit or stash them first."
    exit 1
fi

echo ""
read -rp "Create and push tag ${TAG}? [y/N] " confirm
if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
    echo "Aborted."
    exit 0
fi

git tag "${TAG}"
git push origin "${TAG}"

echo ""
echo "Tag ${TAG} pushed. GitHub Action will create the release."
echo "Monitor: https://github.com/lambda9-gmbh/Nextcloud-Integration-signd.it/actions"