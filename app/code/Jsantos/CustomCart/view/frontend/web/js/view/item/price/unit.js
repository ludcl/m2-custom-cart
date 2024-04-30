
define([
    'uiComponent',
    'Magento_Catalog/js/price-utils'
], function (Component, priceUtils) {
    'use strict';

    return Component.extend({

        displayArea: 'unit_price',

        /**
         * @param {*} price
         * @return {*|String}
         */
        getFormattedPrice: function (price) {
            return 'CA$' + price.toFixed(2);
        },

        /**
         * @param {Object} quoteItem
         * @return {*|String}
         */
        getValue: function (quoteItem) {
            return this.getFormattedPrice(parseFloat(quoteItem.product_price));
        }
    });
});
