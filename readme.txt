=== Friends ===
Contributors: akirk
Tags: friends, rss, decentralized, social-network, oyd, own-your-data
Requires at least: 5.0
Tested up to: 5.7
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: trunk

Use your WordPress to follow your friends (and other websites) and establish friendship relationships with friends who also run the Friends plugin. Consume content inside WordPress and comment at your friends with auto-authentication.

== Description ==

With the Friends Plugin for WordPress you can now consume content your friends (or other blogs) create, and interact with your friends on their blogs with seamless authentication.

As soon as you become friends, both of you get accounts on each other’s WordPresses that you can then use post comments or read private posts. You’ll use the account on your friend’s server just by clicking on their post on your own Friends page.

You can also use the Friends plugin as a capable self-hosted feed reader. With added parser support through plugins you can subscribe to all sorts of content, also on other social networks, allowing you to see what your friends do across social network borders.

A “friend” in the Friends Plugin doesn’t need to be a real friend, you can also subscribe to any site you like and that provides a viable means for retrieving your content.

You can turn your favorite blog into your personal newsletter by receiving full-post notification e-mails, using feed rules to filter out content you are not interested in.

https://www.youtube.com/watch?v=4bz6GluXnsk

=== Philosophy ===

The Friends Plugin was built to make use of what WordPress provides:

- You use the WordPress infrastructure (Gutenberg or Classic Editor, what you prefer) to create your posts.
- If a post is private, only logged-in friends can see it. They can only log in through their own Friends plugin on their blog.
- Therefore, your friend is just a user on your WordPress blog, their posts are theirs, you can delete them to unfriend them.
- No extra tables: The Friends plugin just uses a post type, options and some taxonomies to store its data. When you delete the plugin, your WordPress will be slim like before.

In future, I could see mobile apps instead of talking to a third party, to talk to your own blog. It will have your friends' posts already fetched. Maybe the apps will be specialized, like Twitter or Instagram, where you'd only interact with and create posts in the specific post format.

The logo was created by Ramon Dodd, @ramonopoly. Thank you!

== Installation ==
 
1. Upload the `friends` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
 
== Frequently Asked Questions ==
 
= Does this plugin create custom tables? =
No, all the functionality is achieved with standard WordPress means. Subscriptions or Friends are minimal-permission users on your install. External posts are cached in a custom post types and attributed to those users.

= Why does this create users on my WordPress install? =
I believe this is a very elegant way to attribute content and it allows to delete the users content when you delete them. The users have minimal privileges, so they cannot be used to post actual content to your site.

The users can only be used for login through your specific friend's WordPress install (they are created with a strong password throw-away password), if they have been upgraded to a "friend" or "aquaintance" user.

= Why is the friendship established between WordPress sites and not WordPress users? =
For one, this allows to stick with established WordPress configurations and terminologies. For example, you can use the WordPress mobile apps to post privately to your site.

Secondly, a lot of WordPresses are like cell phones. Some are used by more than one person but mostly there is a 1:1 relationship between a WordPress blog and a person.

If someone has multiple WordPresses this actually allows to segment your friendships. Close friends might want to follow all your blogs but you'd only add your photographer friends to your photoblog.

= What if the friend request is deleted or not accepted? =
You'll still see the public posts from the other WordPress, you've subscribed to its public RSS feed.

= What's the point? If I want to post something privately I can use Facebook. =
Well, that's actually exactly the point. Facebook owns your data, with WordPress you can decide where you want to host it and have all the benefits of running open source software.

= What happens if I modify or delete a post? =
There is a cache of your friends post in form of a Custom Post Type friend_post that is updated when you change a post. When you delete a post your friends' WordPresses are notified and they delete the cached post.

== Screenshots ==

1. You can use it like a Feed Reader
2. But it is centered around users; you can have multiple feeds per person, even on social networks (parsing capabilities provided by plugins)
3. Extensible with plugins itself
4. Use the customizer to adapt it to your liking
5. Categorize incoming content with Post Formats and view all posts of a certain format across your friends
6. Use rules to filter incoming content (sometimes you’re not interested in everything your friends do)
7. Friends users are plain WordPress users with low privileges
8. A Friend Request is accepted in the users screen. Delete the user to reject it or accept the request to make them a friend
 
== Changelog ==
= 1.5.4 =
- Improve rules screen
- Allow untrashing of posts (that have been autotrashed by rules)

= 1.5.3 =
- Update Gutenberg blocks structure

= 1.5.2 =
- Allow editing the friends description
- Display feed rules in author header
- Show author header on single post view

= 1.5.1 =
- Improved the author header a bit more, updated the page title on the friends page
- Added some filters for the Post Collection plugin.
- Incorporate Friends Plugins strings in the main plugin to have them translatable on translate.wordpress.org.

= 1.5 =
- Show an info header for a specific friends when viewing all their posts

= 1.4.4 =
- Autocomplete friends via search
- Infinite scroll on your friends page

= 1.4 =
- Versions 1.2 and 1.3 were skipped because of semantic versioning mistake
- Removed author pages for friend users
- Added some actions for futher friend plugins and improved the modifiability of feed items through hooks

= 1.1 =
- Easier overloading of templates using the Gamajo Template Loader

= 1.0 =
- Support for multiple feeds per person
- Support for (parser) plugins
- Touch-up the Friends page appearance

= 0.20 =
- Revamp the friendship protocol, allow specifying a codeword and sending a message

= 0.16 =
- Bugfixes: Friend Request error handling, feed rules for author
- Add some help texts
- Bring back shortcodes

= 0.15 =
- Removed Saved Articles, this will go into the Thinkery plugin
- Subscriptions will use the site's favicon as user avatar
- Removed short codes and replaced them with Gutenberg blocks "Friend Posts" and "Friends List" Gutenberg, added a "Friends Visibility" global Gutenberg option

= 0.14 =
- Add Saved Articles that can be used to recommend articles to friends
- Extract article contents using Readability

= 0.13 =
- No longer use hardcoded WP REST API URLs
- Add bookmarklets

= 0.12 =
- Bugfix: don't notify about posts that are trashed via rules

= 0.11 =
- Add Restricted Friend functionality
- Add feed rules

= 0.10 =
- Disable listing users via REST
- Allow just subscribing to a site
- Add Gutenberg blocks

= 0.9 =
- Post recommendations
- Includes the most recent version of SimplePie

= 0.8 =
- Reactions
- Shortcodes
- Customizable Profile page

= 0.7 =
- Download OPML
- Suggest Friends plugin via different media

= 0.6 =
- Add friends settings

= 0.5 =
- Use in/out tokens.

= 0.4 =
- Send notifications for friend request, accepted request and new posts.
- Improve friend request security.
- Initial release on to the WordPress.org plugin directory.

= 0.3 =
- Delete your cached posts on friends blogs when you delete them.
- Widgets: Friend Requests, Friend List.
- Initial submission to the WordPress.org plugin directory.

= 0.2 =
- Subscribe to WordPresses without Friends plugin.

= 0.1 =
- All basic functionality:
- Request and accept friendships
- Subscribe to private feed
- /friends/ page 
