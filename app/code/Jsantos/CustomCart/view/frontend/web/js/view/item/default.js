
define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data',
    'Jsantos_CustomCart/js/action/update-cart',
    'Magento_Checkout/js/action/get-totals',
], function (
    Component,
    ko,
    $,
    customerData,
    updateCartAction,
    getTotals,
) {
    'use strict';

    let imageData = window.checkoutConfig.cartImageData;
    let quoteMessages = window.checkoutConfig.quoteMessages;

    return Component.extend({
        defaults: {
            template: 'Jsantos_CustomCart/cart/item/default'
        },
        imageData: imageData,
        quoteMessages: quoteMessages,
        quoteItems: [],

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();

            let cartItems = customerData.get(['customcart-data'])().items;
            let quoteItemsTemp = cartItems;

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
                        getTotals([]);
                    });

                });
            }

            /*for (let i=0; i < cartItems.length; i++) {
                this.quoteItems[cartItems[i].item_id].product_has_url = cartItems[i].product_has_url;
                this.quoteItems[cartItems[i].item_id].product_url = cartItems[i].product_url;
                this.quoteItems[cartItems[i].item_id].product_image = cartItems[i].product_image;
            }*/
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
         * @param {Object} item
         * @return {Array}
         */
        getImageItem: function (item) {
            if (this.imageData[item['item_id']]) {
                return this.imageData[item['item_id']];
            }
            return [];
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
        getSrc: function (item) {
            if (this.imageData[item['item_id']]) {
                return this.imageData[item['item_id']].src;
            }
            return null;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getWidth: function (item) {
            if (this.imageData[item['item_id']]) {
                return this.imageData[item['item_id']].width;
            }
            return null;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getHeight: function (item) {
            if (this.imageData[item['item_id']]) {
                return this.imageData[item['item_id']].height;
            }
            return null;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getAlt: function (item) {
            if (this.imageData[item['item_id']]) {
                return this.imageData[item['item_id']].alt;
            }
            return null;
        },

        /**
         * @param {Object} item
         * @return {null}
         */
        getMessage: function (item) {
            if (this.quoteMessages[item['item_id']]) {
                return this.quoteMessages[item['item_id']];
            }
            return null;
        }
    });
});
