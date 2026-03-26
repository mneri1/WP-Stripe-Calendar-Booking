(function () {
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    ready(function () {
        if (typeof SCBC_DATA === 'undefined' || !SCBC_DATA.publishableKey) {
            return;
        }

        var stripe = Stripe(SCBC_DATA.publishableKey);
        var slotList = document.getElementById('scbc-slot-list');
        var loadMoreBtn = document.getElementById('scbc-load-more');
        var paginationWrap = document.getElementById('scbc-pagination');
        var monthFilter = document.getElementById('scbc-month-filter');
        var emailInput = document.getElementById('scbc-customer-email');
        var modal = document.getElementById('scbc-slot-modal');
        var modalClose = document.getElementById('scbc-modal-close');
        var modalDetails = document.getElementById('scbc-modal-details');
        var modalBookBtn = document.getElementById('scbc-modal-book-btn');
        var modalRetryBtn = document.getElementById('scbc-modal-retry-btn');
        var activeSlotId = '';

        function buildSkeletonMarkup(count) {
            var total = count || 4;
            var html = '<div class="scbc-skeleton-grid" aria-hidden="true">';
            for (var i = 0; i < total; i++) {
                html += '<article class="scbc-list-card scbc-skeleton-card"><div class="scbc-skeleton-line w70"></div><div class="scbc-skeleton-line w45"></div><div class="scbc-skeleton-gap"></div><div class="scbc-skeleton-line w90"></div><div class="scbc-skeleton-line w65"></div><div class="scbc-skeleton-line w55"></div><div class="scbc-skeleton-line w40"></div><div class="scbc-skeleton-btn"></div></article>';
            }
            html += '</div>';
            return html;
        }

        function updateLoadMoreButton(page, maxPages, disabledText) {
            if (!loadMoreBtn) {
                return;
            }
            loadMoreBtn.setAttribute('data-page', String(page));
            loadMoreBtn.setAttribute('data-max-pages', String(maxPages));
            if (page >= maxPages) {
                loadMoreBtn.disabled = true;
                loadMoreBtn.textContent = disabledText || SCBC_DATA.messages.noMore;
            } else {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = SCBC_DATA.messages.loadMore;
            }
        }

        function updatePagination(html) {
            if (!paginationWrap) {
                return;
            }
            paginationWrap.innerHTML = html || '';
        }

        function mergeSectionsByMonth(tempContainer) {
            if (!slotList) {
                return;
            }
            var sections = tempContainer.querySelectorAll('.scbc-list-view[data-month-key]');
            sections.forEach(function (section) {
                var monthKey = section.getAttribute('data-month-key');
                if (!monthKey) {
                    slotList.appendChild(section);
                    return;
                }
                var existing = slotList.querySelector('.scbc-list-view[data-month-key="' + monthKey + '"]');
                if (!existing) {
                    slotList.appendChild(section);
                    return;
                }
                var existingGrid = existing.querySelector('.scbc-list-grid');
                var incomingGrid = section.querySelector('.scbc-list-grid');
                if (existingGrid && incomingGrid) {
                    while (incomingGrid.firstChild) {
                        existingGrid.appendChild(incomingGrid.firstChild);
                    }
                }
            });
        }

        function triggerSlotFade() {
            if (!slotList) {
                return;
            }
            slotList.classList.remove('scbc-fade-run');
            window.requestAnimationFrame(function () {
                slotList.classList.add('scbc-fade-run');
            });
        }

        function loadSlots(page, month, append) {
            if (!slotList) {
                return Promise.resolve();
            }
            var skeletonTail = null;
            if (loadMoreBtn) {
                loadMoreBtn.disabled = true;
                loadMoreBtn.textContent = SCBC_DATA.messages.loadingSlots;
            }
            if (append) {
                skeletonTail = document.createElement('div');
                skeletonTail.className = 'scbc-loading-tail';
                skeletonTail.innerHTML = buildSkeletonMarkup(4);
                slotList.appendChild(skeletonTail);
            } else {
                slotList.innerHTML = buildSkeletonMarkup(6);
            }

            var form = new FormData();
            form.append('action', 'scbc_fetch_slots');
            form.append('nonce', SCBC_DATA.nonce);
            form.append('page', String(page));
            form.append('month', month || '');

            return fetch(SCBC_DATA.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: form
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (payload) {
                    if (!payload.success || !payload.data) {
                        throw new Error(SCBC_DATA.messages.loadError);
                    }
                    if (skeletonTail && skeletonTail.parentNode) {
                        skeletonTail.parentNode.removeChild(skeletonTail);
                    }
                    if (!append) {
                        slotList.innerHTML = '';
                    }

                    if (payload.data.html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = payload.data.html;
                        mergeSectionsByMonth(temp);
                    } else if (!append) {
                        slotList.innerHTML = '<p class="scbc-empty-list">No schedules are available right now.</p>';
                    }

                    updateLoadMoreButton(payload.data.page, payload.data.maxPages);
                    updatePagination(payload.data.paginationHtml || '');
                    triggerSlotFade();
                })
                .catch(function () {
                    if (skeletonTail && skeletonTail.parentNode) {
                        skeletonTail.parentNode.removeChild(skeletonTail);
                    }
                    if (!append) {
                        slotList.innerHTML = '<p class="scbc-empty-list">' + escapeHtml(SCBC_DATA.messages.loadError) + '</p>';
                    }
                    if (loadMoreBtn) {
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = SCBC_DATA.messages.loadMore;
                    }
                });
        }

        function closeModal() {
            if (!modal) {
                return;
            }
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('scbc-modal-open');
            activeSlotId = '';
            if (modalBookBtn) {
                modalBookBtn.setAttribute('data-slot-id', '');
                modalBookBtn.disabled = false;
                modalBookBtn.textContent = SCBC_DATA.messages.modalButton || 'Continue to Payment';
            }
            if (modalRetryBtn) {
                modalRetryBtn.setAttribute('data-slot-id', '');
                modalRetryBtn.hidden = true;
                modalRetryBtn.disabled = false;
            }
        }

        function openModal(button) {
            if (!modal || !modalDetails || !modalBookBtn) {
                return;
            }
            var slotId = button.getAttribute('data-slot-id') || '';
            if (!slotId) {
                return;
            }
            activeSlotId = slotId;
            modalBookBtn.setAttribute('data-slot-id', slotId);
            if (modalRetryBtn) {
                modalRetryBtn.setAttribute('data-slot-id', slotId);
                modalRetryBtn.hidden = true;
                modalRetryBtn.disabled = false;
            }
            modalDetails.innerHTML =
                '<p><strong>' + escapeHtml(button.getAttribute('data-slot-title') || '') + '</strong></p>' +
                '<p><strong>Date:</strong> ' + escapeHtml(button.getAttribute('data-slot-date') || '') + '</p>' +
                '<p><strong>Time:</strong> ' + escapeHtml(button.getAttribute('data-slot-time') || '') + '</p>' +
                '<p><strong>Duration:</strong> ' + escapeHtml(button.getAttribute('data-slot-duration') || '') + '</p>' +
                '<p><strong>Spots Left:</strong> ' + escapeHtml(button.getAttribute('data-slot-spots') || '') + '</p>' +
                '<p><strong>Price:</strong> ' + escapeHtml(button.getAttribute('data-slot-price') || '') + '</p>';

            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('scbc-modal-open');
        }

        function startCheckout(slotId, actionButton) {
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

            actionButton.disabled = true;
            actionButton.textContent = SCBC_DATA.messages.loading;

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
                    var base = (err && err.message) ? err.message : SCBC_DATA.messages.error;
                    var help = SCBC_DATA.messages.checkoutHelp || '';
                    alert(help ? (base + '\n\n' + help) : base);
                    actionButton.disabled = false;
                    actionButton.textContent = SCBC_DATA.messages.modalButton || 'Continue to Payment';
                    if (modalRetryBtn) {
                        modalRetryBtn.hidden = false;
                        modalRetryBtn.disabled = false;
                    }
                });
        }

        if (slotList) {
            slotList.addEventListener('click', function (event) {
                var trigger = event.target.closest('.scbc-open-modal');
                if (!trigger) {
                    return;
                }
                openModal(trigger);
            });
        }

        if (modalBookBtn) {
            modalBookBtn.addEventListener('click', function () {
                var slotId = modalBookBtn.getAttribute('data-slot-id') || activeSlotId;
                startCheckout(slotId, modalBookBtn);
            });
        }

        if (modalRetryBtn) {
            modalRetryBtn.addEventListener('click', function () {
                var slotId = modalRetryBtn.getAttribute('data-slot-id') || activeSlotId;
                startCheckout(slotId, modalRetryBtn);
            });
        }

        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal && modal.getAttribute('aria-hidden') === 'false') {
                closeModal();
            }
        });

        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                var page = parseInt(loadMoreBtn.getAttribute('data-page') || '1', 10);
                var maxPages = parseInt(loadMoreBtn.getAttribute('data-max-pages') || '1', 10);
                if (page >= maxPages) {
                    updateLoadMoreButton(page, maxPages);
                    return;
                }
                var month = monthFilter ? monthFilter.value : '';
                loadSlots(page + 1, month, true);
            });
        }

        if (monthFilter) {
            monthFilter.addEventListener('change', function () {
                loadSlots(1, monthFilter.value, false);
            });
        }

        if (paginationWrap) {
            paginationWrap.addEventListener('click', function (event) {
                var trigger = event.target.closest('.scbc-page-btn, .scbc-page-nav');
                if (!trigger || trigger.disabled) {
                    return;
                }
                var targetPage = parseInt(trigger.getAttribute('data-page') || '1', 10);
                if (isNaN(targetPage) || targetPage < 1) {
                    return;
                }
                var month = monthFilter ? monthFilter.value : '';
                loadSlots(targetPage, month, false);
                window.scrollTo({ top: slotList ? slotList.offsetTop - 110 : 0, behavior: 'smooth' });
            });
        }
    });
})();
