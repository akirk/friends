# Release Process

## How to release

1. Go to [Actions → Prepare Release](https://github.com/akirk/friends/actions/workflows/release-prep.yml)
2. Click **Run workflow**, enter the version number (e.g. `4.0.4`), and run it
3. The workflow creates a PR with the version bump and compiled changelog
4. Review and merge the PR
5. [Create a GitHub release](https://github.com/akirk/friends/releases/new) with the version as the tag, targeting `main`
6. Approve the deployment in the `production` environment
7. The `deploy.yml` workflow deploys to WordPress.org

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
