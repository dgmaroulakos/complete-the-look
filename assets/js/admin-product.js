/**
 * Complete the Look — product edit media uploader + color presets.
 */
(function ($) {
	'use strict';

	var frame;
	var frameTarget = null;

	function setDefaultPreview(attachmentId, url) {
		$('#ctm_look_image_id').val(attachmentId || '');

		if (url) {
			$('#ctm_look_image_preview')
				.html('<img src="' + url + '" alt="" />')
				.show();
			$('.ctm-look-image-remove[data-target="default"]').show();
			$('.ctm-look-image-upload[data-target="default"]').text(ctmAdminProduct.i18n.change);
		} else {
			$('#ctm_look_image_preview').empty().hide();
			$('.ctm-look-image-remove[data-target="default"]').hide();
			$('.ctm-look-image-upload[data-target="default"]').text(ctmAdminProduct.i18n.upload);
		}
	}

	function setPresetPreview($wrap, attachmentId, url) {
		$wrap.find('.ctm-color-preset-image-id').val(attachmentId || '');

		if (url) {
			$wrap
				.find('.ctm-color-preset-image-preview')
				.html('<img src="' + url + '" alt="" />')
				.show();
			$wrap.find('.ctm-look-image-remove[data-target="preset"]').show();
			$wrap.find('.ctm-look-image-upload[data-target="preset"]').text(ctmAdminProduct.i18n.change);
		} else {
			$wrap.find('.ctm-color-preset-image-preview').empty().hide();
			$wrap.find('.ctm-look-image-remove[data-target="preset"]').hide();
			$wrap.find('.ctm-look-image-upload[data-target="preset"]').text(ctmAdminProduct.i18n.upload);
		}
	}

	$(document).on('click', '.ctm-look-image-upload', function (e) {
		e.preventDefault();

		var $btn = $(this);
		frameTarget = {
			type: $btn.data('target') || 'default',
			$wrap: $btn.closest('.ctm-look-image-wrap')
		};

		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: ctmAdminProduct.i18n.title,
			button: { text: ctmAdminProduct.i18n.use },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url =
				(attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ||
				(attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) ||
				attachment.url;

			if (!frameTarget) {
				return;
			}

			if (frameTarget.type === 'preset') {
				setPresetPreview(frameTarget.$wrap, attachment.id, url);
			} else {
				setDefaultPreview(attachment.id, url);
			}
		});

		frame.open();
	});

	$(document).on('click', '.ctm-look-image-remove', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var target = $btn.data('target') || 'default';

		if (target === 'preset') {
			setPresetPreview($btn.closest('.ctm-look-image-wrap'), '', '');
		} else {
			setDefaultPreview('', '');
		}
	});

	function getSelectedProductIds() {
		var ids = $('#ctm_product_ids').val() || [];
		return ids.map(function (id) {
			return parseInt(id, 10);
		}).filter(Boolean);
	}

	function buildRowHtml(colorSlug, row) {
		var selectHtml = row.html || '';

		if (selectHtml.indexOf('<select') === 0) {
			selectHtml = selectHtml.replace(
				'<select class="ctm-color-preset__color-select">',
				'<select name="ctm_color_presets[' +
					colorSlug +
					'][defaults][' +
					row.product_id +
					']" class="ctm-color-preset__color-select">'
			);
		}

		return (
			'<div class="ctm-color-preset__row" data-product-id="' +
			row.product_id +
			'">' +
			'<span class="ctm-color-preset__product-name">' +
			$('<div/>').text(row.name).html() +
			'</span>' +
			selectHtml +
			'</div>'
		);
	}

	function refreshPresetProductRows() {
		var $presets = $('.ctm-color-presets');

		if (!$presets.length) {
			return;
		}

		var productIds = getSelectedProductIds();
		var mainColors = [];

		$presets.find('.ctm-color-preset').each(function () {
			mainColors.push($(this).data('color'));
		});

		if (!productIds.length) {
			$presets.find('.ctm-color-preset__products').html(
				'<p class="ctm-color-preset__empty">' + ctmAdminProduct.i18n.empty + '</p>'
			);
			return;
		}

		$.post(ctmAdminProduct.ajaxUrl, {
			action: 'ctm_get_product_color_options',
			nonce: ctmAdminProduct.nonce,
			product_ids: productIds,
			main_colors: mainColors
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.rows) {
				return;
			}

			var rows = response.data.rows;

			$presets.find('.ctm-color-preset').each(function () {
				var $preset = $(this);
				var colorSlug = String($preset.data('color'));
				var previous = {};

				$preset.find('.ctm-color-preset__row').each(function () {
					var pid = String($(this).data('product-id'));
					previous[pid] = $(this).find('select').val() || '';
				});

				var html = rows
					.map(function (row) {
						var rowHtml = buildRowHtml(colorSlug, row);
						var $tmp = $('<div/>').html(rowHtml);
						var prevVal = previous[String(row.product_id)];

						if (prevVal) {
							$tmp.find('select').val(prevVal);
						}

						return $tmp.html();
					})
					.join('');

				$preset.find('.ctm-color-preset__products').html(html);
			});
		});
	}

	$(document).on('change', '#ctm_product_ids', function () {
		refreshPresetProductRows();
	});
})(jQuery);
