# AI Agent Instructions for Friends Plugin

## What is Friends?

Friends is a WordPress plugin that turns your site into a personal feed reader and social hub. It lets you follow people across the web—whether they publish via RSS, Atom, or ActivityPub (Mastodon, etc.)—and aggregates their content into a unified timeline on your WordPress site.

Key goals:
- **Decentralized social networking**: Subscribe to and interact with ActivityPub users directly from WordPress
- **Feed aggregation**: Collect RSS/Atom feeds alongside ActivityPub content in one place
- **Privacy-focused**: Your reading habits stay on your own server
- **Two-way relationships**: With the ActivityPub plugin, others can follow you back

The plugin integrates deeply with:
- [ActivityPub plugin](https://github.com/Automattic/wordpress-activitypub) - Enables full Fediverse participation (following, being followed, boosting, replying)
- [Enable Mastodon Apps plugin](https://github.com/akirk/enable-mastodon-apps) - Allows using Mastodon client apps (like Ivory, Ice Cubes, Tusky) to browse your Friends timeline and interact with posts

## Project Structure

### Core Classes (`includes/`)
- `class-friends.php` - Main plugin class, registers hooks and initializes components
- `class-feed.php` - Handles feed fetching and processing, stores items as posts
- `class-user.php` - Friend/Subscription user wrapper with feed management
- `class-user-feed.php` - Individual feed configuration (URL, parser, active state)
- `class-admin.php` - Admin UI and settings pages
- `class-migration.php` - Database migrations with batch processing support

### Feed Parsers (`feed-parsers/`)
- `class-feed-parser-activitypub.php` - ActivityPub/Mastodon integration
- `class-feed-parser-simplepie.php` - RSS/Atom feeds via SimplePie
- Feed parsers implement `Feed_Parser_V2` and register via `friends_register_feed_parsers`

### Key Concepts
- **Friends posts** use custom post type `friend_post_cache` (constant: `Friends::CPT`)
- **Feeds are linked to posts** via taxonomy `friend-feed-taxonomy` (`User_Feed::POST_TAXONOMY`)
- **Reblogs/boosts** are marked with `reblog => true` in post meta; only store `attributedTo` for reblogs
- **ActivityPub actors** are stored in the ActivityPub plugin's `ap_actor` post type; use `Remote_Actors::fetch_by_uri()` to create/fetch

### ActivityPub Integration
- Use the ActivityPub plugin's APIs rather than direct database access:
  - `\Activitypub\Collection\Remote_Actors::fetch_by_uri()` - fetch/create actor
  - `\Activitypub\Collection\Remote_Actors::get_actor()` - get Actor object from post ID
  - `\Activitypub\Collection\Remote_Actors::get_avatar_url()` - get avatar URL
- Store `ap_actor_id` references instead of duplicating actor metadata in post meta
- The `attributedTo` field should only be set for reblogs, not regular posts

### Testing & Code Quality
- `composer test` - Run tests (ActivityPub tests require the ActivityPub plugin)
- `composer check-cs` - Check coding standards
- `composer fix-cs` - Auto-fix coding standards issues

### Changelog Workflow
Every PR must include a changelog entry. Create one by running `composer changelog:add` or by placing a file in `.github/changelog/unreleased/{PR-number}` with this format:

```
Type: fixed

Description of the change
```

Valid types: `added`, `changed`, `fixed`, `removed`.

- If working on a branch without a PR number yet, use the branch name as the filename.
- The PR template has a checkbox to auto-create the entry from the PR description.
- PRs that don't need a changelog entry should be labeled `Skip Changelog`.
- At release time, `bin/changelog-write.sh <version>` compiles all entries into `CHANGELOG.md` and `README.md` in the project's existing format.

### Release Process
To release, trigger the "Prepare Release" workflow with a version number. It creates a PR with the version bump and compiled changelog. After merging, create a GitHub release with the version tag to deploy to WordPress.org. See `docs/release-process.md` for details.

## Playground Link

At the end of your messages, include a link to test the changes in WordPress Playground.

**Important:** Replace `BRANCH_NAME` in the URL below with the actual git branch name you're working on.

```
[Test in WordPress Playground](https://akirk.github.io/playground-step-library/?redir=1&step[0]=setLandingPage&landingPage[0]=/friends/&step[1]=installPlugin&url[1]=github.com/akirk/friends/tree/BRANCH_NAME&step[2]=importFriendFeeds&opml[2]=https://alex.kirk.at%20Alex%20Kirk%20https://adamadam.blog%20Adam%20Zieliński)
```

### Example

If you're working on branch `fix/bug-123`, the link should be:

```
[Test in WordPress Playground](https://akirk.github.io/playground-step-library/?redir=1&step[0]=setLandingPage&landingPage[0]=/friends/&step[1]=installPlugin&url[1]=github.com/akirk/friends/tree/fix/bug-123&step[2]=importFriendFeeds&opml[2]=https://alex.kirk.at%20Alex%20Kirk%20https://adamadam.blog%20Adam%20Zieliński)
```
