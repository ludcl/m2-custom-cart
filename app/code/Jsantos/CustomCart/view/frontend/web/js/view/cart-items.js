
define([
    'ko',
    'Jsantos_CustomCart/js/model/totals',
    'uiComponent'
], function (ko, totals, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Jsantos_CustomCart/cart/cart-items'
        },
        totals: totals.totals(),
        items: ko.observable([]),
        cartUrl: window.checkoutConfig.cartUrl,

        /**
         * Returns cart items qty
         *
         * @returns {Number}
         */
        getItemsQty: function () {
            return parseFloat(this.totals['items_qty']);
        },

        /**
         * Returns count of cart line items
         *
         * @returns {Number}
         */
        getCartLineItemsCount: function () {
            return parseInt(totals.getItems()().length, 10);
        },

        /**
         * Returns shopping cart items summary (includes config settings)
         *
         * @returns {Number}
         */
        getCartSummaryItemsCount: function () {
            return this.getItemsQty();
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            this._super();
            // Set initial items to observable field
            this.setItems(totals.getItems()());
            // Subscribe for items data changes and refresh items in view
            totals.getItems().subscribe(function (items) {
                this.setItems(items);
            }.bind(this));
        },

        /**
         * Set items to observable field
         *
         * @param {Object} items
         */
        setItems: function (items) {
            this.items(items);
        }
    });
});
