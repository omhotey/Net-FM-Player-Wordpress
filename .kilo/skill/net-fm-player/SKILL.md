---
name: net-fm-player
description: Develop and review the NET FM Player WordPress, Elementor, AzuraCast, and direct-stream plugin safely.
---

# NET FM Player Skill

Use this skill for changes to the NET FM Player plugin.

## Architecture

- `net-fm-player.php` owns the plugin lifecycle, settings, shortcode attributes, JSON configuration, and asset handles.
- `includes/class-net-fm-elementor-widget.php` exposes equivalent local controls in Elementor and delegates output to the shortcode.
- `assets/net-fm-player.js` initializes each `[data-nfp]` root once, manages `<audio>`, polls AzuraCast metadata, and handles direct-stream fallback.
- `assets/net-fm-player.css` must remain isolated under `.nfp`.

## Change Protocol

1. Locate every stage of a changed setting: default, sanitization, shortcode, widget control, rendered config, and browser use.
2. Prefer core WordPress APIs and browser APIs. Do not add a dependency or build system without explicit approval.
3. Escape output by context: `esc_html`, `esc_attr`, `esc_url`; sanitize saved input before it is persisted.
4. Preserve audio fallback and a useful UI if metadata requests or CORS fail.
5. Retain native buttons and range input semantics; update accessible names when the audio state changes.
6. Validate PHP syntax and `git diff --check` after editing.

## Manual Runtime Matrix

- Shortcode with global settings.
- Shortcode with all local overrides.
- Elementor widget using global settings.
- Elementor widget using local settings.
- Direct stream with no metadata API.
- AzuraCast metadata success and unavailable metadata.
- Primary/secondary streams, mute, volume, reconnection, desktop, and mobile viewport.
