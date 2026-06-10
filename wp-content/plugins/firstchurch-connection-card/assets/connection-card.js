/**
 * Check-in & Connection Card — client behavior.
 *
 * Vanilla JS, no dependencies. Validates required fields client-side
 * (mirrors what the WP REST handler enforces), POSTs JSON to the
 * REST endpoint, and renders inline success/error states.
 */

(function () {
	'use strict';

	document.querySelectorAll('form.fcc-form').forEach(initForm);

	function initForm(form) {
		var endpoint = form.dataset.endpoint;
		var nonce = form.dataset.nonce;
		var statusEl = form.querySelector('.fcc-form__status');
		var submitBtn = form.querySelector('.fcc-submit');

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			clearStatus();

			var errors = validate();
			if (errors.length) {
				showStatus('error', errors.join(' '));
				return;
			}

			var payload = buildPayload();
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting…';

			fetch(endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify(payload),
			})
				.then(function (resp) {
					return resp.json().then(function (body) {
						return { ok: resp.ok, status: resp.status, body: body };
					});
				})
				.then(function (result) {
					if (result.ok && result.body && result.body.ok) {
						showStatus('success', "Thanks for checking in! We're glad you're here.");
						form.reset();
					} else if (result.status === 429) {
						showStatus(
							'error',
							"You've submitted a few times already — please wait a few minutes and try again."
						);
					} else {
						var msg =
							(result.body && (result.body.message || result.body.code)) ||
							'Something went wrong. Please try again or use the Breeze form directly.';
						showStatus('error', msg);
					}
				})
				.catch(function () {
					showStatus(
						'error',
						"We couldn't reach the server. Check your connection and try again."
					);
				})
				.finally(function () {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit';
				});
		});

		// Clear per-field error state as the user fixes things.
		form.addEventListener('input', function (e) {
			if (e.target.hasAttribute('aria-invalid')) {
				e.target.removeAttribute('aria-invalid');
			}
		});
		form.addEventListener('change', function (e) {
			var fs = e.target.closest('.fcc-fieldset');
			if (fs && fs.hasAttribute('aria-invalid')) {
				fs.removeAttribute('aria-invalid');
			}
		});

		function validate() {
			var errs = [];

			var first = field('first_name');
			var last = field('last_name');
			var email = field('email');
			var attended = form.querySelector('input[name="attended"]:checked');
			var iAmA = form.querySelector('input[name="i_am_a"]:checked');

			if (!first.value.trim()) markInvalid(first, errs, 'Please enter your first name.');
			if (!last.value.trim()) markInvalid(last, errs, 'Please enter your last name.');
			if (!email.value.trim() || !/^\S+@\S+\.\S+$/.test(email.value)) {
				markInvalid(email, errs, 'Please enter a valid email.');
			}
			if (!attended) {
				markFieldsetInvalid('attended', errs, 'Please choose Online or In-person.');
			}
			if (!iAmA) {
				markFieldsetInvalid('i_am_a', errs, 'Please choose how you relate to First Church.');
			}

			return errs;
		}

		function buildPayload() {
			var fd = new FormData(form);
			var payload = {
				first_name: fd.get('first_name') || '',
				last_name: fd.get('last_name') || '',
				email: fd.get('email') || '',
				attended: fd.get('attended') || '',
				i_am_a: fd.get('i_am_a') || '',
				phone: fd.get('phone') || '',
				newsletter: fd.get('newsletter') ? true : false,
				change_of_info: fd.get('change_of_info') ? true : false,
				heard_from: fd.get('heard_from') || '',
				comments: fd.get('comments') || '',
				learn_more: fd.getAll('learn_more[]'),
				pastor_contact: fd.getAll('pastor_contact[]'),
				website: fd.get('website') || '',
				'cf-turnstile-response': fd.get('cf-turnstile-response') || '',
			};

			var addr = {
				street: fd.get('address[street]') || '',
				city: fd.get('address[city]') || '',
				state: fd.get('address[state]') || '',
				zip: fd.get('address[zip]') || '',
			};
			if (addr.street || addr.city || addr.state || addr.zip) {
				payload.address = addr;
			}

			return payload;
		}

		function field(name) {
			return form.querySelector('[name="' + name + '"]');
		}

		function markInvalid(el, errs, msg) {
			el.setAttribute('aria-invalid', 'true');
			errs.push(msg);
		}

		function markFieldsetInvalid(name, errs, msg) {
			var input = form.querySelector('input[name="' + name + '"]');
			if (input) {
				var fs = input.closest('.fcc-fieldset');
				if (fs) fs.setAttribute('aria-invalid', 'true');
			}
			errs.push(msg);
		}

		function showStatus(state, message) {
			statusEl.setAttribute('data-state', state);
			statusEl.textContent = message;
			if (state === 'error') {
				statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}

		function clearStatus() {
			statusEl.removeAttribute('data-state');
			statusEl.textContent = '';
		}
	}
})();
