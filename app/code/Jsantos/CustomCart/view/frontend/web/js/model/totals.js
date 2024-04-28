/**
 * @api
 */
define([
    'ko',
    'Magento_Customer/js/customer-data'
], function (ko, customerData) {
    'use strict';

    let quoteItems = ko.observable(customerData.get(['customcart-data'])().items ?? []),
        cartData = customerData.get('customcart-data'),
        quote = {
            'totals': customerData.get(['customcart-data'])
        },
        quoteSubtotal = parseFloat(quote.totals().subtotal),
        subtotalAmount = parseFloat(cartData().subtotalAmount);

    quote.totals.subscribe(function (newValue) {
        quoteItems(newValue.items);
    });

    if (!isNaN(subtotalAmount) && quoteSubtotal !== subtotalAmount && quoteSubtotal !== 0) {
        customerData.reload(['customcart-data'], false);
    }

    return {
        totals: quote.totals,
        isLoading: ko.observable(false),

        /**
         * @return {Function}
         */
        getItems: function () {
            return quoteItems;
        },

        /**
         * @param {*} code
         * @return {*}
         */
        getSegment: function (code) {
            return null;
        }
    };
});
