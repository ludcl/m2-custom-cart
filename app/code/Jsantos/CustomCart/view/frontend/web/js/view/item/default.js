
define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data',
    'Jsantos_CustomCart/js/action/update-cart',
], function (
    Component,
    ko,
    $,
    customerData,
    updateCartAction,
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Jsantos_CustomCart/cart/item/default'
        },
        quoteItems: [],

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            let quoteItemsTemp = customerData.get(['customcart-data'])().items ?? [];

            for (let i=0; i < quoteItemsTemp.length; i++) {
                let itemId = quoteItemsTemp[i].item_id;

                this.quoteItems[itemId] = quoteItemsTemp[i];
                this.quoteItems[itemId].qty = ko.observable(quoteItemsTemp[i].qty);

                this.quoteItems[itemId].qty.subscribe(function (newQtyValue) {
                    let data = {'cart': {[itemId] : {'qty' : newQtyValue}}};
                    let deferred = $.Deferred();

                    updateCartAction(data, deferred);
                    $.when(deferred).done(function () {
                        customerData.invalidate(['customcart-data']);
                        customerData.reload(['customcart-data'], true);
                    });

                });
            }
        },

        /**
         * @param {String} quoteItemId
         * @return {Object}
         */
        getQuoteItem: function(quoteItemId) {
            return this.quoteItems[quoteItemId];
        },

        /**
         * @param {Object} quoteItem
         * @return {String}
         */
        getName: function (item) {
            return item.product_name;
        },

        getId: function (item) {
            return item.item_id;
        },

        checkQtyField: function(data, event) {
            return /^[0-9]*$/.test(event.key);
        },

        getQty: function (itemId) {
            return  this.getQuoteItem(itemId).qty;
        },

        hasProductUrl: function (item_id) {
            return this.getQuoteItem(item_id).product_has_url;
        },

        getProductUrl: function (item_id) {
            return this.getQuoteItem(item_id).product_url;
        },

        /**
         * @param {String} item_id
         * @return {String}
         */
        getProductImageUrl: function (item_id) {
            return this.getQuoteItem(item_id)?.product_image?.src ?? '';
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getWidth: function (item) {
            return 150;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getHeight: function (item) {
            return 150;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getMessage: function (item) {
            return null;
        }
    });
});
