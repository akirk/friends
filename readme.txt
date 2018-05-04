=== Friends ===
Contributors: akirk
Tags: friends, private-posts, rss, private-blogging
Requires at least: 4.9
Tested up to: 5.0-alpha
Requires PHP: 5.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 
Connect WordPresses and communicate privately with your friends. You can send friend requests to your friends' blogs and see their posts aggregated.
 
== Description ==
 
On your WordPress site you can post anything you wish to share with the world. But blogging only becomes really interesting if you interact with other people.

With the Friends plugin, you can establish a connection with your friends' WordPress sites by making and accepting friend requests.

Let's look at an example:

Friend Request visualization

You want to connect with your friend Matt and send a friend request to his WordPress. He accepts the friend request–the connection is established.

Actually you're now subscribed to his posts and he's now subscribed to your posts, so on your /friends/ pages you'll both now see each others posts.

Fetch friend posts

No big deal, this is just like subscribing to an RSS feed, right? It actually is, and that's also what's happening behind the scenes.

Things start to get more interesting if you bring private posts to the game.

When you post something with the post status sent to "Private," while not visible to the random (logged-out) visitor of your site, it is shared with your friends.


This is the long description.  No limit, and you can use Markdown (as well as 

== Installation ==
 
1. Upload the `friends` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
 
== Frequently Asked Questions ==
 
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

1. An incoming Friend Request on the users page.
2. The friend request has been accepted.
3. On /friends/ your friends posts appear together with yours.
4. Access the most important features using the site menu.
 
== Changelog ==
= 0.3 =
- Delete your cached posts on friends blogs when you delete them.
- Widgets: Friend Requests, Friend List.
- Initial submission to the WordPress.org plugin directory.

= 0.2 =
- Subscribe to WordPresses without Friends plugin.

= 0.1 =
All basic functionality:
- Request and accept friendships
- Subscribe to private feed
- /friends/ page 