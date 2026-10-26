/* Sepo Forms — the "Sepo form" and "Sepo forms list" blocks. No build step. */
(function (wp, cfg) {
  if (!wp || !wp.blocks) { return; }
  var el = wp.element.createElement;
  var __ = wp.i18n.__;
  var be = wp.blockEditor || wp.editor;
  var C = wp.components;
  var SSR = wp.serverSideRender;
  var forms = (cfg && cfg.forms) || [];

  function preview(name, attrs, props) {
    return el('div', props, SSR ? el(SSR, { block: name, attributes: attrs }) : null);
  }

  wp.blocks.registerBlockType('sepo/form', {
    apiVersion: 2,
    title: __('Sepo form', 'sepo-forms'),
    description: __('Shows one of your forms.', 'sepo-forms'),
    icon: 'feedback',
    category: 'widgets',
    keywords: [__('form', 'sepo-forms'), __('survey', 'sepo-forms'), __('feedback', 'sepo-forms')],
    attributes: { formId: { type: 'number', default: 0 }, showTitle: { type: 'boolean', default: true } },
    edit: function (props) {
      var a = props.attributes;
      var bp = be.useBlockProps ? be.useBlockProps() : {};
      var options = [{ value: 0, label: __('Choose a form…', 'sepo-forms') }].concat(forms);
      var controls = el(be.InspectorControls, null,
        el(C.PanelBody, { title: __('Form', 'sepo-forms'), initialOpen: true },
          el(C.SelectControl, { label: __('Form', 'sepo-forms'), value: a.formId, options: options, onChange: function (v) { props.setAttributes({ formId: parseInt(v, 10) || 0 }); } }),
          el(C.ToggleControl, { label: __('Show the form title', 'sepo-forms'), checked: !!a.showTitle, onChange: function (v) { props.setAttributes({ showTitle: v }); } })
        )
      );
      if (!a.formId) {
        return el('div', bp, controls, el(C.Placeholder, { icon: 'feedback', label: __('Sepo form', 'sepo-forms'), instructions: forms.length ? __('Choose the form to show.', 'sepo-forms') : __('Create a form under Forms → Add form first.', 'sepo-forms') },
          forms.length ? el(C.SelectControl, { value: 0, options: options, onChange: function (v) { props.setAttributes({ formId: parseInt(v, 10) || 0 }); } }) : null));
      }
      return el('div', bp, controls, preview('sepo/form', a, { style: { pointerEvents: 'none' } }));
    },
    save: function () { return null; }
  });

  wp.blocks.registerBlockType('sepo/forms-list', {
    apiVersion: 2,
    title: __('Sepo forms list', 'sepo-forms'),
    description: __('Lists every form, with search and pages.', 'sepo-forms'),
    icon: 'list-view',
    category: 'widgets',
    attributes: { perPage: { type: 'number', default: 12 }, view: { type: 'string', default: 'grid' } },
    edit: function (props) {
      var a = props.attributes;
      var bp = be.useBlockProps ? be.useBlockProps() : {};
      return el('div', bp,
        el(be.InspectorControls, null,
          el(C.PanelBody, { title: __('List', 'sepo-forms'), initialOpen: true },
            el(C.RangeControl, { label: __('Forms per page', 'sepo-forms'), value: a.perPage, min: 3, max: 48, onChange: function (v) { props.setAttributes({ perPage: v }); } }),
            el(C.SelectControl, { label: __('Layout', 'sepo-forms'), value: a.view, options: [{ value: 'grid', label: __('Grid', 'sepo-forms') }, { value: 'list', label: __('List', 'sepo-forms') }], onChange: function (v) { props.setAttributes({ view: v }); } })
          )
        ),
        preview('sepo/forms-list', a, { style: { pointerEvents: 'none' } })
      );
    },
    save: function () { return null; }
  });
})(window.wp, window.SepoFormsBlocks);
