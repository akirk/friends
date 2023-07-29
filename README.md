# Friends

- Contributors: akirk
- Tags: friends, rss, decentralized, social-network, own-your-data
- Requires at least: 5.0
- Tested up to: 6.3
- Requires PHP: 5.6
- License: [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)
- Stable tag: 2.7.5

Your own WordPress at the center of your online activity. Follow friends and other websites and establish friendship relationships between blogs.

## Description

The Friends plugin allows you to follow content from other WordPress sites, and interact with them on your own site. You can follow friends and others via RSS. If you also have the ActivityPub plugin installed, you can follow people on Mastodon and other ActivityPub-compatible social networks.

**Since version 2.6.0, no users will be created for subscriptions.**

**Combine this plugin with the ActivityPub plugin to make your own WordPress your own Mastodon instance. Use the Enable Mastodon Apps to use mobile and desktop Mastodon apps with your own site.**

The Friends Plugin also has a "friend request" function which allows blogs to become friends with each other. This then allows private publishing on your blog while each of their friends has their own blog but will be able to see your privately published posts.

There are many small aspects that make it powerful self-hosted social reader:

You can...
- Have multiple feeds per person, so you can subscribe to their blog(s) and social media account(s).
- Categorize incoming content with Post Formats and view all posts of a certain format across your friends.
- Define rules to filter incoming content (sometimes you’re not interested in everything your friends do).
- Turn your favorite blog into your personal newsletter by receiving full-post notification e-mails
- Use feed rules to filter out content you are not interested in.
- Receive ePubs of your friends' posts to your eReader (via another plugin).
- Collect posts (from your feeds or around the web) in a collection for later reference (via another plugin).

[![Friends Plugin Demo on Youtube](img/friends-plugin-youtube-thumbnail.png)](https://www.youtube.com/watch?v=4bz6GluXnsk)

### Philosophy

The Friends Plugin was built to make use of what WordPress provides:

- You use the WordPress infrastructure (Gutenberg or Classic Editor, what you prefer) to create your posts.
- If a post is private, only logged-in friends can see it. They can only log in through their own Friends plugin on their blog.
- Therefore, your friend is just a user on your WordPress blog, their posts are theirs, you can delete them to unfriend them.
- No extra tables: The Friends plugin just uses a post type, options and some taxonomies to store its data. When you delete the plugin, your WordPress will be slim like before.

In future, I could see mobile apps instead of talking to a third party, to talk to your own blog. It will have your friends' posts already fetched. Maybe the apps will be specialized, like Twitter or Instagram, where you'd only interact with and create posts in the specific post format.

The logo was created by Ramon Dodd, @ramonopoly. Thank you!

Documentation for the plugin can be found on the [GitHub project Wiki](https://github.com/akirk/friends/wiki).

**Development of this plugin is done [on GitHub](https://github.com/akirk/friends). Pull requests welcome. Please see [issues](https://github.com/akirk/friends/issues) reported there before going to the [plugin forum](https://wordpress.org/support/plugin/friends).**

## Installation

1. Upload the `friends` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

## Frequently Asked Questions

### Does this plugin create custom tables?
No, all the functionality is achieved with standard WordPress means. Subscriptions or Friends are minimal-permission users on your install. External posts are cached in a custom post types and attributed to those users.

### Why does this create users on my WordPress install?
I believe this is a very elegant way to attribute content and it allows to delete the users content when you delete them. The users have minimal privileges, so they cannot be used to post actual content to your site.

The users can only be used for login through your specific friend's WordPress install (they are created with a strong password throw-away password), if they have been upgraded to a "friend" or "aquaintance" user.

### Why is the friendship established between WordPress sites and not WordPress users?
For one, this allows to stick with established WordPress configurations and terminologies. For example, you can use the WordPress mobile apps to post privately to your site.

Secondly, a lot of WordPresses are like cell phones. Some are used by more than one person but mostly there is a 1:1 relationship between a WordPress blog and a person.

If someone has multiple WordPresses this actually allows to segment your friendships. Close friends might want to follow all your blogs but you'd only add your photographer friends to your photoblog.

### What if the friend request is deleted or not accepted?
You'll still see the public posts from the other WordPress, you've subscribed to its public RSS feed.

### What's the point? If I want to post something privately I can use Facebook.
Well, that's actually exactly the point. Facebook owns your data, with WordPress you can decide where you want to host it and have all the benefits of running open source software.

### What happens if I modify or delete a post?
There is a cache of your friends post in form of a Custom Post Type friend_post that is updated when you change a post. When you delete a post your friends' WordPresses are notified and they delete the cached post.

## Screenshots

1. Compact view is like Google Reader
2. You can use it like a Feed Reader
3. But it is centered around users; you can have multiple feeds per person, even on social networks (parsing capabilities provided by plugins)
4. Extensible with plugins itself
5. Use the customizer to adapt it to your liking
6. Categorize incoming content with Post Formats and view all posts of a certain format across your friends
7. Use rules to filter incoming content (sometimes you’re not interested in everything your friends do)
8. Friends users are plain WordPress users with low privileges
9. A Friend Request is accepted in the users screen. Delete the user to reject it or accept the request to make them a friend

## Changelog

### 2.7.5
- Fix following users from Enable Mastodon Apps
- Fix disappearing posts after untrashing them

### 2.7.4
- Allow specifying a URL as avatar ([#243])
- Add more mastodon style urls for ActivityPub parsing autodetection (when there is no mime-type in the rel=me)
- Fixed a bug where comments were not retrieved via the comments RSS feed
- Fixed the require codeword checkbox

### 2.7.3
- Add get_posts args support for virtual users ([#242]), this allows the Enable Mastodon Apps to work with external mentions after [#215]

### 2.7.2
- Prepare for Activitypub Update which in its current state could cause a fatal ([#241])
- Post Collection compatibility: Allow trashing of Post Collection posts ([#240])
- Post Collection compatibility: Allow the post collection to be included in the autocomplete ([#239])
- Fix updating of user data when the subscription is a virtual user ([#238])

### 2.7.1
- Update Blocks Everywhere for WordPress 6.3 compatbility ([#236])
- ActivityPub: Also detect standard fediverse urls and assign the post-format status ([#237])

### 2.7.0
- Improve the Onboarding Experience ([#231])
- Expose the ActivityPub plugin more on the Add Friend screen ([#234])
- Fixed Quick Subscribe ([#233])
- Recognize the published date for announces ([#232])
- Allow setting the Collapsed view as default ([#224]) but don't collapse when there is just a single post ([#229])

### 2.6.0
- Don't create a user when only following someone ([#215]). This means that if you only want to use the Friends plugin to follow people, it will no longer create a user for each follow.
- Fix the hardcoded friends url path on the refresh widget ([#223])
- ActivityOPub: add support incoming video attachments ([#222])
- Friends Page: Status View improvements ([#220]):  Style the collapsed status view better. Move the Quick post toggle. Add Reply button and dropdown entry.
- Friends Page: Allow private sharing of a post on your friends page ([#217]). Adds a new "Share" item to the dropdown which gives you a special link that can be viewed by anyone.
- A number of small PHP 8.1 fixes

### 2.5.3
- Add a new Reply To field to the Status post form ([#210]), also see the related update to the Firefox extension at https://addons.mozilla.org/addon/wpfriends/
- Fix a Fatal regarding ActivityPub metadata

### 2.5.2
- Create better display name from follows from the Enable Mastodon Apps plugin
- Relay the external mentions user to the Enable Mastodon Apps plugin

### 2.5.1
- Prevent creation of a new feed for a virtual external mentions feed

### 2.5.0
- Fix/improve outgoing ActivityPub mention behavior for comments ([#205] and [#206])
- Support for external ActivityPub mentions ([#206])

- ### 2.4.0
- Reactions: Make it possible to issue reactions from outside ([#201]) 
- Add Reblogging and Announce support for ActivityPub ([#168]) 
- Add filters and metadata to make the  Enable Mastodon Apps plugin possible ([#200]) 
- ActivityPub: Auto-approve comments by people you follow ([#199]) 
- Add more explicit plugin recommendations ([#203] and [#202])

### 2.3.1
- Remove stale ActivityPub version and use the information from WordPress.org ([#195])
- Allow mixing of post formats on the frontend
- Allow filtering notifications by the incoming feed ([#196])
- Fix friends menu on other site ([#189]) 
- Added missing space to generated SQL query ([#191]) 

### 2.3.0
- The Wiki now contains a [Hooks Documentation](https://github.com/akirk/friends/wiki/Hooks) ([#167]) 
- Frontend: Add CSS rule to fix odd resizing ([#166]) 
- Frontend: Add a "Show hidden items" in the main feed header ([#172]) 
- Frontend: Upgrade Blocks Everywhere, hide Quick Post Panel by default ([#180], [#183]) 
- Frontend: Don't let the Post Kinds plugin interfere with the friends query ([#176]) 
- Admin: Fix inconsistencies with links to the new friends list link ([#184]) props @pfefferle
- Admin: Tweak text for "Show Hidden Items" setting, per-feed. ([#173]) props @alecmuffett

### 2.2.0
- Fix remote friends plugin detection on public Friends page when headers are stripped ([#160]).
- Permissions: Allow using the friends plugin as an Editor ([#121]). An administrator account no longer required, you only need one to set the main user and adjust blog-level settings.
- Automatic status: Add option to disable creation of automatic status post drafts ([#141]).
- ActivityPub: Support for outgoing mentions (prepared in [activitypub#213] by removing the parser from there for quicker iteration). Implemented in [#137].
- ActivityPub: Use the avatar from ActivityPub ([#142]) and allow setting it after the fact (it won't change automatically).
- ActivityPub: Add outbox support ([#163]) which means that when you now subscribe to someone new, it will fetch old posts.

### 2.1.3
- Feeds: fixed a bug where when adding a new user and subscribing to an ActivityPub feed at the same time, the ActivityPub account won't be followed (would need a deactivate/actiate of the feed).
- Taxonomies: Hide the user-feed and reactions taxonomies from the public and delete their entries when uninstalling the plugin ([#132]).
- Improved PHP8 compatibility and start testing with PHP8.2 in the CI.

### 2.1.2
- Frontend: add option to show/hide hidden entries on a user page ([#124]).
- Admin: Fix that the main settings page would not save.
- Plugins (ActivityPub): Add a function to get all friends for a specific parser. This is for [activitypub#213].
- Plugins: Allow plugins to suggest a better display name and username for friends via two new hooks `friends_suggest_user_login` and `friends_suggest_display_name`.

### 2.1.1
- Friends messages and status posting UI: Bring back the Gutenberg Editor using the updated blocks-everywhere ([#122]).
- Admin: Fix a redirect problem on the autostatus admin page.
- Status posts: fix collapsing them and only output the status post box on the main status feed to avoid confusion when your posts don't show up on an author status page.

### 2.1.0
- Plugins: Add filters for allowing ActivityPub integrations (see [activitypub#172]): `friends_rewrite_incoming_url`, `friends_user_feed_activated`, `friends_user_feed_deactivated`.
- Plugins: Add ActivityPub to the Friends Plugins installer.
- Frontend: Add ability to read a post's comments inside the Friends plugin using the comments button (if it supplies a comments feed in its RSS which all WordPresses do) ([#113]).
- Frontend: Add different styling for images and status feed. Add a "post status" box to the status feed.
- Frontend: Allow starring friends + new widget to display those starred friends for convenient access.
- Frontend: Add a recent friends widget that displays your newest friends and subscriptions for convenient access.
- Admin: Introduce tabs on various friends settings for a better overview ([#116]).
- Admin: Remove the Welcome admin notice in favor a Welcome screen inside the friends plugin.
- Admin: Improve the friend space usage calculation for friends with many posts.
- Admin: Improve Automattic Status ([#111]).
- Core: Improved the WP_Query for the frontend to allow to show your reactions to the posts ([#114]).
- Core: allow frontend detection earlier in the WordPress boot process allowing our `add_theme_support()`s to kick in based on whether we are on the frontend.
- Core: Fetch feeds based on their due date. This will allow adjusting feed fetch intervals individually for feeds ([#109]).
- Multisite: Improve activation code so that the friend roles should now be created more reliably on new multisite blogs ([#107]).
- Plugins: Add filters for the Friends roles plugin: `friends_plugin_roles`.

### 2.0.2
- Fix namespace on activation, deactivation and uninstall hooks

### 2.0.1
- Fix a problem with retrieving and up to date plugins.json file from Github

### 2.0.0
- Improved handling of updated feed items and how they are stored as revisions
- PHP: Introduced a namespace, changed the plugin hooks to friends_loaded and friends_load_parsers
- Add checks for diagnosing the correct functioning to Site Health

### 1.9.1
- Fix a fatal error

### 1.9.0
- New feature: Keyword Matcher (get a notification if an incoming post contains keywords you can specify)
- New feature: Automatic status posts (automatically create draft status posts for certain events, they need to be published manually)
- Disable Gutenberg in messages until Gutenberg-Everywhere has been fixed

### 1.8.5
- Multisite: fix adding user to site instead of requiring a new username

### 1.8.4
- Fixes for a PHP fatal and some notices

### 1.8.3
- Improve default feed selection when adding a friend
- Fix improper plugin directory when a friends plugin installed from Github

### 1.8.2
- Move plugin updater data to Github

### 1.8.1
- Fix whitespace missing in feed additions

### 1.8.0
- Small bugfixes (main query modification, menu conflict, show the friends menu in wp-admin, allow regex to match post length)
- Use the Gutenberg editor for blog to blog messaging

### 1.7.0
- Add Emoji reactions
- Add blog to blog messaging

[#241]: https://github.com/akirk/friends/pull/241
[#240]: https://github.com/akirk/friends/pull/240
[#239]: https://github.com/akirk/friends/pull/239
[#238]: https://github.com/akirk/friends/pull/238
[#237]: https://github.com/akirk/friends/pull/237
[#236]: https://github.com/akirk/friends/pull/236
[#231]: https://github.com/akirk/friends/pull/231
[#234]: https://github.com/akirk/friends/pull/234
[#233]: https://github.com/akirk/friends/pull/233
[#232]: https://github.com/akirk/friends/pull/232
[#224]: https://github.com/akirk/friends/pull/224
[#229]: https://github.com/akirk/friends/pull/229
[#223]: https://github.com/akirk/friends/pull/223
[#222]: https://github.com/akirk/friends/pull/222
[#220]: https://github.com/akirk/friends/pull/220
[#217]: https://github.com/akirk/friends/pull/217
[#215]: https://github.com/akirk/friends/pull/215
[#210]: https://github.com/akirk/friends/pull/210
[#206]: https://github.com/akirk/friends/pull/206
[#205]: https://github.com/akirk/friends/pull/205
[#201]: https://github.com/akirk/friends/pull/201
[#168]: https://github.com/akirk/friends/pull/168
[#200]: https://github.com/akirk/friends/pull/200
[#199]: https://github.com/akirk/friends/pull/199
[#203]: https://github.com/akirk/friends/pull/203
[#202]: https://github.com/akirk/friends/pull/202
[#195]: https://github.com/akirk/friends/pull/195
[#196]: https://github.com/akirk/friends/pull/196
[#189]: https://github.com/akirk/friends/pull/189
[#191]: https://github.com/akirk/friends/pull/191
[#184]: https://github.com/akirk/friends/pull/184
[#183]: https://github.com/akirk/friends/pull/183
[#180]: https://github.com/akirk/friends/pull/180
[#176]: https://github.com/akirk/friends/pull/176
[#173]: https://github.com/akirk/friends/pull/173
[#172]: https://github.com/akirk/friends/pull/172
[#167]: https://github.com/akirk/friends/pull/167
[#166]: https://github.com/akirk/friends/pull/166
[#160]: https://github.com/akirk/friends/pull/160
[#121]: https://github.com/akirk/friends/pull/121
[#141]: https://github.com/akirk/friends/pull/141
[#137]: https://github.com/akirk/friends/pull/137
[#142]: https://github.com/akirk/friends/pull/142
[#163]: https://github.com/akirk/friends/pull/163
[#132]: https://github.com/akirk/friends/pull/132
[#124]: https://github.com/akirk/friends/pull/124
[#122]: https://github.com/akirk/friends/pull/122
[#113]: https://github.com/akirk/friends/pull/113
[#116]: https://github.com/akirk/friends/pull/116
[#111]: https://github.com/akirk/friends/pull/111
[#114]: https://github.com/akirk/friends/pull/114
[#109]: https://github.com/akirk/friends/pull/109
[#107]: https://github.com/akirk/friends/pull/107
[activitypub#213]: https://github.com/pfefferle/wordpress-activitypub/pull/213
[activitypub#172]: https://github.com/pfefferle/wordpress-activitypub/pull/172
