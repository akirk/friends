# Release Process

## How to release

1. Go to https://github.com/akirk/friends/releases
2. Click **Draft a new release**
3. Type the new version tag (e.g. `4.0.4`)
4. Target the `main` branch
5. Click **Generate release notes**, then **Publish release**

That's it. The `deploy.yml` workflow will automatically:
- Compile changelog entries from `.github/changelog/unreleased/` into `CHANGELOG.md` and `README.md`
- Bump the version in `friends.php` and `README.md`
- Commit and push to `main`
- Move the tag to the new commit
- Deploy to WordPress.org via SVN

## Version numbering

- **Patch** (4.0.3 → 4.0.4): bug fixes, small changes
- **Minor** (4.0.3 → 4.1.0): new features
- **Major** (4.0.3 → 5.0.0): breaking changes

## Local scripts (optional)

These scripts can also be run locally if needed:

- `bin/changelog-write.sh <version>` — compile `.github/changelog/unreleased/` entries into CHANGELOG.md and README.md
- `bin/version-bump.sh <version>` — update version in `friends.php` and `README.md`
- `bin/changelog-add.sh [PR-number]` — interactively create a changelog entry

## Setup

### Environment

The deploy workflow uses a `production` environment with required reviewers. Configure at: https://github.com/akirk/friends/settings/environments

1. Create an environment called `production`
2. Enable **Required reviewers** and add the users who can approve deploys
3. Optionally restrict to the `main` branch under **Deployment branches**

### Secrets

Add these as environment secrets on `production` (or as repository secrets):
- `SVN_USERNAME` — your WordPress.org username
- `SVN_PASSWORD` — your WordPress.org password
