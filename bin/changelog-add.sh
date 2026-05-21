#!/bin/bash
# Interactive helper to create a changelog entry for a PR.
# Usage: bin/changelog-add.sh <PR-number>

cd "$(dirname "$0")/.."

CHANGELOG_DIR=".github/changelog/unreleased"
mkdir -p "$CHANGELOG_DIR"

if [ -n "$1" ]; then
	PR_NUMBER="$1"
else
	echo -n "PR number: "
	read PR_NUMBER
fi

if ! echo "$PR_NUMBER" | grep -Eq '^[0-9]+$'; then
	echo "A numeric PR number is required."
	exit 1
fi

ENTRY_NAME="$PR_NUMBER-from-description"

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
