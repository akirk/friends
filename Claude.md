# Claude Instructions for Friends Plugin

## WordPress Playground Testing

When working on this WordPress plugin, you can help users test changes by providing a WordPress Playground link that uses the current branch.

### How to Generate Playground Links

When you've made changes to the plugin and pushed to a branch, provide a WordPress Playground link in this format:

```
https://playground.wordpress.net/#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/friends%22,%22ref%22:%22BRANCH_NAME%22,%22refType%22:%22refname%22},%22options%22:{%22activate%22:true}}]}
```

Replace `BRANCH_NAME` with the actual branch name you're working on.

### Example

If working on branch `claude/add-feature-xyz`, the link would be:

```
https://playground.wordpress.net/#{%22steps%22:[{%22step%22:%22installPlugin%22,%22pluginData%22:{%22resource%22:%22git:directory%22,%22url%22:%22https://github.com/akirk/friends%22,%22ref%22:%22claude/add-feature-xyz%22,%22refType%22:%22refname%22},%22options%22:{%22activate%22:true}}]}
```

### When to Provide Links

- After completing a feature implementation
- After fixing a bug
- When the user requests to test changes
- After creating or updating a pull request

### Message Format

When providing the link, use this format:

```markdown
## Test These Changes in WordPress Playground

You can test the changes from branch `BRANCH_NAME` directly in WordPress Playground:

[Launch WordPress Playground](PLAYGROUND_URL)

This will install and activate the plugin with the changes from this branch.
```

### Notes

- The playground link uses the GitHub repository as the source
- The `installPlugin` step automatically installs and activates the plugin
- Users can test the changes without needing to set up a local WordPress environment
- This mirrors the functionality of the GitHub action in `.github/workflows/pr-playground-comment.yml` but uses branches instead of PR refs
