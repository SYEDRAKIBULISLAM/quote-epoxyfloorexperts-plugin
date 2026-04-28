(function ($) {
	'use strict';

	const config = typeof efexQuoteForm !== 'undefined' ? efexQuoteForm : {};
	const validZips = Array.isArray(config.validZipCodes) ? config.validZipCodes : [];
	const serviceAreaEnforced = validZips.length > 0;
	/** Elementor container widget that hosts this form (shortcode section). */
	const EFEX_ELEMENTOR_WIDGET_ID = '6e7257f';

	function normZip(zip) {
		return (zip || '').toString().trim().replace(/\D/g, '').slice(0, 5);
	}

	function isZipInService(zip) {
		if (!serviceAreaEnforced) {
			return true;
		}
		return validZips.indexOf(normZip(zip)) !== -1;
	}

	function getStepsInOrder() {
		return [1, 2, 3];
	}

	function getCurrentStepNumber($form) {
		const $active = $form.find('.arc-step.active');
		return $active.length ? parseInt($active.attr('data-step'), 10) : 1;
	}

	function hideArcErrors($form) {
		$form
			.find(
				'.arc-zip-error, .arc-step2-error, .arc-contact-error'
			)
			.hide();
	}

	function findElementorElement($form) {
		if (!$form || !$form.length) {
			return $();
		}
		const id = EFEX_ELEMENTOR_WIDGET_ID;
		return $form.closest(
			'.elementor-element-' +
				id +
				', [data-id="' +
				id +
				'"], .elementor-element[data-element-id*="' +
				id +
				'"]'
		);
	}

	/**
	 * Mobile: expand form viewport (generic Elementor page). Site-specific sibling hides
	 * can be added via `efexQuoteForm.elementorHideSelectors` / `elementorUnhideSelectors` (arrays of selectors).
	 */
	function efexHideSupportingLayout() {
		const extraHide = config.elementorHideSelectors;
		if (Array.isArray(extraHide)) {
			extraHide.forEach(function (sel) {
				if (sel) {
					$(sel).css('display', 'none');
				}
			});
		}
	}

	function efexUnhideSupportingLayout() {
		const extra = config.elementorUnhideSelectors;
		if (Array.isArray(extra)) {
			extra.forEach(function (sel) {
				if (sel) {
					$(sel).css('display', '');
				}
			});
		}
	}

	function hideOtherSections() {
		if (window.matchMedia('(max-width: 991px)').matches) {
			efexHideSupportingLayout();
			$("[data-elementor-type='wp-page']").css({
				padding: '0',
				minHeight: '100vh',
				display: 'flex',
				flexDirection: 'column',
				overflowY: 'auto',
			});
			$("[data-elementor-type='wp-page'] > *").css({
				marginTop: 'auto',
				marginBottom: 'auto',
				paddingBottom: '0',
				paddingTop: '0',
			});
			$('.e-con-inner').css('padding', '0');

			$(".elementor-element-6ce7684").css("display", "none");
			$(".elementor-element-1af20b3").css({"padding": "0", "min-height": "0"});
			$(".elementor-element-888fd35").css("display", "none");
			$(".elementor-element-f7b282a").css("display", "none");
			$(".elementor-element-f973ee0").css("display", "none");
			$(".elementor-element-c75a54c").css("display", "none");
			$(".ekit-template-content-footer").css("display", "none");
		}
	}

	function unhideOtherSections() {
		if (window.matchMedia('(max-width: 991px)').matches) {
			efexUnhideSupportingLayout();
			$("[data-elementor-type='wp-page']").css({
				padding: '',
				minHeight: '',
				display: '',
				flexDirection: '',
				overflowY: '',
			});
			$("[data-elementor-type='wp-page'] > *").css({
				marginTop: '',
				marginBottom: '',
				paddingBottom: '',
				paddingTop: '',
			});
			$('.e-con-inner').css('padding', '');

			$(".elementor-element-6ce7684").css("display", "");
			$(".elementor-element-1af20b3").css({"padding": "", "min-height": "720px"});
			$(".elementor-element-888fd35").css("display", "");
			$(".elementor-element-f7b282a").css("display", "");
			$(".elementor-element-3344556").css("display", "");
			$(".elementor-element-c75a54c").css("display", "");
			$(".ekit-template-content-footer").css("display", "");
		}
	}

	function toggleElementorPositioning($form, stepNumber) {
		const $elementorElement = findElementorElement($form);

		if (!$elementorElement.length) {
			return;
		}

		const isMobile = window.innerWidth <= 991;

		if (stepNumber === 1) {
			$elementorElement.css({
				position: '',
				width: '',
				height: '',
				right: '',
				background: '',
				top: '',
				display: '',
				flexDirection: '',
				alignItems: '',
				overflowY: '',
			});
			$elementorElement.children().css({
				marginTop: '',
				marginBottom: '',
			});
			$elementorElement.find('.arc-calculator').css({
				boxShadow: '0 4px 24px rgba(0, 0, 0, 0.08)',
			});
			unhideOtherSections();
		} else {
			if (isMobile) {
				hideOtherSections();
				$elementorElement.css({
					position: 'relative',
					width: '100%',
					background: '#fff',
					paddingTop: '20px',
					paddingBottom: '20px',
				});
			} else {
				unhideOtherSections();
				$elementorElement.css({
					position: 'absolute',
					width: '43%',
					height: '100%',
					right: '0',
					background: '#fff',
					top: '0',
					display: 'flex',
					flexDirection: 'column',
					alignItems: 'center',
					overflowY: 'scroll',
					paddingTop: 'unset',
					paddingBottom: 'unset',
					borderRadius: 'unset',
				});
				$elementorElement.children().css({
					marginTop: 'auto',
					marginBottom: 'auto',
				});
			}
			$elementorElement.find('.arc-calculator').css({
				boxShadow: 'unset',
			});
		}
	}

	/**
	 * Progress bar: hidden on step 1 (ZIP); from step 2 onward shows wizard steps 1…N.
	 * Connectors between circles use flex-grow so the row matches full content width.
	 */
	function updateStepProgress($form, stepNumber) {
		const $bar = $form.find('.arc-step-progress');
		const $list = $form.find('.arc-step-progress-list');
		const order = getStepsInOrder();
		const idx = order.indexOf(stepNumber);
		if (idx < 0) {
			$bar.prop('hidden', true);
			return;
		}
		const wizardIndex = idx + 1;
		const total = order.length;

		if (stepNumber < 2) {
			$bar.prop('hidden', true);
			return;
		}

		$bar.prop('hidden', false);
		let html = '';
		for (let w = 1; w <= total; w++) {
			let state = 'upcoming';
			if (w < wizardIndex) {
				state = 'complete';
			} else if (w === wizardIndex) {
				state = 'active';
			}
			const currentAttr = w === wizardIndex ? ' aria-current="step"' : '';
			html +=
				'<li class="arc-step-progress-item arc-step-progress-item--' +
				state +
				'"' +
				currentAttr +
				'><span class="arc-step-progress-num">' +
				w +
				'</span></li>';
			if (w < total) {
				const segState = w < wizardIndex ? 'complete' : 'upcoming';
				html +=
					'<li class="arc-step-progress-connector arc-step-progress-connector--' +
					segState +
					'" role="presentation" aria-hidden="true"></li>';
			}
		}
		$list.html(html);
	}

	function showStep($form, stepNumber) {
		$form.find('.arc-step').removeClass('active');
		$form.find('.arc-step-' + stepNumber).addClass('active');
		hideArcErrors($form);

		$form.find('.efex-endflow-message').hide();

		updateStepProgress($form, stepNumber);

		toggleElementorPositioning($form, stepNumber);

		// Nav buttons (ported from roof-form.js)
		$form.find('.arc-btn-next, .arc-btn-submit').hide();
		if (stepNumber === 1) {
			$form.find('.arc-btn-get-started').show();
			$form.find('.arc-btn-back').hide();
		} else {
			$form.find('.arc-btn-get-started').hide();
			$form.find('.arc-btn-back').show();

			const order = getStepsInOrder();
			const last = order[order.length - 1];
			if (stepNumber === last) {
				$form.find('.arc-btn-submit').show();
			} else {
				$form.find('.arc-btn-next').show();
			}
		}
	}

	function setEndflow($form, reason) {
		$form.find('.efex-endflow-flag').val('1');
		$form.find('.efex-endflow-reason').val(reason);

		const $msg = $form.find('.efex-endflow-message');
		const outText = config.outOfAreaText || '';
		if (outText) {
			$msg.find('.efex-endflow-out-of-area').text(outText);
		}

		$msg.show();
		$msg.find('.efex-endflow-out-of-area').toggle(reason === 'out_of_area');
	}

	function autoSubmitEndflow($form, reason) {
		setEndflow($form, reason);
		window.setTimeout(function () {
			window.onbeforeunload = null;
			$form[0].submit();
		}, 700);
	}

	function formatPhone(value) {
		const digits = String(value).replace(/\D/g, '').slice(0, 10);
		if (digits.length <= 3) {
			return digits.length ? '(' + digits : '';
		}
		if (digits.length <= 6) {
			return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
		}
		return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
	}

	$(document).ready(function () {
		$('.arc-roof-form-form.arc-form').each(function () {
			const $form = $(this);

			$form.on('input', 'input[type="tel"]', function () {
				this.value = formatPhone(this.value);
			});

			// Step 1 — Get Started
			$form.on('click', '.arc-btn-get-started', function () {
				const zip = normZip($form.find('.arc-zip').val());
				$form.find('.arc-zip').val(zip);
				$form.find('.arc-zip-error').hide();

				if (zip.length !== 5) {
					const msg = config.zipErrorText || 'Please enter a valid ZIP code.';
					$form.find('.arc-zip-error').text(msg).show();
					return;
				}

				if (!isZipInService(zip)) {
					const msg = config.outOfAreaText || 'Thank you for your interest! At this time, we do not service your area.';
					$form.find('.arc-zip-error').text(msg).show();
					return;
				}

				showStep($form, 2);
			});

			$form.find('.arc-zip').on('input keydown', function () {
				$form.find('.arc-zip-error').hide();
			});

			// Next (step 2 only)
			$form.on('click', '.arc-btn-next', function () {
				const current = getCurrentStepNumber($form);
				hideArcErrors($form);

				if (current === 2) {
					const $checks = $form.find('input[name^="installation_area"]:checked');
					if (!$checks.length) {
						$form.find('.arc-step2-error').show();
						return;
					}
					showStep($form, 3);
					return;
				}
			});

			// Back
			$form.on('click', '.arc-btn-back', function () {
				const current = getCurrentStepNumber($form);
				const order = getStepsInOrder();
				const idx = order.indexOf(current);
				if (idx <= 0) {
					return;
				}
				const prev = order[idx - 1];
				showStep($form, prev);
			});

			// Clear errors when options change
			$form.on('change', 'input[name^="installation_area"]', function () {
				$form.find('.arc-step2-error').hide();
			});

			// Submit — contact validation unless end-flow early submit
			$form.on('submit', function (e) {
				const isEndflow = $form.find('.efex-endflow-flag').val() === '1';
				if (isEndflow) {
					window.onbeforeunload = null;
					return true;
				}

				const firstName = ($form.find('input[name="first_name"]').val() || '').trim();
				const lastName = ($form.find('input[name="last_name"]').val() || '').trim();
				const email = ($form.find('input[name="email"]').val() || '').trim();
				const phone = ($form.find('input[name="phone"]').val() || '').trim();
				const consent = $form.find('input[name="consent"]:checked').length > 0;

				$form.find('.arc-contact-error').hide();
				if (!firstName || !lastName || !email || !phone || !consent) {
					e.preventDefault();
					$form.find('.arc-contact-error').show();
					return false;
				}
				window.onbeforeunload = null;
				return true;
			});

			showStep($form, 1);
		});

		$(window).on('resize', function () {
			$('.arc-roof-form-form.arc-form').each(function () {
				const $form = $(this);
				toggleElementorPositioning($form, getCurrentStepNumber($form));
			});
		});
	});
})(jQuery);