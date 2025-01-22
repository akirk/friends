### 3.3.1
- Fix some styling issues ([#437])
- Fix FediPress installation ([#435])

### 3.3.0
- Styling Overhaul! ([#431])
- Add the FediPress theme ([#433])
- Incoming Feed Items: Fix in-article hash links ([#426])
- Add more functions for a browser extension ([#427])
- Browser Extension: API Key per user ([#429])
- Fix parsing Pixelfed's Image attachments ([#430])
- Add the friend's avatar to the page header ([#422])
- Add inline follow link ([#432])
- Log the newly supported ActivityPub events to the Friends Log ([#423])
- Augment the ActivityPub New Follower E-Mail ([#434])

### 3.2.3
- ActivityPub: Support update of posts and people ([#421])
- Add support for ActivityPub Move activity ([#420])
- Make tagged Friend Posts accessible ([#419])
- Uninstall: Delete more taxonomy entries ([#415])
- Standardize REST Error messages ([#413])
- Use the ActivityPub blog user as an actor if set to blog profile only ([#411])
- Add a Duplicate Remover ([#409])

### 3.2.2
- Move permissions checks into a dedicated permission_callback ([#408])
- Add more checks around friendships ([#407])

Hoping that this hardening will bring back the plugin to the WordPress.org directory after [this issue](https://www.wordfence.com/threat-intel/vulnerabilities/wordpress-plugins/friends/friends-321-missing-authorization) was reported. While I am unsure it qualified to get the plugin taken down, I've done some hardening and bugfixing in the above pull requests. Unfortunately it was not reported in a way that it could be patched in time. If you have a security issue to report, please follow the instructions on [https://github.com/akirk/friends/blob/main/SECURITY.md](https://github.com/akirk/friends/blob/main/SECURITY.md) and/or report through [https://github.com/akirk/friends/security](https://github.com/akirk/friends/security).

### 3.2.1
- OPML Import: Support OPMLs without nesting ([#403])

### 3.2.0
- Improve Translate Live compatibility ([#401])
- Fix blog follower count in sidebar ([#400])

### 3.1.9
- Fix bug with loading the main theme ([#398])

### 3.1.8
- Fix missing JavaScript on the frontend ([#396])

### 3.1.7
- Add a theme selector ([#393])
- Followers: Add Support for ActivityPub plugins blog profile ([#394])
- Generate the suggested user login from the display name ([#395])

### 3.1.6
- Site Health: Check if the cron job is enabled ([#391])
- Fix starring of a friend ([#392])
- Layout improvements props @liviagouvea in ([#384])

### 3.1.5
- Fix next page articles attached in the wrong place ([#388])
- Allow an extra redirect when discovering feeds ([#389])

### 3.1.4
- Fix Warning:  Undefined variable $account ([#385])
- Fixes for Friend Messages ([#387])
- Add Podcast Support ([#386])

### 3.1.3
- Add AJAX refreshing of feeds ([#382])
- Fix Fatal in the MF2 library ([#381])

### 3.1.2
- Fix support for threads.net ([#378])
- Add a warning if a user has not enabled ActivityPub on their threads.net account ([#377])
- Upgrade and improve the MF2 library ([#374])

### 3.1.1
- Improve Name Detection ([#372])
- Add a Button to the Welcome page ([#373])

### 3.1.0
- Add mark tag CSS to emails to ensure highlighting ([#365])
- Only show the dashboard widgets if the user has enough permissions ([#368])
- Prevent retrieving the same feed in parallel ([#366])
- Add Friend: Use more info from the given URL ([#369])
- Log ActivityPub actions and add the publish date to Announcements ([#364])
- Improve OPML Support ([#370])
- Update blueprints for previewing in WordPress Playground ([#371])

### 3.0.0
- Show Mutual Followers and allow removing of followers ([#359])
- Add an e-mail notification for new and lost followers ([#358])
- Add the ability to disable notifications per post format and feed parser ([#357])
- Fix 404 on the New private post widget props @liviacarolgouvea ([#361])
- Improve ghost.org ActivityPub compatibility ([#356])

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

### 2.9.3
- Fix Rewrite for Categories ([#322])
- Show Comments and the Form on Permalink pages ([#321])
- Don't allow to follow any local URL to prevent loops ([#320])
- Enable Mastodon Apps Updates: Cache the post when replying to it ([#318]), Map back the canonical user id to a user ([#317])
- Allow a dot in the ActivityPub username ([#316])

### 2.9.2
- Fix Friend Request notification setting saving ([#313])
- Add a Stats Widget ([#297])
- Enable Mastodon Apps: Improve virtual user handling ([#310])
- Increase cron feed polling frequency - this doesn't poll the feeds more often, just increases its reliability ([#312])
- Add my-apps icon ([#308])
- Enable Mastodon Apps CloudFest Hackathon Compatibility Updates ([#298])
- Fix Notification Author and Highlight a Matched Keyword ([#306])

### 2.9.1
- Fix the reblog title ([#300])

### 2.9.0
- Fix issues discovered by the plugin check plugin ([#301])

### 2.8.9
- One more fix to the update routine for previous version

### 2.8.8
- Fix update routine for previous version

### 2.8.7
- Fix the reblog button ([#299])
- Add local users to potential mentions so that it won't be trashed ([#296])
- Add Option to Disable Friendships ([#294])
- Improve Omnibox ([#292])
- Send the ActivityPub follow not to the shared inbox but the individuals inbox ([#291])

### 2.8.6
- Improve friend URL validation before trying to discover feeds ([#290])

### 2.8.5
- Add Boosting any URL and improve search field ([#287])
- Allow translator translation for the welcome box ([#286])
- Remove dysfunct double setting from Edit User Page ([#289])

### 2.8.4
- Fix adding new feeds with a Mastodon-style notation ([#282])

### 2.8.3
- Unfortunately we had to disable Gutenberg on the frontend ([#273])
- Fixed a missing reblog button for virtual users ([#261])
- Allow segmentation of friends frontpages ([#272]), see this experiment: https://github.com/akirk/friends-mastodon-like-interface
- Allow double clicking the text to collapse/uncollapse ([#263])
- Fix that the friends email address was displayed wrong ([#266])
- Fix external auth on multisite ([#267])
- Translatability fixes ([#265])
- Frontend: Allow future post status ([#270])
- Improve error checking regarding post formats and missing ActivityPub metadata ([#271])


### 2.8.2
- Fix crash when using get_the_excerpt in the admin edit-post screen ([#260])

### 2.8.1
- Fix broken outgoing activities with version 1.0 of the ActivityPub plugin ([#258])

### 2.8.0
- Fix invalid callback

### 2.7.9
- Fix broken replies with version 1.0 of the ActivityPub plugin ([#257])

### 2.7.8
- Fix some incompatibilities with version 1.0 of the ActivityPub plugin ([#256])

### 2.7.7
- No longer send your own comments out via ActivityPub, should be moved into the ActivityPub plugin ([#255])
- Hide block options for ClassicPress ([#252])
- Change feed table from horizontal to vertical ([#248])

### 2.7.6
- Added an option to enable/disable Gutenberg on the frontend ([#245])
- Fixed that a faulty "hide from friends page" value could cause missing posts in the main feed
- Change so that the [Send to E-Reader plugin] can enable downloading the ePub from a special URL

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

[#437]: https://github.com/akirk/friends/pull/437
[#435]: https://github.com/akirk/friends/pull/435
[#426]: https://github.com/akirk/friends/pull/426
[#427]: https://github.com/akirk/friends/pull/427
[#429]: https://github.com/akirk/friends/pull/429
[#430]: https://github.com/akirk/friends/pull/430
[#431]: https://github.com/akirk/friends/pull/431
[#422]: https://github.com/akirk/friends/pull/422
[#432]: https://github.com/akirk/friends/pull/432
[#433]: https://github.com/akirk/friends/pull/433
[#423]: https://github.com/akirk/friends/pull/423
[#434]: https://github.com/akirk/friends/pull/434
[#421]: https://github.com/akirk/friends/pull/421
[#420]: https://github.com/akirk/friends/pull/420
[#419]: https://github.com/akirk/friends/pull/419
[#415]: https://github.com/akirk/friends/pull/415
[#413]: https://github.com/akirk/friends/pull/413
[#411]: https://github.com/akirk/friends/pull/411
[#408]: https://github.com/akirk/friends/pull/408
[#407]: https://github.com/akirk/friends/pull/407
[#403]: https://github.com/akirk/friends/pull/403
[#401]: https://github.com/akirk/friends/pull/401
[#400]: https://github.com/akirk/friends/pull/400
[#398]: https://github.com/akirk/friends/pull/398
[#396]: https://github.com/akirk/friends/pull/396
[#395]: https://github.com/akirk/friends/pull/395
[#394]: https://github.com/akirk/friends/pull/394
[#393]: https://github.com/akirk/friends/pull/393
[#392]: https://github.com/akirk/friends/pull/392
[#391]: https://github.com/akirk/friends/pull/391
[#389]: https://github.com/akirk/friends/pull/389
[#388]: https://github.com/akirk/friends/pull/388
[#387]: https://github.com/akirk/friends/pull/387
[#386]: https://github.com/akirk/friends/pull/386
[#385]: https://github.com/akirk/friends/pull/385
[#384]: https://github.com/akirk/friends/pull/384
[#382]: https://github.com/akirk/friends/pull/382
[#381]: https://github.com/akirk/friends/pull/381
[#378]: https://github.com/akirk/friends/pull/378
[#377]: https://github.com/akirk/friends/pull/377
[#374]: https://github.com/akirk/friends/pull/374
[#372]: https://github.com/akirk/friends/pull/372
[#373]: https://github.com/akirk/friends/pull/373
[#365]: https://github.com/akirk/friends/pull/365
[#368]: https://github.com/akirk/friends/pull/368
[#366]: https://github.com/akirk/friends/pull/366
[#369]: https://github.com/akirk/friends/pull/369
[#364]: https://github.com/akirk/friends/pull/364
[#370]: https://github.com/akirk/friends/pull/370
[#371]: https://github.com/akirk/friends/pull/371
[#359]: https://github.com/akirk/friends/pull/359
[#358]: https://github.com/akirk/friends/pull/358
[#357]: https://github.com/akirk/friends/pull/357
[#361]: https://github.com/akirk/friends/pull/361
[#356]: https://github.com/akirk/friends/pull/356
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
[#322]: https://github.com/akirk/friends/pull/322
[#321]: https://github.com/akirk/friends/pull/321
[#320]: https://github.com/akirk/friends/pull/320
[#318]: https://github.com/akirk/friends/pull/318
[#317]: https://github.com/akirk/friends/pull/317
[#316]: https://github.com/akirk/friends/pull/316
[#313]: https://github.com/akirk/friends/pull/313
[#297]: https://github.com/akirk/friends/pull/297
[#310]: https://github.com/akirk/friends/pull/310
[#312]: https://github.com/akirk/friends/pull/312
[#308]: https://github.com/akirk/friends/pull/308
[#298]: https://github.com/akirk/friends/pull/298
[#306]: https://github.com/akirk/friends/pull/306
[#301]: https://github.com/akirk/friends/pull/301
[#300]: https://github.com/akirk/friends/pull/300
[#290]: https://github.com/akirk/friends/pull/290
[#287]: https://github.com/akirk/friends/pull/287
[#286]: https://github.com/akirk/friends/pull/286
[#289]: https://github.com/akirk/friends/pull/289
[#282]: https://github.com/akirk/friends/pull/282
[#273]: https://github.com/akirk/friends/pull/273
[#261]: https://github.com/akirk/friends/pull/261
[#272]: https://github.com/akirk/friends/pull/272
[#263]: https://github.com/akirk/friends/pull/263
[#266]: https://github.com/akirk/friends/pull/266
[#267]: https://github.com/akirk/friends/pull/267
[#265]: https://github.com/akirk/friends/pull/265
[#270]: https://github.com/akirk/friends/pull/270
[#271]: https://github.com/akirk/friends/pull/271
[#260]: https://github.com/akirk/friends/pull/260
[#258]: https://github.com/akirk/friends/pull/258
[#257]: https://github.com/akirk/friends/pull/257
[#256]: https://github.com/akirk/friends/pull/256
[#255]: https://github.com/akirk/friends/pull/255
[#252]: https://github.com/akirk/friends/pull/252
[#248]: https://github.com/akirk/friends/pull/248
[#245]: https://github.com/akirk/friends/pull/245
[#243]: https://github.com/akirk/friends/pull/243
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
[Send to E-Reader plugin]: https://github.com/akirk/friends-send-to-e-reader
