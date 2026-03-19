/**
 * Auction bid UI - place bid, quick buttons, max bid, refresh history
 */
(function($) {
    'use strict';

    function formatPrice(amount) {
        if (typeof wc_price !== 'undefined') {
            return wc_price(amount);
        }
        return '$' + parseFloat(amount).toFixed(2);
    }

    function init() {
        var $wrapper = $('.azure-auction-bid-wrapper');
        if (!$wrapper.length || typeof azureAuction === 'undefined') return;

        var productId = azureAuction.productId || $wrapper.data('product-id');
        var $amount = $('.auction-bid-amount');
        var $priceVal = $('.auction-price-value');
        var $list = $('.auction-bid-list');
        var $noBids = $('.no-bids');
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
            $list.empty();
            if (!bids || !bids.length) {
                $noBids.show();
                return;
            }
            $noBids.hide();
            bids.forEach(function(b) {
                var time = b.time || '';
                var line = (b.bidder || '***') + ' — ' + formatPrice(b.amount);
                if (time) line += ' <span class="bid-time">' + time + '</span>';
                $list.append($('<li>').html(line));
            });
        }

        function setPrice(price) {
            $priceVal.html(formatPrice(price));
        }

        $('.auction-buy-it-now-btn').on('click', function() {
            var $btn = $(this);
            if (typeof azureAuction === 'undefined' || !azureAuction.i18n || !confirm(azureAuction.i18n.buyItNowConfirm || 'Create order and go to checkout?')) {
                return;
            }
            $btn.prop('disabled', true);
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

        $placeBtn.on('click', function() {
            var isMax = $useMax.is(':checked');
            var amount = parseFloat($amount.val());
            var maxBid = parseFloat($maxAmount.val());
            if (isMax && (isNaN(maxBid) || maxBid <= 0)) {
                $msg.css('color', '#c00').text('Please enter a max bid.').show();
                return;
            }
            if (!isMax && (isNaN(amount) || amount <= 0)) {
                $msg.css('color', '#c00').text('Please enter a bid amount.').show();
                return;
            }

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
                        $msg.css('color', '#0a0').text('Bid placed.').show();
                        if (getCurrentPrice() > 0) {
                            $amount.val((getCurrentPrice() + 5).toFixed(2));
                        }
                    } else {
                        $msg.css('color', '#c00').text(res.data && res.data.message ? res.data.message : 'Bid failed.').show();
                    }
                })
                .fail(function() {
                    $msg.css('color', '#c00').text('Network error.').show();
                })
                .always(function() {
                    $placeBtn.prop('disabled', false);
                });
        });
    }

    $(function() {
        init();
    });
})(jQuery);
