(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        if (typeof SCBC_DATA === 'undefined' || !SCBC_DATA.publishableKey) {
            return;
        }

        var stripe = Stripe(SCBC_DATA.publishableKey);
        var buttons = document.querySelectorAll('.scbc-book-btn');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var slotId = this.getAttribute('data-slot-id');
                var emailInput = document.getElementById('scbc-customer-email');
                var customerEmail = emailInput ? emailInput.value.trim() : '';
                if (!slotId) {
                    return;
                }
                if (!customerEmail) {
                    alert('Please enter your client email first.');
                    if (emailInput) {
                        emailInput.focus();
                    }
                    return;
                }

                button.disabled = true;
                button.textContent = SCBC_DATA.messages.loading;

                var form = new FormData();
                form.append('action', 'scbc_create_checkout_session');
                form.append('nonce', SCBC_DATA.nonce);
                form.append('slot_id', slotId);
                form.append('return_url', window.location.href);
                form.append('customer_email', customerEmail);

                fetch(SCBC_DATA.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                })
                    .then(function (res) {
                        return res.json();
                    })
                    .then(function (payload) {
                        if (!payload.success || !payload.data || !payload.data.sessionId) {
                            throw new Error(payload.data && payload.data.message ? payload.data.message : SCBC_DATA.messages.error);
                        }

                        return stripe.redirectToCheckout({ sessionId: payload.data.sessionId });
                    })
                    .then(function (result) {
                        if (result && result.error) {
                            throw new Error(result.error.message);
                        }
                    })
                    .catch(function (err) {
                        alert(err.message || SCBC_DATA.messages.error);
                        button.disabled = false;
                        button.textContent = SCBC_DATA.buttonLabel || 'Book 6 Week Session';
                    });
            });
        });
    });
})();
