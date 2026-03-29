#!/bin/bash
# Interactive helper to create a changelog entry for a PR.
# Usage: bin/changelog-add.sh [PR-number]

cd "$(dirname "$0")/.."

CHANGELOG_DIR=".github/changelog/unreleased"
mkdir -p "$CHANGELOG_DIR"

# Determine the filename (PR number or branch name)
if [ -n "$1" ]; then
	ENTRY_NAME="$1"
else
	echo -n "PR number (or leave empty to use branch name): "
	read PR_NUMBER
	if [ -n "$PR_NUMBER" ]; then
		ENTRY_NAME="$PR_NUMBER"
	else
		ENTRY_NAME=$(git rev-parse --abbrev-ref HEAD | sed 's/[\/]/-/g')
	fi
fi

if [ -f "$CHANGELOG_DIR/$ENTRY_NAME" ]; then
	echo "Entry $CHANGELOG_DIR/$ENTRY_NAME already exists:"
	cat "$CHANGELOG_DIR/$ENTRY_NAME"
	echo
	echo -n "Overwrite? [y/N] "
	read
	if [ "$REPLY" != "y" ]; then
		exit 0
	fi
fi

# Select type
echo "Type:"
echo "  1) Added"
echo "  2) Changed"
echo "  3) Fixed"
echo "  4) Removed"
echo -n "Choose [1-4]: "
read TYPE_NUM

case "$TYPE_NUM" in
	1) TYPE="added" ;;
	2) TYPE="changed" ;;
	3) TYPE="fixed" ;;
	4) TYPE="removed" ;;
	*)
		echo "Invalid type"
		exit 1
		;;
esac

# Get message
echo -n "Changelog message: "
read MESSAGE

if [ -z "$MESSAGE" ]; then
	echo "Message cannot be empty"
	exit 1
fi

# Write the entry
cat > "$CHANGELOG_DIR/$ENTRY_NAME" <<EOF
Type: $TYPE

$MESSAGE
EOF

echo -ne "\033[32m✔\033[0m "
echo "Created $CHANGELOG_DIR/$ENTRY_NAME"
cat "$CHANGELOG_DIR/$ENTRY_NAME"
