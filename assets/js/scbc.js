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
        var modalEmailInput = document.getElementById('scbc-modal-customer-email');
        var modal = document.getElementById('scbc-slot-modal');
        var modalClose = document.getElementById('scbc-modal-close');
        var modalDetails = document.getElementById('scbc-modal-details');
        var modalBookBtn = document.getElementById('scbc-modal-book-btn');
        var modalRetryBtn = document.getElementById('scbc-modal-retry-btn');
        var modalErrorBox = document.getElementById('scbc-modal-error');
        var confirmedModal = document.getElementById('scbc-confirmed-modal');
        var confirmedModalClose = document.getElementById('scbc-confirmed-modal-close');
        var confirmedModalDetails = document.getElementById('scbc-confirmed-modal-details');
        var activeSlotId = '';

        function showModalError(text) {
            if (!modalErrorBox) {
                alert(text);
                return;
            }
            modalErrorBox.textContent = text || '';
            modalErrorBox.hidden = false;
        }

        function clearModalError() {
            if (!modalErrorBox) {
                return;
            }
            modalErrorBox.textContent = '';
            modalErrorBox.hidden = true;
        }

        function logRetryClick(slotId) {
            if (!slotId || typeof SCBC_DATA === 'undefined' || !SCBC_DATA.nonce) {
                return;
            }
            var form = new FormData();
            form.append('action', 'scbc_log_retry_click');
            form.append('nonce', SCBC_DATA.nonce);
            form.append('slot_id', slotId);
            fetch(SCBC_DATA.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: form
            }).catch(function () {
                return null;
            });
        }

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
                modalBookBtn.textContent = SCBC_DATA.messages.modalButton || 'BOOK NOW';
            }
            if (modalRetryBtn) {
                modalRetryBtn.setAttribute('data-slot-id', '');
                modalRetryBtn.hidden = true;
                modalRetryBtn.disabled = false;
            }
            clearModalError();
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
            clearModalError();
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

        function getActiveModalEmailInput(actionButton) {
            var visibleModalInput = document.querySelector('.scbc-modal[aria-hidden="false"] #scbc-modal-customer-email');
            if (visibleModalInput) {
                return visibleModalInput;
            }

            if (actionButton) {
                var modalRoot = actionButton.closest('.scbc-modal');
                if (modalRoot) {
                    var scoped = modalRoot.querySelector('#scbc-modal-customer-email');
                    if (scoped) {
                        return scoped;
                    }
                }
            }

            if (modal && modal.getAttribute('aria-hidden') === 'false') {
                var openModalInput = modal.querySelector('#scbc-modal-customer-email');
                if (openModalInput) {
                    return openModalInput;
                }
            }

            var anyFilledInput = Array.prototype.find.call(
                document.querySelectorAll('#scbc-modal-customer-email'),
                function (node) {
                    return node && String(node.value || '').trim() !== '';
                }
            );
            if (anyFilledInput) {
                return anyFilledInput;
            }

            return modalEmailInput;
        }

        function startCheckout(slotId, actionButton) {
            var emailInput = getActiveModalEmailInput(actionButton);
            var customerEmail = emailInput ? emailInput.value.trim() : '';
            if (!slotId) {
                return;
            }
            if (!customerEmail) {
                showModalError('Please type your email first.');
                if (emailInput) {
                    emailInput.focus();
                }
                return;
            }
            if (emailInput && !emailInput.checkValidity()) {
                showModalError('Please enter a valid email address.');
                emailInput.focus();
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
                    showModalError(help ? (base + ' ' + help) : base);
                    actionButton.disabled = false;
                    actionButton.textContent = SCBC_DATA.messages.modalButton || 'BOOK NOW';
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
                logRetryClick(slotId);
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

        function closeConfirmedModal() {
            if (!confirmedModal) {
                return;
            }
            confirmedModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('scbc-modal-open');
            if (confirmedModalDetails) {
                confirmedModalDetails.innerHTML = '';
            }
        }

        function openConfirmedModal(button) {
            if (!confirmedModal || !confirmedModalDetails || !button) {
                return;
            }
            var title = button.getAttribute('data-title') || 'Booking';
            var dateText = button.getAttribute('data-date') || '';
            var timeText = button.getAttribute('data-time') || '';
            var statusText = button.getAttribute('data-status') || '';
            var bookedText = button.getAttribute('data-booked') || '';
            var amountText = button.getAttribute('data-amount') || '';
            confirmedModalDetails.innerHTML =
                '<p><strong>' + escapeHtml(title) + '</strong></p>' +
                '<p><strong>Status:</strong> ' + escapeHtml(statusText) + '</p>' +
                '<p><strong>Date:</strong> ' + escapeHtml(dateText) + '</p>' +
                '<p><strong>Time:</strong> ' + escapeHtml(timeText) + '</p>' +
                '<p><strong>Booked On:</strong> ' + escapeHtml(bookedText) + '</p>' +
                '<p><strong>Amount:</strong> ' + escapeHtml(amountText) + '</p>';
            confirmedModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('scbc-modal-open');
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }
            if (modal && modal.getAttribute('aria-hidden') === 'false') {
                closeModal();
            }
            if (confirmedModal && confirmedModal.getAttribute('aria-hidden') === 'false') {
                closeConfirmedModal();
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

        document.addEventListener('click', function (event) {
            var showBtn = event.target.closest('.scbc-confirmed-show');
            if (!showBtn) {
                return;
            }
            openConfirmedModal(showBtn);
        });

        if (confirmedModalClose) {
            confirmedModalClose.addEventListener('click', closeConfirmedModal);
        }
        if (confirmedModal) {
            confirmedModal.addEventListener('click', function (event) {
                if (event.target === confirmedModal) {
                    closeConfirmedModal();
                }
            });
        }

        function formatRelativeStart(startTs, nowTs) {
            var diffSeconds = startTs - nowTs;
            if (diffSeconds > 0) {
                var mins = Math.ceil(diffSeconds / 60);
                if (mins >= 60) {
                    var hours = Math.ceil(mins / 60);
                    return 'Starts in ' + String(hours) + ' hour' + (hours === 1 ? '' : 's');
                }
                return 'Starts in ' + String(mins) + ' minute' + (mins === 1 ? '' : 's');
            }
            var minsAgo = Math.floor(Math.abs(diffSeconds) / 60);
            if (minsAgo >= 60) {
                var hoursAgo = Math.floor(minsAgo / 60);
                return 'Started ' + String(hoursAgo) + ' hour' + (hoursAgo === 1 ? '' : 's') + ' ago';
            }
            return 'Started ' + String(minsAgo) + ' minute' + (minsAgo === 1 ? '' : 's') + ' ago';
        }

        function refreshConfirmedRelativeTimes() {
            var nodes = document.querySelectorAll('.scbc-confirmed-relative[data-start-ts]');
            if (!nodes.length) {
                return;
            }
            var nowTs = Math.floor(Date.now() / 1000);
            nodes.forEach(function (node) {
                var ts = parseInt(node.getAttribute('data-start-ts') || '0', 10);
                if (!ts || isNaN(ts)) {
                    return;
                }
                node.innerHTML = '<strong>' + escapeHtml(formatRelativeStart(ts, nowTs)) + '</strong>';
            });
        }

        refreshConfirmedRelativeTimes();
        window.setInterval(refreshConfirmedRelativeTimes, 60000);

        function getYmdInTimezone(unixTs, timezone) {
            var date = new Date(unixTs * 1000);
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone || 'UTC',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).formatToParts(date);
            var y = '';
            var m = '';
            var d = '';
            parts.forEach(function (part) {
                if (part.type === 'year') { y = part.value; }
                if (part.type === 'month') { m = part.value; }
                if (part.type === 'day') { d = part.value; }
            });
            return y + '-' + m + '-' + d;
        }

        function refreshConfirmedTodayState() {
            var cards = document.querySelectorAll('.scbc-confirmed-card[data-start-ts][data-timezone]');
            if (!cards.length) {
                return;
            }
            var nowTs = Math.floor(Date.now() / 1000);
            var todayUpcomingCount = 0;
            var todayCompletedCount = 0;
            cards.forEach(function (card) {
                var ts = parseInt(card.getAttribute('data-start-ts') || '0', 10);
                var tz = card.getAttribute('data-timezone') || 'UTC';
                if (!ts || isNaN(ts)) {
                    return;
                }
                var startDay = getYmdInTimezone(ts, tz);
                var todayDay = getYmdInTimezone(nowTs, tz);
                var isToday = startDay === todayDay;
                var isUpcoming = ts > nowTs;

                card.classList.toggle('is-upcoming', isUpcoming);
                card.classList.toggle('is-completed', !isUpcoming);
                card.setAttribute('data-is-today', isToday ? '1' : '0');
                card.setAttribute('data-is-upcoming', isUpcoming ? '1' : '0');

                var statusBadge = card.querySelector('.scbc-confirmed-status');
                if (statusBadge) {
                    statusBadge.textContent = isUpcoming ? 'Upcoming' : 'Completed';
                }

                var todayBadge = card.querySelector('.scbc-today-badge');
                if (todayBadge) {
                    todayBadge.hidden = !isToday;
                }

                if (isToday) {
                    if (isUpcoming) {
                        todayUpcomingCount += 1;
                    } else {
                        todayCompletedCount += 1;
                    }
                }
            });

            var dividerUpcoming = document.getElementById('scbc-divider-upcoming');
            if (dividerUpcoming) {
                dividerUpcoming.textContent = 'Today Upcoming (' + String(todayUpcomingCount) + ')';
                dividerUpcoming.hidden = todayUpcomingCount < 1;
            }
            var dividerCompleted = document.getElementById('scbc-divider-completed');
            if (dividerCompleted) {
                dividerCompleted.textContent = 'Today Completed (' + String(todayCompletedCount) + ')';
                dividerCompleted.hidden = todayCompletedCount < 1;
            }
        }

        refreshConfirmedTodayState();
        window.setInterval(refreshConfirmedTodayState, 120000);

        var confirmedRefreshBtn = document.getElementById('scbc-confirmed-refresh');
        var confirmedRefreshedLabel = document.getElementById('scbc-confirmed-refreshed');

        function updateConfirmedRefreshedTime() {
            if (!confirmedRefreshedLabel) {
                return;
            }
            var text = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            confirmedRefreshedLabel.textContent = 'Last refreshed: ' + text;
        }

        function getCurrentSlotsPage() {
            var activePageBtn = paginationWrap ? paginationWrap.querySelector('.scbc-page-btn.is-active') : null;
            if (activePageBtn) {
                var activePage = parseInt(activePageBtn.getAttribute('data-page') || '1', 10);
                if (!isNaN(activePage) && activePage > 0) {
                    return activePage;
                }
            }
            var loadPage = loadMoreBtn ? parseInt(loadMoreBtn.getAttribute('data-page') || '1', 10) : 1;
            if (!isNaN(loadPage) && loadPage > 0) {
                return loadPage;
            }
            return 1;
        }

        if (confirmedRefreshBtn) {
            confirmedRefreshBtn.addEventListener('click', function () {
                confirmedRefreshBtn.disabled = true;
                confirmedRefreshBtn.classList.remove('is-success');
                confirmedRefreshBtn.classList.add('is-loading');
                confirmedRefreshBtn.textContent = 'Refreshing...';
                var currentPage = getCurrentSlotsPage();
                var month = monthFilter ? monthFilter.value : '';
                loadSlots(currentPage, month, false).finally(function () {
                    refreshConfirmedRelativeTimes();
                    refreshConfirmedTodayState();
                    updateConfirmedRefreshedTime();
                    if (confirmedRefreshedLabel) {
                        confirmedRefreshedLabel.classList.add('is-success');
                    }
                    confirmedRefreshBtn.disabled = false;
                    confirmedRefreshBtn.classList.remove('is-loading');
                    confirmedRefreshBtn.classList.add('is-success');
                    confirmedRefreshBtn.textContent = 'Refreshed ✓';
                    window.setTimeout(function () {
                        if (confirmedRefreshedLabel) {
                            confirmedRefreshedLabel.classList.remove('is-success');
                        }
                        confirmedRefreshBtn.classList.remove('is-success');
                        confirmedRefreshBtn.textContent = 'Refresh Now';
                    }, 1300);
                });
            });
        }

        updateConfirmedRefreshedTime();
    });
})();
