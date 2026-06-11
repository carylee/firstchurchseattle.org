/**
 * Mode 3 — native Breeze form client behavior.
 *
 * Vanilla JS, no dependencies. Reads the field contract the server embedded on
 * the form (data-fields: [{key,type}]), serializes each field to the shape the
 * REST handler expects, POSTs JSON to data-endpoint, and renders inline
 * success/error states. The same field types the PHP core understands are
 * handled here: name, email/phone/text, textarea, checkbox, radio, address.
 */
(function () {
	'use strict';

	document.querySelectorAll('form.fcbf-native').forEach(initForm);

	function initForm(form) {
		var endpoint = form.dataset.endpoint;
		var nonce = form.dataset.nonce;
		var successMsg = form.dataset.success || 'Thank you — your submission has been received.';
		var statusEl = form.querySelector('.fcbf-native__status');
		var submitBtn = form.querySelector('.fcbf-native__submit');
		var submitLabel = submitBtn ? submitBtn.textContent : 'Submit';

		var fields = [];
		try {
			fields = JSON.parse(form.dataset.fields || '[]');
		} catch (e) {
			fields = [];
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			clearStatus();

			var errors = validate();
			if (errors.length) {
				showStatus('error', errors.join(' '));
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending…';

			fetch(endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					Accept: 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify(buildPayload()),
			})
				.then(function (resp) {
					return resp.json().then(function (body) {
						return { ok: resp.ok, status: resp.status, body: body };
					});
				})
				.then(function (result) {
					if (result.ok && result.body && result.body.ok) {
						showStatus('success', successMsg);
						form.reset();
					} else if (result.status === 429) {
						showStatus(
							'error',
							"You've submitted a few times already — please wait a few minutes and try again."
						);
					} else {
						var msg =
							(result.body && (result.body.message || result.body.code)) ||
							'Something went wrong. Please try again.';
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
					submitBtn.textContent = submitLabel;
				});
		});

		form.addEventListener('input', function (e) {
			if (e.target.hasAttribute('aria-invalid')) {
				e.target.removeAttribute('aria-invalid');
			}
		});

		var fd = function () {
			return new FormData(form);
		};

		function buildPayload() {
			var data = fd();
			var payload = {
				form_id: data.get('form_id') || '',
				website: data.get('website') || '',
				'cf-turnstile-response': data.get('cf-turnstile-response') || '',
			};
			fields.forEach(function (f) {
				payload[f.key] = serialize(data, f);
			});
			return payload;
		}

		function serialize(data, f) {
			switch (f.type) {
				case 'name':
					return {
						first: data.get(f.key + '_first') || '',
						last: data.get(f.key + '_last') || '',
					};
				case 'address':
					return {
						street: data.get(f.key + '_street') || '',
						city: data.get(f.key + '_city') || '',
						state: data.get(f.key + '_state') || '',
						zip: data.get(f.key + '_zip') || '',
					};
				case 'checkbox':
					return data.getAll(f.key + '[]');
				case 'radio':
					return data.get(f.key) || '';
				default:
					return data.get(f.key) || '';
			}
		}

		// Client-side mirror of Native::validate — required-field checks only, so
		// the visitor gets fast feedback; the server re-validates regardless.
		function validate() {
			var errs = [];
			var data = fd();
			fields.forEach(function (f) {
				if (!isRequired(f)) return;
				var val = serialize(data, f);
				if (isEmpty(f, val)) {
					errs.push(messageFor(f));
					mark(f);
				}
			});
			return errs;
		}

		function isRequired(f) {
			// name/text/textarea/email/phone carry `required` on their input;
			// checkbox/radio groups mark the fieldset with data-required (set by
			// the server only when the group is required — see below).
			var el = form.querySelector(
				'[name="' + f.key + '"], [name="' + f.key + '_first"]'
			);
			if (el) return el.hasAttribute('required');
			var fs = form.querySelector('fieldset[data-key="' + f.key + '"]');
			return fs ? fs.hasAttribute('data-required') : false;
		}

		function isEmpty(f, val) {
			switch (f.type) {
				case 'name':
					return !val.first.trim() || !val.last.trim();
				case 'address':
					return !val.street.trim();
				case 'checkbox':
					return val.length === 0;
				case 'email':
					return !/^\S+@\S+\.\S+$/.test((val || '').trim());
				default:
					return !(val || '').trim();
			}
		}

		function messageFor(f) {
			if (f.type === 'name') return 'Please provide your first and last name.';
			if (f.type === 'email') return 'Please provide a valid email address.';
			var fs = form.querySelector('fieldset[data-key="' + f.key + '"] legend');
			var label = fs ? fs.textContent.replace('*', '').trim() : f.key;
			return 'Please complete “' + label + '”.';
		}

		function mark(f) {
			var el = form.querySelector('[name="' + f.key + '"], [name="' + f.key + '_first"]');
			if (el) el.setAttribute('aria-invalid', 'true');
			var fs = form.querySelector('fieldset[data-key="' + f.key + '"]');
			if (fs) fs.setAttribute('aria-invalid', 'true');
		}

		function showStatus(state, message) {
			statusEl.setAttribute('data-state', state);
			statusEl.textContent = message;
			statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		function clearStatus() {
			statusEl.removeAttribute('data-state');
			statusEl.textContent = '';
		}
	}
})();
