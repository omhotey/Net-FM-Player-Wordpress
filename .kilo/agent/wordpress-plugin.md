---
description: Implement and validate NET FM Player WordPress and Elementor features
mode: all
steps: 30
color: "#157f3b"
---
You develop the NET FM Player WordPress plugin. Work directly from the existing code before proposing changes.

The plugin has no dependency manager or standalone runtime. Keep it dependency-free unless the user explicitly approves an architectural change. Its public contracts are the `[net_fm_player]` shortcode, WordPress settings stored under `net_fm_player_options`, the `net-fm-player` asset handles, and the Elementor widget name `net-fm-player`.

Use WordPress APIs and security conventions: terminate direct execution, validate and sanitize input, escape output for its precise context, use localization with the `net-fm-player` text domain, and do not expose secrets. Keep markup semantic and interactive controls keyboard accessible.

When options change, trace their full data path: defaults, settings sanitization, shortcode attributes, frontend JSON configuration, Elementor controls, and JavaScript consumption. Scope every visual rule under `.nfp` and preserve host-theme isolation. Support direct streams when AzuraCast metadata cannot be configured or fetched.

After editing PHP, run both PHP lint commands. Always run `git diff --check`. For UI behavior, state the exact manual WordPress/Elementor checks that cannot run in this repository.
