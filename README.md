# Friends

Your own WordPress at the center of your online activity. Follow friends and other websites and establish friendship relationships between blogs.

**Contributors:** akirk
**Tags:** friends, rss, decentralized, social-network, own-your-data
**Requires at least:** 5.0
**Tested up to:** 5.9
**Requires PHP:** 5.2.4
**License:** [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)
**Stable tag:** trunk

## Description

With the Friends Plugin for WordPress you can now consume content your friends (or other blogs) create, and interact with your friends on their blogs with seamless authentication.

As soon as you become friends, both of you get accounts on each other’s WordPresses that you can then use post comments or read private posts. You’ll use the account on your friend’s server just by clicking on their post on your own Friends page.

You can also use the Friends plugin as a capable self-hosted feed reader. With added parser support through plugins you can subscribe to all sorts of content, also on other social networks, allowing you to see what your friends do across social network borders.

A “friend” in the Friends Plugin doesn’t need to be a real friend, you can also subscribe to any site you like and that provides a viable means for retrieving your content.

You can turn your favorite blog into your personal newsletter by receiving full-post notification e-mails, using feed rules to filter out content you are not interested in.

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

1. You can use it like a Feed Reader
2. But it is centered around users; you can have multiple feeds per person, even on social networks (parsing capabilities provided by plugins)
3. Extensible with plugins itself
4. Use the customizer to adapt it to your liking
5. Categorize incoming content with Post Formats and view all posts of a certain format across your friends
6. Use rules to filter incoming content (sometimes you’re not interested in everything your friends do)
7. Friends users are plain WordPress users with low privileges
8. A Friend Request is accepted in the users screen. Delete the user to reject it or accept the request to make them a friend
9. You can react to a post or share it using Post Collections.

## Changelog

### 1.9.0
- New feature: Keyword Matcher (get a notification if an incoming post contains keywords you can specify)
- New feature: Automatic status posts (automatically create draft status posts for certain events, they need to be published manually)

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
