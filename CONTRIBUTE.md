# Contributing to Friends Plugin

Thank you for considering contributing to the Friends Plugin! We welcome contributions from the community and appreciate your interest in helping us improve our project.

## Table of Contents

- [Getting Started](#getting-started)
- [Coding Guidelines](#coding-guidelines)
- [Compiling SCSS](#compiling-scss)
- [Submitting Changes](#submitting-changes)
- [Reporting Issues](#reporting-issues)

## Getting Started

1. **Fork the repository**: Click the "Fork" button on the top right corner of this page.
2. **Clone your fork**:
   ```bash
   git clone https://github.com/your-username/friends.git
   ```
3. **Install Node.js dependencies**:
   Navigate to the project directory and run:
   ```bash
   npm install
   ```

## Running tests

`composer test` runs all PHPUnit suites using the existing WordPress test environment
and any installed sibling ActivityPub or Enable Mastodon Apps checkouts. It does not
install or change plugins. Tests requiring an unavailable integration remain skipped.

Use `composer test -- --testsuite default` for the general suite,
`composer test -- --testsuite combined-ema` for ActivityPub and EMA together, and
`composer test -- --testsuite only-ema` for EMA without the ActivityPub hooks.
Set `WP_MULTISITE=1` for a multisite run. Suite membership is defined in `phpunit.xml`.

## Coding Guidelines

- Follow the existing coding style in the project.
- Write clear, descriptive commit messages.
- Ensure your code is well-commented and easy to understand.

## Compiling SCSS

To compile the SCSS files, run the following command in your terminal:

```bash
npm run sass:compile
```

This will process the SCSS files and update the CSS output accordingly.

## Submitting Changes

1. **Create a new feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. **Make your changes**.
3. **Commit your changes**:
   ```bash
   git add .
   git commit -m "Add some feature"
   ```
4. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```
5. **Create a Pull Request**: Go to the original repository and click on the "New Pull Request" button. Select your branch and submit the pull request.

## Reporting Issues

If you encounter any bugs or have suggestions for improvement, please open an issue in this repository. Provide as much detail as possible, including steps to reproduce the issue.

