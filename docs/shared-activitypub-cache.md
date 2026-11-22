# Shared ActivityPub cache prototype

This draft lets Friends display ActivityPub's cached posts without making another
Friends copy of an object ActivityPub has already stored for the site's actor.
Historical Friends rows remain intact. RSS and unsupported ingestion continue
using Friends' cache.

The prototype requires the ActivityPub Reader and an additional site option:

```sh
wp --url=alex.kirk.at option update friends_activitypub_reader_cache 1
```

Use a test installation while reviewing the transition. Set the option to `0` to
return to the existing queries. This PR does not enable the option or migrate rows.

Friends fetches bounded candidates from each store using the same date/ID cursor,
merges them in publication order, and then slices the visible page. Reader posts
are restricted to the authenticated actor's recipients and actors linked to a
Friends subscription. Matching historical GUIDs are excluded before limiting the
Friends source. The lookup is case-sensitive. The shared home query also supports
EMA; public/local timelines retain their own source rules.

New shared items gain Friends subscription/feed relationships, so the existing
UI can find their author. ActivityPub retains ownership of content and recipient
metadata. Incoming requests can use the shared object without a logged-in browser
session. A failed ActivityPub cache lookup leaves Friends ingestion in place.

## Before enabling this by default

- Represent Announce provenance independently of the cached original object.
  This draft retains legacy boost ingestion; merging currently prefers the AP
  original when both caches have exactly the same GUID.
- Complete outgoing reaction/Undo delegation and verify every reaction button.
- Map the remaining format/tag views and backfill subscription relationships on
  reader posts cached before the bridge was enabled.
- Preserve hidden-subscription preferences in the AP query, which has no local
  WordPress `post_author` corresponding to that subscription.
- Deduplicate all historical GUID escaping variants, and define cache-retention
  behavior for objects referenced by Friends reactions/bookmarks.
- Verify all themes and browser clients. No historical IDs are converted here;
  any eventual consolidation needs to preserve comment and client references.
