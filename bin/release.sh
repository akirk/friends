#!/bin/bash
cd "$(dirname "$0")/.."
FRIENDS_VERSION=$(grep -E define...FRIENDS_VERSION friends.php | grep -Eo "[0-9]+\.[0-9]+\.[0-9]+")

echo Friends Release $FRIENDS_VERSION
echo "====================="
echo

svn info | grep ^URL: | grep -q plugins.svn.wordpress.org/friends/trunk
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on the SVN path https://plugins.svn.wordpress.org/friends/trunk"
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "On the SVN path https://plugins.svn.wordpress.org/friends/trunk"

git symbolic-ref --short HEAD | grep -q ^main$
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Not on git branch main"
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "On git branch main"

if $(git tag | grep -Eq ^$FRIENDS_VERSION\$); then
	echo -ne "\033[31m✘\033[0m "
	echo "Tag $FRIENDS_VERSION already exists"

	echo -n "Which version number shall this version get? "
	read NEW_VERSION

	if ! echo $NEW_VERSION | grep -Eq "^[0-9]+\.[0-9]+\.[0-9]+$"; then
		echo -ne "\033[31m✘\033[0m "
		echo "Invalid version number $NEW_VERSION"
		exit 1
	fi

	if [ -n new-changelog.md ]; then
		prs=$(git log $FRIENDS_VERSION..main --pretty=format:"- %s")
		echo "### $NEW_VERSION" > new-changelog.md
		echo -e "$prs" >> new-changelog.md
	fi

	if [ -n "$VISUAL" ]; then
		CMD="${VISUAL%% *}"
		ARGS="${VISUAL#* }"
		$CMD $ARGS new-changelog.md
	else
		$EDITOR new-changelog.md
	fi

	if [ $? -eq 1 ]; then
		echo -ne "\033[31m✘\033[0m "
		echo "Failed to open $VISUAL"

		echo "This is the generated changelog:"
		cat new-changelog.md
		echo -n "Do you want to continue? [y/N] "
		read

		if [ "$REPLY" != "y" ]; then
			exit 1
		fi
	fi

	links=""
	for link in $(grep -Eo "#[0-9]+" new-changelog.md | sort | uniq); do
		links="$links\n[$link]: https://github.com/akirk/friends/pull/${link:1}"
	done

	echo >> new-changelog.md

	cat new-changelog.md > CHANGELOG.new
	cat CHANGELOG.md | sed -e "s/#\([0-9]\+\)/[\1]/g" >> CHANGELOG.new
	echo -e "$links" >> CHANGELOG.new
	mv CHANGELOG.new CHANGELOG.md

	echo -ne "\033[32m✔\033[0m "
	echo "Changelog updated in CHANGELOG.md"

	sed -i -e '/## Changelog/{n
r new-changelog.md
}' README.md

	rm -f README.md-e
	echo -e "$links" >> README.md

	echo -ne "\033[32m✔\033[0m "
	echo "Changelog updated in README.md"

	sed -i -e "s/$FRIENDS_VERSION/$NEW_VERSION/" friends.php
	rm -f friends.php-e

	echo -ne "\033[32m✔\033[0m "
	echo "Version updated in friends.php"

	sed -i -e "s/Stable tag: $FRIENDS_VERSION/Stable tag: $NEW_VERSION/" README.md
	rm -f README.md-e

	echo -ne "\033[32m✔\033[0m "
	echo "Stable tag updated in README.md"

	echo -n "❯ git diff CHANGELOG.md README.md friends.php"
	read
	git diff CHANGELOG.md README.md friends.php

	echo -n "Are you happy with the changes? [y/N] "
	read

	if [ "$REPLY" != "y" ]; then
		echo "You can revert the changes with"
		echo
		echo "❯ git checkout CHANGELOG.md README.md friends.php"
		echo

		read
		git checkout CHANGELOG.md README.md friends.php

		echo Keeping the new
		exit 1
	fi
	rm -f new-changelog.md


	echo -n "❯ git add CHANGELOG.md README.md friends.php"
	read
	git add CHANGELOG.md README.md friends.php

	echo -n "❯ git commit -m \"Version bump + Changelog"
	read
	git commit -m "Version bump + Changelog"

	echo -n "❯ git push"
	read
	git push

	echo "Restart the script to continue"
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "Tag $FRIENDS_VERSION doesn't exist yet"

svn update > /dev/null
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Failed to update svn"
	echo
	echo ❯ svn update
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "svn up to date"

grep -q "Stable tag: $FRIENDS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Stable tag not updated in README.md:"
	awk '/Stable tag: / { print "  " $0 }' README.md
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "Stable tag updated in README.md:"
awk '/Stable tag: / { print "  " $0 }' README.md

grep -q "Version: $FRIENDS_VERSION" friends.php
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Version not updated in friends.php:"
	awk '/Version: / { print "  " $0 }' friends.php
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "Version updated in friends.php:"
awk '/Version: / { print "  " $0 }' friends.php

grep -q "### $FRIENDS_VERSION" CHANGELOG.md
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Changelog not found in CHANGELOG.md"
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in CHANGELOG.md:"
awk '/^### '$FRIENDS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' CHANGELOG.md
grep -q "### $FRIENDS_VERSION" README.md
if [ $? -eq 1 ]; then
	echo -n "Changelog not found in README.md"
	echo -e "\033[31m✘\033[0m"
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "Changelog updated in README.md:"
awk '/^### '$FRIENDS_VERSION'/ { print "  " $0; show = 1; next } /^###/ { show = 0 } { if ( show ) print "  " $0 }' README.md
svn status | grep -Eq "^[?]"
if [ $? -eq 0 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unknown files in svn"
	echo
	echo ❯ svn status
	svn status
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "No unknown files in svn"
echo

git diff-files --quiet
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Unstaged changes in git"
	echo
	echo ❯ git status
	git status
	exit 1
fi
echo -ne "\033[32m✔\033[0m "
echo "No unstaged changes in git"

echo -ne "\033[32m✔\033[0m "
echo "All looks good, ready to tag and commit!"
echo -n ❯ git push
read
git push
if [ $? -eq 1 ]; then
	echo -ne "\033[31m✘\033[0m "
	echo "Failed to push to git"
	exit 1
fi
echo -n ❯ git tag $FRIENDS_VERSION
read
git tag $FRIENDS_VERSION
echo -n ❯ git push origin $FRIENDS_VERSION
read
git push origin $FRIENDS_VERSION
echo -n '❯ svn ci -m "Friends '$FRIENDS_VERSION'" && svn cp https://plugins.svn.wordpress.org/friends/trunk https://plugins.svn.wordpress.org/friends/tags/'$FRIENDS_VERSION' -m "Release '$FRIENDS_VERSION'"'
read
svn ci -m "Friends $FRIENDS_VERSION" && svn cp https://plugins.svn.wordpress.org/friends/trunk https://plugins.svn.wordpress.org/friends/tags/$FRIENDS_VERSION -m "Release $FRIENDS_VERSION"
echo -n '❯ gh release create '$FRIENDS_VERSION' --generate-notes'
read
gh release create $FRIENDS_VERSION --generate-notes
