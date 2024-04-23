
define([
    'Jsantos_CustomCart/js/view/cart-items',
    'jquery',
    'Jsantos_CustomCart/js/action/update-cart',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/get-totals',
], function (
    Component,
    $,
    updateCartAction,
    customerData,
    getTotals,
) {
    'use strict';

    return Component.extend({
        emptyCart: function (eventData) {  //eslint-disable-line no-unused-vars
            let data = {'cart': {'empty_cart' : true }};
            let deferred = $.Deferred();

            updateCartAction(data, deferred);
            $.when(deferred).done(function () {
                customerData.invalidate(['cart']);
                customerData.reload(['cart'], true);
                getTotals([]);
            });
        }
    });
});
