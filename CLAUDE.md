# Claude Instructions for Friends Plugin

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

## WordPress Playground Testing

After pushing changes to a branch, provide a WordPress Playground link for testing:

```
https://playground.wordpress.net/#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/friends%22,%22ref%22:%22BRANCH_NAME%22,%22refType%22:%22branch%22},%22options%22:{%22activate%22:true}}]}
```

Replace `BRANCH_NAME` with the branch name. Use `"refType":"branch"` for branches (not `"refType":"refname"` which is for full refs like `refs/pull/123/head`).
