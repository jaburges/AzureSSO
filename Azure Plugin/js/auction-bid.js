/**
 * Auction bid UI - place bid, quick buttons, max bid, countdown, confirm modal
 */
(function($) {
    'use strict';

    function formatPrice(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    }

    function initCountdown() {
        var $cd = $('.auction-countdown');
        if (!$cd.length) return;

        var endTs = parseInt($cd.data('end'), 10);
        if (!endTs) return;

        var $timer = $cd.find('.auction-countdown-timer');

        function update() {
            var now = Math.floor(Date.now() / 1000);
            var diff = endTs - now;
            if (diff <= 0) {
                $timer.text('Ended').addClass('ending-soon');
                return;
            }
            var days = Math.floor(diff / 86400);
            var hours = Math.floor((diff % 86400) / 3600);
            var mins = Math.floor((diff % 3600) / 60);
            var secs = diff % 60;
            var parts = [];
            if (days > 0) parts.push(days + 'd');
            if (hours > 0 || days > 0) parts.push(hours + 'h');
            parts.push(mins + 'm');
            parts.push(secs + 's');
            $timer.text(parts.join(' '));
            if (diff < 3600) {
                $timer.addClass('ending-soon');
            }
            setTimeout(update, 1000);
        }
        update();
    }

    function showConfirmModal(amount, isMax, onConfirm) {
        var label = isMax ? 'Max bid' : 'Bid';
        var $overlay = $('<div class="auction-confirm-overlay">');
        var $box = $(
            '<div class="auction-confirm-box">' +
                '<h3>Confirm your ' + label.toLowerCase() + '</h3>' +
                '<div class="confirm-amount">' + formatPrice(amount) + '</div>' +
                '<p class="confirm-desc">This action cannot be undone.</p>' +
                '<div class="auction-confirm-actions">' +
                    '<button type="button" class="auction-confirm-yes">Confirm ' + label + '</button>' +
                    '<button type="button" class="auction-confirm-no">Cancel</button>' +
                '</div>' +
            '</div>'
        );
        $overlay.append($box);
        $('body').append($overlay);

        $overlay.on('click', '.auction-confirm-yes', function() {
            $overlay.remove();
            onConfirm();
        });
        $overlay.on('click', '.auction-confirm-no', function() {
            $overlay.remove();
        });
        $overlay.on('click', function(e) {
            if (e.target === $overlay[0]) $overlay.remove();
        });
    }

    function init() {
        var $wrapper = $('.azure-auction-bid-wrapper');
        if (!$wrapper.length || typeof azureAuction === 'undefined') return;

        var productId = azureAuction.productId || $wrapper.data('product-id');
        var $amount = $('.auction-bid-amount');
        var $priceVal = $('.auction-price-value');
        var $bidBody = $('.auction-bid-list');
        var $noBids = $('.no-bids');
        var $bidTable = $('.auction-bid-table');
        var $msg = $('.auction-bid-message');
        var $placeBtn = $('.auction-place-bid');
        var $useMax = $('.auction-use-max-bid');
        var $maxAmount = $('.auction-max-bid-amount');

        function getCurrentPrice() {
            var text = $priceVal.text().replace(/[^0-9.]/g, '');
            return parseFloat(text) || 0;
        }

        $('.auction-quick-bid').on('click', function() {
            var inc = parseFloat($(this).data('increment')) || 0;
            var cur = getCurrentPrice();
            $amount.val((cur + inc).toFixed(2));
        });

        $useMax.on('change', function() {
            $maxAmount.toggle($useMax.is(':checked'));
            if ($useMax.is(':checked') && !$maxAmount.val()) {
                $maxAmount.val($amount.val());
            }
        });

        function renderBids(bids) {
            $bidBody.empty();
            if (!bids || !bids.length) {
                $bidTable.hide();
                $noBids.show();
                return;
            }
            $noBids.hide();
            $bidTable.show();
            bids.forEach(function(b) {
                var $row = $('<tr>');
                $row.append($('<td>').text(b.bidder || '***'));
                $row.append($('<td>').html(formatPrice(b.amount)));
                $row.append($('<td class="bid-time">').text(b.time || ''));
                $bidBody.append($row);
            });
        }

        function setPrice(price) {
            $priceVal.html(formatPrice(price));
        }

        $('.auction-buy-it-now-btn').on('click', function() {
            var $btn = $(this);
            if (!confirm(azureAuction.i18n.buyItNowConfirm || 'Create order and go to checkout?')) {
                return;
            }
            $btn.prop('disabled', true).text('Processing...');
            $.post(azureAuction.ajaxurl, {
                action: 'azure_auction_buy_it_now',
                nonce: azureAuction.nonce,
                product_id: productId
            })
                .done(function(res) {
                    if (res.success && res.data && res.data.checkout_url) {
                        window.location.href = res.data.checkout_url;
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Could not create order.');
                        $btn.prop('disabled', false);
                    }
                })
                .fail(function() {
                    alert('Network error.');
                    $btn.prop('disabled', false);
                });
        });

        function submitBid(isMax, amount, maxBid) {
            $placeBtn.prop('disabled', true);
            $msg.hide();

            var data = {
                action: 'azure_auction_place_bid',
                nonce: azureAuction.nonce,
                product_id: productId,
                is_max_bid: isMax ? '1' : '0'
            };
            if (isMax) {
                data.max_bid = maxBid;
                data.amount = maxBid;
            } else {
                data.amount = amount;
            }

            $.post(azureAuction.ajaxurl, data)
                .done(function(res) {
                    if (res.success && res.data) {
                        setPrice(res.data.current_price);
                        renderBids(res.data.bids || []);
                        $msg.removeClass('error').addClass('success').text('Bid placed!').show();
                        var newPrice = res.data.current_price || getCurrentPrice();
                        if (newPrice > 0) {
                            $amount.val((newPrice + 5).toFixed(2));
                        }
                        $('.auction-price-label').text('Current bid:');
                        setTimeout(function() { $msg.fadeOut(); }, 4000);
                    } else {
                        $msg.removeClass('success').addClass('error')
                            .text(res.data && res.data.message ? res.data.message : 'Bid failed.').show();
                    }
                })
                .fail(function() {
                    $msg.removeClass('success').addClass('error').text('Network error.').show();
                })
                .always(function() {
                    $placeBtn.prop('disabled', false);
                });
        }

        $placeBtn.on('click', function() {
            var isMax = $useMax.is(':checked');
            var amount = parseFloat($amount.val());
            var maxBid = parseFloat($maxAmount.val());

            if (isMax && (isNaN(maxBid) || maxBid <= 0)) {
                $msg.removeClass('success').addClass('error').text('Please enter a max bid.').show();
                return;
            }
            if (!isMax && (isNaN(amount) || amount <= 0)) {
                $msg.removeClass('success').addClass('error').text('Please enter a bid amount.').show();
                return;
            }

            var confirmAmount = isMax ? maxBid : amount;
            showConfirmModal(confirmAmount, isMax, function() {
                submitBid(isMax, amount, maxBid);
            });
        });
    }

    $(function() {
        initCountdown();
        init();
    });
})(jQuery);
