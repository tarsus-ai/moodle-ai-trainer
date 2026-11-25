#!/usr/bin/env bash
# Publish a GitHub release for every version listed in CHANGELOG.md that has
# no tag yet. For each such version the newest commit whose version.php
# declares it ($plugin->release) is tagged v<version> - the newest commit at
# all, also one that did not change version.php, since fixes made after the
# version was set belong to the release - the plugin zip is
# built from the tag with `git archive` (docs/ and .github/ are
# export-ignored, see .gitattributes) - one folder "tarsuscoach", as Moodle's
# plugin installer expects - and the release notes are the version's
# changelog section plus install instructions and the zip's checksum.
#
# Runs in GitHub Actions (see .github/workflows/release.yml); needs `gh`,
# a checkout with the full history and GH_TOKEN with contents: write.
set -euo pipefail

repo_url="https://github.com/${GITHUB_REPOSITORY:-tarsus-ai/moodle-ai-trainer}"

git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"

# Versions from the changelog, oldest first, so the newest is created last
# and becomes the latest release.
versions=$(grep -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+\]' CHANGELOG.md | tr -d '#[] ' | tac)
highest=$(echo "$versions" | tail -n 1)

# Versions to mark as replaced, asked for by hand (workflow_dispatch, e.g.
# "1.0.0 1.0.1"): each release becomes a pre-release - so never "Latest" -
# with a warning above its notes. Nothing is deleted: the tag and zip stay.
withdrawn_note='> **Replaced – please do not install.** This version is called `mod_tarsus`, a name another Moodle plugin also uses, and its live call page is not sealed off from Moodle. The plugin is being reworked as *Tarsus coach* (`mod_tarsuscoach`).'
for v in ${WITHDRAW:-}; do
    tag="v$v"
    if ! gh release view "$tag" >/dev/null 2>&1; then
        echo "no release $tag to withdraw"
        continue
    fi
    body=$(gh release view "$tag" --json body --jq .body)
    case "$body" in
        "$withdrawn_note"*) printf '%s\n' "$body" > "withdrawn-$v.md" ;;
        *) printf '%s\n\n%s\n' "$withdrawn_note" "$body" > "withdrawn-$v.md" ;;
    esac
    gh release edit "$tag" --prerelease --notes-file "withdrawn-$v.md"
    echo "withdrew $tag"
done

for v in $versions; do
    tag="v$v"
    if [ -n "${REBUILD:-}" ] && [ "$REBUILD" = "$tag" ]; then
        # Asked for by hand (workflow_dispatch): the release and its tag go,
        # and the version is built again below.
        echo "rebuilding $tag"
        gh release delete "$tag" --cleanup-tag --yes || true
        git tag -d "$tag" >/dev/null 2>&1 || true
    fi
    if git ls-remote --exit-code --tags origin "refs/tags/$tag" >/dev/null 2>&1; then
        echo "$tag exists, skipping"
        continue
    fi

    # The newest commit on this branch whose version.php declares the release.
    commit=""
    for c in $(git rev-list HEAD); do
        if git show "$c:version.php" 2>/dev/null | grep -Fq "\$plugin->release = '$v';"; then
            commit=$c
            break
        fi
    done
    if [ -z "$commit" ]; then
        echo "no commit declares release $v in version.php, skipping"
        continue
    fi

    echo "tagging $tag at $commit"
    git tag -a "$tag" -m "Tarsus for Moodle $v" "$commit"
    git push origin "refs/tags/$tag"

    zip="Tarsus-moodle-plugin-$v.zip"
    git archive --format=zip --prefix=tarsuscoach/ -o "$zip" "$tag"
    sum=$(sha256sum "$zip" | cut -d ' ' -f 1)

    {
        echo "Tarsus for Moodle $v – activity module (mod_tarsuscoach) for Moodle 4.5 to 5.3."
        echo
        # The version's section of the changelog, without its heading and
        # without the link references at the end of the file.
        awk -v v="$v" '/^## \[/ { p = ($0 ~ ("^## \\[" v "\\]")); next } p' CHANGELOG.md \
            | grep -vE '^\[[0-9.]+\]: ' || true
        echo
        echo "## Install or update"
        echo
        echo "1. Download \`$zip\` below."
        echo "2. In Moodle: *Site administration › Plugins › Install plugins*, drop the zip into the box and click **Install plugin from the ZIP file**, then **Continue** and **Upgrade Moodle database now**."
        echo "3. Without that page (it can be switched off): unzip the file into Moodle's \`mod\` folder (Moodle 5.1 and newer: \`public/mod\`) – it contains the \`tarsuscoach\` folder – and open *Site administration › Notifications*."
        echo
        echo "Step by step with pictures: [README]($repo_url#installation)."
        echo
        echo "## Checksum"
        echo
        echo '```'
        echo "SHA-256  $sum  $zip"
        echo '```'
    } > "notes-$v.md"

    latest=()
    if [ "$v" = "$highest" ]; then
        latest=(--latest)
    fi
    gh release create "$tag" \
        --title "Tarsus for Moodle $v" \
        --notes-file "notes-$v.md" \
        "${latest[@]}" \
        "$zip#$zip (plugin – install it in Moodle as it is)"
    echo "released $tag"
done
