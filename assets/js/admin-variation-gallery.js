(function ($) {
  'use strict';

  $(document).on('click', '.wcvs-add-variation-images', function (e) {
    e.preventDefault();
    const $box = $(this).closest('.wcvs-variation-gallery-box');
    const $list = $box.find('.wcvs-admin-gallery-list');
    const $input = $box.find('.wcvs-gallery-ids-input');

    const frame = wp.media({
      title: 'Select Variation Gallery Images',
      button: { text: 'Add to Variation Gallery' },
      multiple: true,
      library: { type: 'image' },
    });

    frame.on('select', function () {
      const selection = frame.state().get('selection').toJSON();
      const existingIds = $input.val() ? $input.val().split(',').map(Number) : [];

      selection.forEach((attachment) => {
        if (!existingIds.includes(attachment.id)) {
          existingIds.push(attachment.id);
          const thumb = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
          $list.append(`
            <li class="wcvs-admin-gallery-item" data-attachment-id="${attachment.id}">
              <img src="${thumb}" alt="" />
              <button type="button" class="wcvs-remove-gallery-img">&times;</button>
            </li>
          `);
        }
      });

      $input.val(existingIds.join(',')).trigger('change');
      $box.closest('.woocommerce_variation').addClass('variation-needs-update');
    });

    frame.open();
  });

  $(document).on('click', '.wcvs-remove-gallery-img', function (e) {
    e.preventDefault();
    const $item = $(this).closest('.wcvs-admin-gallery-item');
    const $box = $item.closest('.wcvs-variation-gallery-box');
    $item.remove();

    const updatedIds = $box.find('.wcvs-admin-gallery-item').map(function () {
      return $(this).data('attachment-id');
    }).get();

    $box.find('.wcvs-gallery-ids-input').val(updatedIds.join(',')).trigger('change');
    $box.closest('.woocommerce_variation').addClass('variation-needs-update');
  });
})(jQuery);
