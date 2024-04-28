
define([
    'uiComponent',
    'Magento_Catalog/js/price-utils'
], function (Component, priceUtils) {
    'use strict';

    return Component.extend({

        displayArea: 'row_price',

        /**
         * @param {*} price
         * @return {*|String}
         */
        getFormattedPrice: function (price) {
            return price;//priceUtils.formatPrice(price, quote.getPriceFormat());
        },

        /**
         * @param {Object} quoteItem
         * @return {*|String}
         */
        getValue: function (quoteItem) {
            return this.getFormattedPrice(quoteItem.product_price * quoteItem.qty);
        }
    });
});
