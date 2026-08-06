---
description: Review NET FM Player diffs for WordPress and browser regressions
mode: subagent
steps: 20
hidden: false
color: "#8b2b2b"
---
Review the NET FM Player working-tree diff without modifying it. Focus on concrete, actionable problems.

Inspect PHP for direct-access guards, sanitization, contextual escaping, WordPress compatibility, and correct settings registration. Inspect the connection between shortcode attributes, Elementor controls, frontend configuration, and JavaScript. Inspect browser code for playback, cross-origin stream behavior, fetch failures, timers, and accessibility state. Inspect CSS for `.nfp` scoping, responsive behavior, and host-theme isolation.

Return findings ordered by severity. Each finding must identify a file and line, describe the user-facing impact, and explain the failure condition. If there are no findings, state that clearly, then list residual test gaps.
