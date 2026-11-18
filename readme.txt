=== NET FM Player for AzuraCast ===
Contributors: omhotey
Tags: radio, azuracast, audio, streaming, elementor
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AzuraCast and direct-stream radio player for WordPress.

== Description ==

For each station, configure stream URLs and their visible labels in the NET FM Player settings. For example, use 128 kbps and 320 kbps for two available streams. The secondary stream can also serve as fallback. Add only streams provided by the station.

NET FM Player provides a configurable radio player through the `[net_fm_player]` shortcode, the NET FM Player Gutenberg block, and an Elementor widget when Elementor is active.

Configure a station under Settings > NET FM Player. Vinyl is the default presentation; Studio remains available for existing embeds and new overrides.

Add additional stations under Settings > NET FM Player. Each saved station displays its ID. Use [net_fm_player station="station-id"] for one station, [net_fm_player stations="all"] for all saved stations, or [net_fm_player stations="station-id-1,station-id-2"] for selected stations. Gutenberg and Elementor offer the same station fields. Existing shortcodes without these fields retain the global settings.

The public read-only REST endpoint /wp-json/net-fm-player/v1/stations provides enabled station IDs, names, metadata, stream and logo URLs to compatible applications. Enter only public URLs without passwords or keys.

The plugin requests the configured AzuraCast Now Playing endpoint directly from the visitor's browser to display live metadata and artwork. The configured audio stream is played directly by the visitor's browser. Ensure that your streaming provider permits browser access and supplies appropriate CORS headers.

== Installation ==

1. Upload the plugin ZIP in Plugins > Add New > Upload Plugin.
2. Activate NET FM Player.
3. Configure station and stream URLs in Settings > NET FM Player.
4. Insert `[net_fm_player]`, the NET FM Player block, or the Elementor widget.

== Changelog ==

= 0.3.0 =
* Added station cards, public station catalog and station selection in the player.
* Added stream quality labels and bounded fallback to an available stream.
* Improved Gutenberg, Elementor and responsive player behavior.
* Keep track titles on one line with scrolling and offer copying the current track.
* Refresh metadata on play and ignore outdated metadata responses.

= 0.2.0 =
* Added the dynamic Gutenberg block.
* Made Vinyl the default theme for new installations.
* Added responsive vinyl player and Elementor parity improvements.
