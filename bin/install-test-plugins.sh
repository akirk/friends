#!/usr/bin/env bash
# Install integration dependencies without changing existing local checkouts.
set -euo pipefail

friends_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
plugin_name="${1:?Usage: install-test-plugins.sh activitypub|ema}"
case "$plugin_name" in
  activitypub)
    plugin_slug=activitypub
    plugin_file=activitypub.php
    plugin_repo=https://github.com/Automattic/wordpress-activitypub.git
    plugin_ref="${FRIENDS_TEST_ACTIVITYPUB_REF:-trunk}"
    if [[ -f "$friends_root/../wordpress-activitypub/$plugin_file" ]]; then
      exit 0
    fi
    ;;
  ema)
    plugin_slug=enable-mastodon-apps
    plugin_file=enable-mastodon-apps.php
    plugin_repo=https://github.com/akirk/enable-mastodon-apps.git
    plugin_ref="${FRIENDS_TEST_EMA_REF:-main}"
    ;;
  *)
    echo "Unknown integration dependency: $plugin_name" >&2
    exit 1
    ;;
esac

if [[ -f "$friends_root/../$plugin_slug/$plugin_file" ]]; then
  exit 0
fi
plugin_target="$friends_root/.test-plugins/$plugin_slug"
if [[ -f "$plugin_target/$plugin_file" ]]; then
  exit 0
fi
mkdir -p "$friends_root/.test-plugins"
git clone --depth 1 --branch "$plugin_ref" "$plugin_repo" "$plugin_target"
