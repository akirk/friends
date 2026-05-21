#!/bin/bash
# Compile changelog entries from .github/changelog/ into CHANGELOG.md and README.md.
# Usage: bin/changelog-write.sh [--dry-run] <version>

cd "$(dirname "$0")/.."

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then
	DRY_RUN=1
	shift
fi

VERSION="$1"

if [ -z "$VERSION" ]; then
	echo "Usage: bin/changelog-write.sh [--dry-run] <version>"
	exit 1
fi

UNRELEASED_DIR=".github/changelog/unreleased"
ENTRIES=()
PR_NUMBERS=()
HAS_ERRORS=0
REPO="${GITHUB_REPOSITORY:-akirk/friends}"

if [ ! -d "$UNRELEASED_DIR" ] || [ -z "$(ls -A "$UNRELEASED_DIR" 2>/dev/null)" ]; then
	echo "No changelog entries found in $UNRELEASED_DIR/"
	exit 1
fi

# Collect all changelog entries
for entry_file in "$UNRELEASED_DIR"/*; do
	filename=$(basename "$entry_file")

	# Extract PR number from filename (e.g., "123" or "123-from-description")
	pr_number=$(echo "$filename" | grep -Eo '^[0-9]+')

	# Branch-named files (e.g., "fix-theme-loading-propeller") have no PR number in
	# the filename. Look it up from git history.
	if [ -z "$pr_number" ]; then
		add_commit=$(git log --diff-filter=A --format='%H' -1 -- "$entry_file")

		if [ -n "$add_commit" ]; then
			# Squash-merged PRs usually include "(#NNN)" in the subject.
			pr_number=$(git log -1 --format='%s' "$add_commit" | grep -Eo '\(#[0-9]+\)' | tail -1 | grep -Eo '[0-9]+')

			# Rebase-merged PRs leave no PR ref in the commit message; ask GitHub.
			if [ -z "$pr_number" ] && command -v gh >/dev/null 2>&1; then
				pr_number=$(gh api -H "Accept: application/vnd.github+json" "repos/$REPO/commits/$add_commit/pulls" --jq '.[0].number // empty' 2>/dev/null)
			fi
		fi
	fi

	pr_number=$(printf '%s\n' "$pr_number" | grep -Eo '^[0-9]+$' | head -1)

	if [ -z "$pr_number" ]; then
		echo -ne "\033[31m✘\033[0m "
		echo "Could not determine PR number for $filename"
		if [ -n "${add_commit:-}" ]; then
			echo "  Added in commit $add_commit, but no associated PR could be resolved."
		fi
		echo "  Use a PR-numbered filename or run with an authenticated GitHub CLI so branch-named entries can be resolved."
		HAS_ERRORS=1
		continue
	fi

	# Read the message (everything after the blank line)
	message=$(awk 'BEGIN{found=0} /^$/{found=1; next} found{print}' "$entry_file" | head -1)

	if [ -z "$message" ]; then
		echo -ne "\033[31m✘\033[0m "
		echo "No message found in $filename"
		HAS_ERRORS=1
		continue
	fi

	ENTRIES+=("- $message ([#$pr_number])")
	PR_NUMBERS+=("$pr_number")
done

if [ "$HAS_ERRORS" = 1 ]; then
	echo "Refusing to write changelog with unresolved or invalid entries. Fix the entries above and retry."
	exit 1
fi

if [ ${#ENTRIES[@]} -eq 0 ]; then
	echo "No changelog entries found in $UNRELEASED_DIR/"
	exit 1
fi

echo "Changelog entries for $VERSION:"
for entry in "${ENTRIES[@]}"; do
	echo "  $entry"
done
echo

if [ "$DRY_RUN" = 1 ]; then
	echo "Dry run: would update CHANGELOG.md and README.md, then archive entries to .github/changelog/$VERSION/"
	exit 0
fi

# Write the new section to a temp file
SECTION_FILE=$(mktemp)
echo "### $VERSION" > "$SECTION_FILE"
for entry in "${ENTRIES[@]}"; do
	echo "$entry" >> "$SECTION_FILE"
done

# Write PR reference links to a temp file
LINKS_FILE=$(mktemp)
for pr in $(printf '%s\n' "${PR_NUMBERS[@]}" | sort -n | uniq); do
	echo "[#$pr]: https://github.com/akirk/friends/pull/$pr" >> "$LINKS_FILE"
done

# Prepend to CHANGELOG.md
{
	cat "$SECTION_FILE"
	echo
	cat CHANGELOG.md
} > CHANGELOG.md.tmp
mv CHANGELOG.md.tmp CHANGELOG.md

# Append links to CHANGELOG.md
echo >> CHANGELOG.md
cat "$LINKS_FILE" >> CHANGELOG.md

echo -ne "\033[32m✔\033[0m "
echo "Updated CHANGELOG.md"

# Insert into README.md after "## Changelog" heading using awk + temp file
awk -v sfile="$SECTION_FILE" '
/^## Changelog$/ {
	print
	print ""
	while ((getline line < sfile) > 0) print line
	next
}
{ print }
' README.md > README.md.tmp
mv README.md.tmp README.md

# Append links to README.md
echo >> README.md
cat "$LINKS_FILE" >> README.md

echo -ne "\033[32m✔\033[0m "
echo "Updated README.md"

# Clean up temp files
rm -f "$SECTION_FILE" "$LINKS_FILE"

# Rename unreleased/ to version directory as archive
mv "$UNRELEASED_DIR" ".github/changelog/$VERSION"

echo -ne "\033[32m✔\033[0m "
echo "Archived entries to .github/changelog/$VERSION/"
