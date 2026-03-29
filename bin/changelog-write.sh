#!/bin/bash
# Compile changelog entries from .github/changelog/ into CHANGELOG.md and README.md.
# Usage: bin/changelog-write.sh <version>

cd "$(dirname "$0")/.."

VERSION="$1"

if [ -z "$VERSION" ]; then
	echo "Usage: bin/changelog-write.sh <version>"
	exit 1
fi

UNRELEASED_DIR=".github/changelog/unreleased"
ENTRIES=()
PR_NUMBERS=()

if [ ! -d "$UNRELEASED_DIR" ] || [ -z "$(ls -A "$UNRELEASED_DIR" 2>/dev/null)" ]; then
	echo "No changelog entries found in $UNRELEASED_DIR/"
	exit 1
fi

# Collect all changelog entries
for entry_file in "$UNRELEASED_DIR"/*; do
	filename=$(basename "$entry_file")

	# Extract PR number from filename (e.g., "123" or "123-from-description")
	pr_number=$(echo "$filename" | grep -Eo '^[0-9]+')

	if [ -z "$pr_number" ]; then
		echo -ne "\033[33m!\033[0m "
		echo "Skipping $filename (no PR number in filename)"
		continue
	fi

	# Read the message (everything after the blank line)
	message=$(awk 'BEGIN{found=0} /^$/{found=1; next} found{print}' "$entry_file" | head -1)

	if [ -z "$message" ]; then
		echo -ne "\033[33m!\033[0m "
		echo "Skipping $filename (no message found)"
		continue
	fi

	ENTRIES+=("- $message ([#$pr_number])")
	PR_NUMBERS+=("$pr_number")
done

if [ ${#ENTRIES[@]} -eq 0 ]; then
	echo "No changelog entries found in $UNRELEASED_DIR/"
	exit 1
fi

echo "Changelog entries for $VERSION:"
for entry in "${ENTRIES[@]}"; do
	echo "  $entry"
done
echo

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
