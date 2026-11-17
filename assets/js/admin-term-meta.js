(function ($) {
  'use strict';

  function initColorPickers() {
    $('.wcvs-color-picker').each(function () {
      if (!$(this).hasClass('wp-color-picker')) {
        $(this).wpColorPicker();
      }
    });
  }

  $(document).ready(function () {
    initColorPickers();
  });

  // Reset Add-Term form controls after WordPress AJAX term creation.
  $(document).ajaxComplete(function (_event, _xhr, settings) {
    if (settings && typeof settings.data === 'string' && settings.data.indexOf('action=add-tag') !== -1) {
      $('#addtag .wcvs-color-picker').val('').trigger('change');
      $('#addtag .wcvs-term-image-id').val('');
      $('#addtag .wcvs-term-image-preview').empty();
      $('#addtag .wcvs-remove-term-image').hide();
    }
  });

  $(document).on('click', '.wcvs-upload-term-image', function (e) {
    e.preventDefault();
    const $wrap = $(this).closest('.wcvs-term-image-wrap');
    const frame = wp.media({
      title: 'Select Swatch Image',
      button: { text: 'Use Image' },
      multiple: false,
      library: { type: 'image' },
    });

    frame.on('select', function () {
      const attachment = frame.state().get('selection').first().toJSON();
      const thumbUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
      $wrap.find('.wcvs-term-image-id').val(attachment.id);
      $wrap.find('.wcvs-term-image-preview').html(
        `<img src="${thumbUrl}" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px;" />`
      );
      $wrap.find('.wcvs-remove-term-image').show();
    });

    frame.open();
  });

  $(document).on('click', '.wcvs-remove-term-image', function (e) {
    e.preventDefault();
    const $wrap = $(this).closest('.wcvs-term-image-wrap');
    $wrap.find('.wcvs-term-image-id').val('');
    $wrap.find('.wcvs-term-image-preview').empty();
    $(this).hide();
  });
})(jQuery);
