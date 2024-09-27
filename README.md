# Friends

- Contributors: akirk
- Tags: friends, rss, decentralized, social-network, own-your-data
- Requires at least: 5.0
- Tested up to: 6.6
- License: GPL-2.0-or-later
- Stable tag: 2.9.9

Your own WordPress at the center of your online activity. Follow friends and other websites and establish friendship relationships between blogs.

[![Active Installs](https://img.shields.io/wordpress/plugin/installs/friends?logo=wordpress&logoColor=%23fff&label=Active%20Installs&labelColor=%2323282dA&color=%2323282dA)](https://wordpress.org/plugins/friends/) [![Playground Demo Link](https://img.shields.io/wordpress/plugin/v/friends?logo=wordpress&logoColor=%23fff&label=Playground%20Demo&labelColor=%233858e9&color=%233858e9)](https://wordpress.org/plugins/friends/?preview=1)

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

### 2.9.9
- Avoid fatal when no user login can be found during boosting ([#355])
- A small update of a filter for Enable Mastodon Apps 0.9.8

### 2.9.8
- Fix ActivityPub preview and use more details when creating the user ([#354])

### 2.9.7
- Fix boost button ([#353])

### 2.9.6
- Allow creating multiple dashboard widgets in ([#349])
- Add support for double-encoded HTML entities in RSS in ([#352])
- Expose the list of your followers and make it easy to follow back in ([#351])
- Fixed a bug that could cause double items on initial feed refresh.

### 2.9.5
- Don't override ActivityPub mentions ([#345])
- Add a dashboard widget ([#346])
- Automatically update dashboard widget with new posts ([#347])
- Account for WordPress installs in a directory ([#348])

### 2.9.4
- Fix an out of memory error in get_author_posts_url ([#339])
- Fix ignore notification settings when no `friend_listed` passed by @logicalor in ([#324])
- Null coalesce `$html` to empty string to avoid deprecation notices by @logicalor in ([#325])
- Support the WordPress PHPCS ruleset ([#329]), props @apermo
- Improve PHPCS WordPress compliance ([#330])
- Post Cache Admin: Only show subscription author ([#333])
- Add automatic-status menu slug ([#334])
- Fix the all postids query ([#335])
- Don't define a wp-editor dependency ([#332])

[#355]: https://github.com/akirk/friends/pull/355
[#354]: https://github.com/akirk/friends/pull/354
[#353]: https://github.com/akirk/friends/pull/353
[#349]: https://github.com/akirk/friends/pull/349
[#352]: https://github.com/akirk/friends/pull/352
[#351]: https://github.com/akirk/friends/pull/351
[#345]: https://github.com/akirk/friends/pull/345
[#346]: https://github.com/akirk/friends/pull/346
[#347]: https://github.com/akirk/friends/pull/347
[#348]: https://github.com/akirk/friends/pull/348
[#339]: https://github.com/akirk/friends/pull/339
[#324]: https://github.com/akirk/friends/pull/324
[#325]: https://github.com/akirk/friends/pull/325
[#329]: https://github.com/akirk/friends/pull/329
[#330]: https://github.com/akirk/friends/pull/330
[#333]: https://github.com/akirk/friends/pull/333
[#334]: https://github.com/akirk/friends/pull/334
[#335]: https://github.com/akirk/friends/pull/335
[#332]: https://github.com/akirk/friends/pull/332
