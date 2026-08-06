---
description: Validate the NET FM Player changes and fix regressions
agent: wordpress-plugin
---
Review the current working-tree changes for NET FM Player.

1. Run PHP syntax checks for `net-fm-player.php` and `includes/class-net-fm-elementor-widget.php`.
2. Run `git diff --check`.
3. Inspect changed PHP, JavaScript, and CSS for WordPress escaping/sanitization, shortcode-Elementor-setting consistency, scoped CSS, accessibility, and direct-stream/AzuraCast fallback behavior.
4. Fix only defects introduced by or present in the changed area.
5. Report executed checks, results, changed files, and any manual WordPress checks still required.
