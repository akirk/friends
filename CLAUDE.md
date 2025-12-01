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

### Testing
- Run tests with `composer test`
- ActivityPub tests require the ActivityPub plugin to be available

## WordPress Playground Testing

When working on this WordPress plugin, you can help users test changes by providing a WordPress Playground link that uses the current branch.

### How to Generate Playground Links

When you've made changes to the plugin and pushed to a branch, provide a WordPress Playground link in this format:

```
https://playground.wordpress.net/#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/friends%22,%22ref%22:%22BRANCH_NAME%22,%22refType%22:%22branch%22},%22options%22:{%22activate%22:true}}]}
```

Replace `BRANCH_NAME` with the actual branch name you're working on.

### Example

If working on branch `claude/add-feature-xyz`, the link would be:

```
https://playground.wordpress.net/#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/friends%22,%22ref%22:%22claude/add-feature-xyz%22,%22refType%22:%22branch%22},%22options%22:{%22activate%22:true}}]}
```

### When to Provide Links

- After completing a feature implementation
- After fixing a bug
- When the user requests to test changes
- After creating or updating a pull request

### Message Format

When providing the link, use this format:

```markdown
## Test These Changes in WordPress Playground

You can test the changes from branch `BRANCH_NAME` directly in WordPress Playground:

[Launch WordPress Playground](PLAYGROUND_URL)

This will install and activate the plugin with the changes from this branch.
```

### Notes

- The playground link uses the GitHub repository as the source
- The `installPlugin` step automatically installs and activates the plugin
- Users can test the changes without needing to set up a local WordPress environment
- This mirrors the functionality of the GitHub action in `.github/workflows/pr-playground-comment.yml` but uses branches instead of PR refs
- Important: Use `"refType":"branch"` when referencing branch names, not `"refType":"refname"` (which is used for full refs like `refs/pull/123/head`)
