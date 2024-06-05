#!/bin/zsh
cd $(dirname $0)/..
FRIENDS_VERSION=$(egrep define...FRIENDS_VERSION friends.php | egrep -o "[0-9]+\.[0-9]+\.[0-9]+")

echo Friends Release $FRIENDS_VERSION
echo "====================="
echo

svn info | grep ^URL: | grep -q plugins.svn.wordpress.org/friends/trunk
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on the SVN path https://plugins.svn.wordpress.org/friends/trunk"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "On the SVN path https://plugins.svn.wordpress.org/friends/trunk"

git symbolic-ref --short HEAD | grep -q ^main$
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on git branch main"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "On git branch main"

svn update > /dev/null
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Failed to update svn"
	echo
	echo ❯ svn update
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "svn up to date"

git diff-files --quiet
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unstaged changes in git"
	echo
	echo ❯ git status
	git status
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "No unstaged changes in git"

git tag | egrep -q ^$FRIENDS_VERSION\$
if [ $? -eq 0 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Tag $FRIENDS_VERSION already exists"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Tag $FRIENDS_VERSION doesn't exist yet"

grep -q "Stable tag: $FRIENDS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Stable tag not updated in README.md:"
	awk '/Stable tag: / { print "  " $0 }' README.md
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Stable tag updated in README.md:"
awk '/Stable tag: / { print "  " $0 }' README.md

grep -q "Version: $FRIENDS_VERSION" friends.php
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Version not updated in friends.php:"
	awk '/Version: / { print "  " $0 }' friends.php
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Version updated in friends.php:"
awk '/Version: / { print "  " $0 }' friends.php

grep -q "### $FRIENDS_VERSION" CHANGELOG.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Changelog not found in CHANGELOG.md"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in CHANGELOG.md:"
awk '/^### '$FRIENDS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' CHANGELOG.md
grep -q "### $FRIENDS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -n "Changelog not found in README.md"
	echo -e "\033[31m✘\033[0m"
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in README.md:"
awk '/^### '$FRIENDS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' README.md
svn status | egrep -q "^[?]"
if [ $? -eq 0 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unknown files in svn"
	echo
	echo ❯ svn status
	svn status
	return
fi
echo -ne "\033[32m✔\033[0m "
echo "No unknown files in svn"
echo

echo -ne "\033[32m✔\033[0m "
echo "All looks good, ready to tag and commit!"
echo -n ❯ git push
read
git push
echo -n ❯ git tag $FRIENDS_VERSION
read
git tag $FRIENDS_VERSION
echo -n ❯ git push origin $FRIENDS_VERSION
read
git push origin $FRIENDS_VERSION
echo -n '❯ svn ci -m "Friends '$FRIENDS_VERSION'" && svn cp https://plugins.svn.wordpress.org/friends/trunk https://plugins.svn.wordpress.org/friends/tags/'$FRIENDS_VERSION' -m "Release '$FRIENDS_VERSION'"'
read
svn ci -m "Friends $FRIENDS_VERSION" && svn cp https://plugins.svn.wordpress.org/friends/trunk https://plugins.svn.wordpress.org/friends/tags/$FRIENDS_VERSION -m "Release $FRIENDS_VERSION"
echo Now create a new release on GitHub: https://github.com/akirk/friends/releases/new\?tag=$FRIENDS_VERSION
