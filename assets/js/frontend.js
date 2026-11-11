/**
 * Complete the Look — multi-select add to cart + variation options + main color sync.
 */
(function ($) {
	'use strict';

	function parseJSONAttr($el, attr) {
		var raw = $el.attr(attr);

		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function parseVariations($item) {
		return parseJSONAttr($item, 'data-product_variations') || [];
	}

	function parsePresets($section) {
		return parseJSONAttr($section, 'data-color-presets') || {};
	}

	function getSelectedAttributes($item) {
		var attrs = {};

		$item.find('.ctm-complete-the-look__attr-select').each(function () {
			var name = $(this).data('attribute_name') || $(this).attr('name');
			var value = $(this).val();

			if (name) {
				attrs[name] = value || '';
			}
		});

		return attrs;
	}

	function attributesComplete(attrs) {
		var keys = Object.keys(attrs);

		if (!keys.length) {
			return false;
		}

		return keys.every(function (key) {
			return !!attrs[key];
		});
	}

	function getFilledAttributes(attrs) {
		var filled = {};

		Object.keys(attrs || {}).forEach(function (key) {
			if (attrs[key]) {
				filled[key] = attrs[key];
			}
		});

		return filled;
	}

	function variationMatches(variation, attrs) {
		var vAttrs = variation.attributes || {};
		var keys = Object.keys(attrs);

		for (var i = 0; i < keys.length; i++) {
			var key = keys[i];
			var selected = attrs[key];
			var available = vAttrs[key];

			// Empty variation attribute means "any".
			if (available && String(available) !== String(selected)) {
				return false;
			}
		}

		return true;
	}

	function findMatchingVariations(variations, attrs) {
		var filled = getFilledAttributes(attrs);

		if (!variations || !variations.length || !Object.keys(filled).length) {
			return [];
		}

		return variations.filter(function (variation) {
			return variationMatches(variation, filled);
		});
	}

	function findMatchingVariation(variations, attrs) {
		var matches = findMatchingVariations(variations, attrs);
		return matches.length ? matches[0] : null;
	}

	function swapImageSmooth($img, src) {
		if (!$img.length || !src) {
			return;
		}

		if ($img.attr('src') === src) {
			$img.removeClass('is-fading');
			return;
		}

		var token = String(Date.now()) + Math.random();
		$img.data('ctm-fade-token', token);

		var applySrc = function () {
			if ($img.data('ctm-fade-token') !== token) {
				return;
			}

			$img.attr('src', src);
			$img.removeAttr('srcset');
			$img.removeAttr('sizes');

			window.requestAnimationFrame(function () {
				window.requestAnimationFrame(function () {
					if ($img.data('ctm-fade-token') === token) {
						$img.removeClass('is-fading');
					}
				});
			});
		};

		$img.addClass('is-fading');

		var preload = new Image();
		preload.onload = function () {
			window.setTimeout(applySrc, 180);
		};
		preload.onerror = function () {
			window.setTimeout(applySrc, 180);
		};
		preload.src = src;
	}

	function setProductImage($item, src) {
		swapImageSmooth(
			$item.find('img.ctm-complete-the-look__image, .ctm-complete-the-look__image').first(),
			src
		);
	}

	function syncSwatchUI($attribute) {
		var value = $attribute.find('.ctm-complete-the-look__attr-select').val();

		$attribute.find('.ctm-complete-the-look__swatch').each(function () {
			var selected = String($(this).data('value')) === String(value);
			$(this).toggleClass('is-selected', selected);
			$(this).attr('aria-selected', selected ? 'true' : 'false');
		});
	}

	function setAttributeValue($item, attributeName, value, triggerChange) {
		var $select = $item.find(
			'.ctm-complete-the-look__attr-select[data-attribute_name="' + attributeName + '"]'
		);

		if (!$select.length) {
			return false;
		}

		var exists =
			!value ||
			$select.find('option').filter(function () {
				return String($(this).val()) === String(value);
			}).length > 0;

		if (!exists) {
			return false;
		}

		if (String($select.val()) === String(value || '')) {
			syncSwatchUI($select.closest('.ctm-complete-the-look__attribute'));
			updateItemVariation($item);
			return true;
		}

		$select.val(value || '');

		if (triggerChange !== false) {
			$select.trigger('change');
		} else {
			syncSwatchUI($select.closest('.ctm-complete-the-look__attribute'));
			updateItemVariation($item);
		}

		return true;
	}

	function setItemColor($item, colorValue) {
		var $colorSelect = $item.find(
			'.ctm-complete-the-look__attribute--color .ctm-complete-the-look__attr-select'
		);

		if (!$colorSelect.length) {
			return false;
		}

		return setAttributeValue(
			$item,
			$colorSelect.data('attribute_name') || $colorSelect.attr('name'),
			colorValue,
			true
		);
	}

	function updateItemVariation($item) {
		if (!$item.hasClass('ctm-complete-the-look__item--variable')) {
			return true;
		}

		var attrs = getSelectedAttributes($item);
		var filled = getFilledAttributes(attrs);
		var $variationInput = $item.find('.ctm-complete-the-look__variation-id');
		var $img = $item.find('img.ctm-complete-the-look__image, .ctm-complete-the-look__image').first();
		var $price = $item.find('.price, .ctm-complete-the-look__price').first();
		var defaultSrc = $img.data('default-src');
		var complete = attributesComplete(attrs);

		if (!Object.keys(filled).length) {
			$variationInput.val('');
			$item.removeClass('ctm-variation-found ctm-variation-unavailable');
			if (defaultSrc) {
				setProductImage($item, defaultSrc);
			}
			return false;
		}

		var matches = findMatchingVariations(parseVariations($item), filled);

		if (!matches.length) {
			$variationInput.val('');
			$item.removeClass('ctm-variation-found').addClass('ctm-variation-unavailable');
			return false;
		}

		var variation = matches[0];

		// Always update thumbnail/price as soon as a color (or other attr) matches.
		if (variation.image_src) {
			setProductImage($item, variation.image_src);
		}

		if ($price.length && variation.price_html) {
			$price.html(variation.price_html);
		}

		$item.removeClass('ctm-variation-unavailable');

		if (complete) {
			$variationInput.val(String(variation.variation_id));
			$item.addClass('ctm-variation-found');
			return true;
		}

		// Partial selection (e.g. color only): preview image, wait for remaining options.
		$variationInput.val('');
		$item.removeClass('ctm-variation-found');
		return false;
	}

	function getCheckedItems($section) {
		var items = [];

		$section.find('.ctm-complete-the-look__checkbox:checked').each(function () {
			var $checkbox = $(this);
			var $item = $checkbox.closest('.ctm-complete-the-look__item');
			var productId = parseInt($checkbox.val(), 10);

			if (!productId) {
				return;
			}

			var payload = {
				product_id: productId,
				variation_id: 0,
				attributes: {}
			};

			if ($item.hasClass('ctm-complete-the-look__item--variable')) {
				var attrs = getSelectedAttributes($item);
				var variationId =
					parseInt($item.find('.ctm-complete-the-look__variation-id').val(), 10) || 0;

				if (!attributesComplete(attrs) || !variationId) {
					payload.invalid = true;
				} else {
					payload.variation_id = variationId;
					payload.attributes = attrs;
				}
			}

			items.push(payload);
		});

		return items;
	}

	function setButtonState($btn, loading) {
		$btn.prop('disabled', loading);
		$btn.toggleClass('loading', loading);

		if (loading) {
			$btn.data('original-text', $btn.text());
			$btn.text(ctmFrontend.i18n.adding);
		} else {
			$btn.text($btn.data('original-text') || ctmFrontend.i18n.addSelected);
		}
	}

	function syncButton($section) {
		var $btn = $section.find('.ctm-complete-the-look__add-selected');
		var items = getCheckedItems($section);
		var count = items.length;

		$btn.prop('disabled', count === 0);
		$btn.removeClass('added');
	}

	function getMainFormColor($section) {
		var attrName = $section.attr('data-main-color-attribute');

		if (!attrName) {
			return '';
		}

		var $form = $('form.variations_form').first();
		var $field = $form.find('[name="' + attrName + '"]');

		if (!$field.length) {
			$field = $('.variations_form [name="' + attrName + '"]');
		}

		return $field.length ? String($field.val() || '') : '';
	}

	function syncMainItemFromForm($section) {
		var $mainItem = $section.find('.ctm-complete-the-look__item--main');

		if (!$mainItem.length) {
			return;
		}

		var $form = $('form.variations_form').first();

		if (!$form.length) {
			return;
		}

		$mainItem.find('.ctm-complete-the-look__attr-select').each(function () {
			var name = $(this).data('attribute_name') || $(this).attr('name');
			var $source = $form.find('[name="' + name + '"]');

			if ($source.length) {
				setAttributeValue($mainItem, name, $source.val() || '', false);
			}
		});
	}

	function updateLookImage($section, colorSlug) {
		var $img = $section.find('.ctm-complete-the-look__look-img');

		if (!$img.length) {
			return;
		}

		var presets = parsePresets($section);
		var preset = colorSlug && presets[colorSlug] ? presets[colorSlug] : null;
		var nextSrc =
			(preset && preset.image_url) ||
			$section.attr('data-default-look-image') ||
			$img.data('default-src') ||
			'';

		swapImageSmooth($img, nextSrc);
	}

	function applyColorPreset($section, colorSlug) {
		if (!$section.hasClass('ctm-syncs-main-color')) {
			return;
		}

		syncMainItemFromForm($section);
		updateLookImage($section, colorSlug);

		if (!colorSlug) {
			syncButton($section);
			return;
		}

		var presets = parsePresets($section);
		var preset = presets[colorSlug] || {};
		var defaults = preset.defaults || {};

		$section.find('.ctm-complete-the-look__item').each(function () {
			var $item = $(this);

			if ($item.hasClass('ctm-complete-the-look__item--main')) {
				return;
			}

			var productId = String($item.data('product-id'));
			var colorValue = defaults[productId] || colorSlug;

			setItemColor($item, colorValue);
		});

		$section.attr('data-active-color', colorSlug);
		syncButton($section);
	}

	function bindMainColorSync($section) {
		if (!$section.hasClass('ctm-syncs-main-color')) {
			return;
		}

		var attrName = $section.attr('data-main-color-attribute');
		var $form = $('form.variations_form').first();

		function refresh() {
			applyColorPreset($section, getMainFormColor($section));
		}

		if ($form.length) {
			$form.on('woocommerce_variation_has_changed found_variation reset_data', refresh);
			$form.on('change', '[name="' + attrName + '"]', refresh);
		}

		$(document.body).on('check_variations', refresh);

		// Initial apply (after variations form is ready).
		refresh();
	}

	$(document).on('click', '.ctm-complete-the-look__swatch', function (e) {
		e.preventDefault();

		var $swatch = $(this);
		var $attribute = $swatch.closest('.ctm-complete-the-look__attribute');
		var $select = $attribute.find('.ctm-complete-the-look__attr-select');
		var value = $swatch.data('value');

		if ($select.val() === String(value)) {
			$select.val('').trigger('change');
			return;
		}

		$select.val(String(value)).trigger('change');
	});

	$(document).on('change', '.ctm-complete-the-look__attr-select', function () {
		var $item = $(this).closest('.ctm-complete-the-look__item');
		var $attribute = $(this).closest('.ctm-complete-the-look__attribute');

		syncSwatchUI($attribute);
		updateItemVariation($item);
		syncButton($item.closest('.ctm-complete-the-look'));
	});

	$(document).on('click', '.ctm-complete-the-look__add-selected', function (e) {
		e.preventDefault();

		var $btn = $(this);
		var $section = $btn.closest('.ctm-complete-the-look');
		var items = getCheckedItems($section);

		if (!items.length) {
			window.alert(ctmFrontend.i18n.noneSelected);
			return;
		}

		var hasInvalid = items.some(function (item) {
			return !!item.invalid;
		});

		if (hasInvalid) {
			window.alert(ctmFrontend.i18n.selectOptions);
			return;
		}

		setButtonState($btn, true);

		$.ajax({
			type: 'POST',
			url: ctmFrontend.ajaxUrl,
			dataType: 'json',
			data: {
				action: 'ctm_add_selected_to_cart',
				nonce: ctmFrontend.nonce,
				items: items
			}
		})
			.done(function (response) {
				if (!response || !response.success) {
					window.alert(
						(response && response.data && response.data.message) ||
							ctmFrontend.i18n.error
					);
					return;
				}

				if (response.data && response.data.fragments) {
					$.each(response.data.fragments, function (key, value) {
						$(key).replaceWith(value);
					});
				}

				$(document.body).trigger('wc_fragment_refresh');
				$(document.body).trigger('added_to_cart', [
					response.data.fragments,
					response.data.cart_hash,
					$btn
				]);

				$btn.addClass('added');
			})
			.fail(function () {
				window.alert(ctmFrontend.i18n.error);
			})
			.always(function () {
				setButtonState($btn, false);
				syncButton($section);
			});
	});

	$(document).on('change', '.ctm-complete-the-look__checkbox', function () {
		syncButton($(this).closest('.ctm-complete-the-look'));
	});

	function getJumpScrollOffset() {
		var offset = 16;
		var header = document.querySelector('.has-sticky-header .site-header');

		if (header) {
			offset += header.getBoundingClientRect().height || 72;
		}

		if (document.body.classList.contains('admin-bar')) {
			offset += window.innerWidth > 782 ? 32 : 46;
		}

		return offset;
	}

	function smoothScrollToLook() {
		var target = document.getElementById('ctm-complete-the-look');

		if (!target) {
			return false;
		}

		var reduceMotion =
			window.matchMedia &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var top =
			target.getBoundingClientRect().top +
			window.pageYOffset -
			getJumpScrollOffset();

		window.scrollTo({
			top: Math.max(0, top),
			behavior: reduceMotion ? 'auto' : 'smooth'
		});

		window.setTimeout(
			function () {
				var heading = document.getElementById('ctm-heading');
				if (heading && typeof heading.focus === 'function') {
					heading.focus({ preventScroll: true });
				}
			},
			reduceMotion ? 0 : 650
		);

		return true;
	}

	$(document).on('click', '.ctm-complete-the-look__jump', function (e) {
		e.preventDefault();
		smoothScrollToLook();
	});

	$(function () {
		$('.ctm-complete-the-look').each(function () {
			var $section = $(this);

			$section.find('.ctm-complete-the-look__item--variable').each(function () {
				var $item = $(this);

				$item.find('.ctm-complete-the-look__attribute').each(function () {
					syncSwatchUI($(this));
				});

				updateItemVariation($item);
			});

			bindMainColorSync($section);
			syncButton($section);
		});
	});
})(jQuery);
