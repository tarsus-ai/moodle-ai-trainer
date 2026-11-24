#!/usr/bin/env bash
# Publish a GitHub release for every version listed in CHANGELOG.md that has
# no tag yet. For each such version the newest commit whose version.php
# declares it ($plugin->release) is tagged v<version>, the plugin zip is
# built from the tag with `git archive` (docs/ and .github/ are
# export-ignored, see .gitattributes) - one folder "tarsus", as Moodle's
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

for v in $versions; do
    tag="v$v"
    if git ls-remote --exit-code --tags origin "refs/tags/$tag" >/dev/null 2>&1; then
        echo "$tag exists, skipping"
        continue
    fi

    # The newest commit on this branch whose version.php declares the release.
    commit=""
    for c in $(git log --format=%H -- version.php); do
        if git show "$c:version.php" | grep -Fq "\$plugin->release = '$v';"; then
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
    git archive --format=zip --prefix=tarsus/ -o "$zip" "$tag"
    sum=$(sha256sum "$zip" | cut -d ' ' -f 1)

    {
        echo "Tarsus for Moodle $v – activity module (mod_tarsus) for Moodle 4.1 to 5.3."
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
        echo "3. Without that page (it can be switched off): unzip the file into Moodle's \`mod\` folder (Moodle 5.1 and newer: \`public/mod\`) – it contains the \`tarsus\` folder – and open *Site administration › Notifications*."
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
