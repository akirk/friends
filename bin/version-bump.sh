#!/bin/bash
# Update the version number in all locations.
# Usage: bin/version-bump.sh <new-version>

cd "$(dirname "$0")/.."

NEW_VERSION="$1"

if [ -z "$NEW_VERSION" ]; then
	echo "Usage: bin/version-bump.sh <new-version>"
	exit 1
fi

if ! echo "$NEW_VERSION" | grep -Eq "^[0-9]+\.[0-9]+\.[0-9]+$"; then
	echo -ne "\033[31m✘\033[0m "
	echo "Invalid version number: $NEW_VERSION (expected X.Y.Z)"
	exit 1
fi

OLD_VERSION=$(grep -E "define.*FRIENDS_VERSION" friends.php | grep -Eo "[0-9]+\.[0-9]+\.[0-9]+")

if [ -z "$OLD_VERSION" ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Could not determine current version from friends.php"
	exit 1
fi

if [ "$OLD_VERSION" = "$NEW_VERSION" ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "New version is the same as current version: $OLD_VERSION"
	exit 1
fi

echo "Bumping version: $OLD_VERSION → $NEW_VERSION"
echo

# Update friends.php (plugin header + constant)
sed -i "s/Version: $OLD_VERSION/Version: $NEW_VERSION/" friends.php
sed -i "s/FRIENDS_VERSION', '$OLD_VERSION'/FRIENDS_VERSION', '$NEW_VERSION'/" friends.php
rm -f friends.php-e

echo -ne "\033[32m✔\033[0m "
echo "friends.php updated"

# Update README.md stable tag
sed -i "s/Stable tag: $OLD_VERSION/Stable tag: $NEW_VERSION/" README.md
rm -f README.md-e

echo -ne "\033[32m✔\033[0m "
echo "README.md stable tag updated"

# Verify
echo
echo "Verification:"

grep -q "Version: $NEW_VERSION" friends.php
if [ $? -eq 0 ]; then
	echo -ne "\033[32m✔\033[0m "
else
	echo -ne "\033[31m✘\033[0m "
fi
grep "Version: " friends.php | head -1 | sed 's/^.*\*/  /'

grep -q "FRIENDS_VERSION', '$NEW_VERSION'" friends.php
if [ $? -eq 0 ]; then
	echo -ne "\033[32m✔\033[0m "
else
	echo -ne "\033[31m✘\033[0m "
fi
grep "FRIENDS_VERSION" friends.php | sed 's/^/  /'

grep -q "Stable tag: $NEW_VERSION" README.md
if [ $? -eq 0 ]; then
	echo -ne "\033[32m✔\033[0m "
else
	echo -ne "\033[31m✘\033[0m "
fi
grep "Stable tag:" README.md | sed 's/^/  /'
