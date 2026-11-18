(function (blocks, blockEditor, components, element, i18n, serverSideRender) {
  var el = element.createElement;
  var InspectorControls = blockEditor.InspectorControls;
  var PanelBody = components.PanelBody;
  var SelectControl = components.SelectControl;
  var TextControl = components.TextControl;
  var ServerSideRender = serverSideRender.default || serverSideRender;
  var __ = i18n.__;

  blocks.registerBlockType('net-fm-player/player', {
    apiVersion: 3,
    title: __('NET FM Player', 'net-fm-player'),
    icon: 'playlist-audio',
    category: 'widgets',
    description: __('Radio player using the global NET FM settings.', 'net-fm-player'),
    attributes: {
      station: { type: 'string', default: '' },
      stations: { type: 'string', default: '' },
      theme: { type: 'string', default: 'inherit' },
      layout: { type: 'string', default: 'inherit' }
    },
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;

      return el(
        element.Fragment,
        {},
        el(
          InspectorControls,
          {},
          el(
            PanelBody,
            { title: __('Player appearance', 'net-fm-player'), initialOpen: true },
            el(TextControl, {
              label: __('Station ID', 'net-fm-player'),
              help: __('Copy the ID from NET FM Player settings. Leave empty for global settings.', 'net-fm-player'),
              value: attributes.station,
              onChange: function (station) { setAttributes({ station: station }); }
            }),
            el(TextControl, {
              label: __('Station IDs for selector', 'net-fm-player'),
              help: __('Enter all for all saved stations, or separate station IDs with commas. Overrides the single station ID.', 'net-fm-player'),
              value: attributes.stations,
              onChange: function (stations) { setAttributes({ stations: stations }); }
            }),
            el(SelectControl, {
              label: __('Theme', 'net-fm-player'),
              value: attributes.theme,
              options: [
                { label: __('Use global setting', 'net-fm-player'), value: 'inherit' },
                { label: __('Vinyl', 'net-fm-player'), value: 'vinyl' },
                { label: __('Studio', 'net-fm-player'), value: 'studio' }
              ],
              onChange: function (theme) { setAttributes({ theme: theme }); }
            }),
            el(SelectControl, {
              label: __('Layout', 'net-fm-player'),
              value: attributes.layout,
              options: [
                { label: __('Use global setting', 'net-fm-player'), value: 'inherit' },
                { label: __('Horizontal', 'net-fm-player'), value: 'horizontal' },
                { label: __('Vertical', 'net-fm-player'), value: 'vertical' }
              ],
              onChange: function (layout) { setAttributes({ layout: layout }); }
            })
          )
        ),
        el('div', blockEditor.useBlockProps(),
          el(ServerSideRender, {
            block: 'net-fm-player/player',
            attributes: attributes
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender);
