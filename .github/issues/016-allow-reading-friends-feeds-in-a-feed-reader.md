# [Allow reading friends feeds in a feed reader](https://github.com/akirk/friends/issues/16)

> state: **closed** opened by: **akirk** on: **2018-05-28T08:18:51Z**

We can do this by providing a download for a OPML file.

### Comments

---
> from: [**akirk**](https://github.com/akirk/friends/issues/16#issuecomment-396063744) on: **2018-06-10T16:51:47Z**

Implemented in 0.7
---
> from: [**chrisaldrich**](https://github.com/akirk/friends/issues/16#issuecomment-396463911) on: **2018-06-12T04:42:38Z**

Perhaps better than allowing simply the download of an OPML file, it would be nice to have the ability to provide a link to an OPML file as well? This way feed readers like Inoreader that support [OPML subscription](https://blog.inoreader.com/2014/05/opml-subscriptions.html) would be able to auto update themselves when new feeds/friends are added to the list?

Depending on your infrastructure, you could potentially leverage the old [Link Manager](https://codex.wordpress.org/Links_Manager) within WordPress which provided OPML outputs as well as [outputs arranged by category](https://boffosocko.com/2017/11/13/opml-files-for-categories-within-wordpresss-links-manager/#OPML%20files%20by%20category). This would prevent you from needing to rebuild the side-files unless you’re doing that already.

(Originally published at: https://boffosocko.com/2018/06/11/reply-to-allow-reading-friends-feeds-in-a-feed-reader/)
