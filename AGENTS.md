# NET FM Player Development Guide

## Project Scope

NET FM Player is a standalone WordPress plugin that renders an AzuraCast or direct-stream radio player through the `[net_fm_player]` shortcode and an Elementor widget.

Primary source files:

- `net-fm-player.php`: plugin bootstrap, settings page, shortcode markup, and asset registration.
- `includes/class-net-fm-elementor-widget.php`: Elementor controls and shortcode bridge.
- `assets/net-fm-player.js`: browser-side player behavior and AzuraCast metadata polling.
- `assets/net-fm-player.css`: isolated player presentation, using the `.nfp` namespace.

## Engineering Rules

- Keep the plugin dependency-free. Do not add Composer, npm, a build step, or a framework without an explicit product need.
- Support the WordPress versions declared by the plugin's future `Requires at least` header before using newer APIs.
- Guard direct PHP execution with `ABSPATH`; escape every value at its output context and sanitize saved settings.
- Preserve the `net-fm-player` text domain for all user-visible PHP strings.
- Prefix PHP classes, functions, option names, handles, CSS classes, JavaScript globals, and storage keys with `Net_Fm_Player`, `net_fm_player`, or `nfp` as appropriate.
- Keep CSS selectors scoped beneath `.nfp`. Do not alter host theme or Elementor styles.
- Treat stream URLs and metadata endpoints as untrusted. Maintain graceful fallback behavior when metadata or CORS is unavailable.
- Keep shortcode attributes, WordPress settings, Elementor controls, and browser configuration synchronized whenever a player option changes.
- Maintain keyboard-accessible native controls, accurate ARIA labels, and visible focus states when changing player UI.
- Avoid adding credentials, station-specific private endpoints, WordPress databases, generated archives, or local configuration to Git.

## Verification

Run the smallest relevant validation after edits:

```powershell
php -l net-fm-player.php
php -l includes/class-net-fm-elementor-widget.php
git diff --check
```

For browser-facing changes, manually test a shortcode and Elementor widget in a WordPress site with both a direct stream and an AzuraCast endpoint. Check play/pause, stream switching, mute, volume, metadata refresh, unavailable metadata, and narrow viewport behavior.

## Git Workflow

- Inspect `git status --short`, `git diff --check`, and the intended diff before committing.
- Keep commits focused and use conventional Russian or English commit messages consistent with repository history.
- Do not commit generated ZIPs, dependencies, IDE state, Agent Manager state, or secrets.
- Do not use `git reset --hard`, `git checkout --`, force pushes, or `git stash` to resolve worktree conflicts.
- Agent Manager worktrees are isolated branches. Resolve conflicts inside the worktree, validate there, then apply or merge deliberately.
