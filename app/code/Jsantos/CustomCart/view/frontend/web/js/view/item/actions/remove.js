
define([
    'uiComponent',
    'jquery',
    'Jsantos_CustomCart/js/action/update-cart',
    'Magento_Customer/js/customer-data',
], function (
    Component,
    $,
    updateCartAction,
    customerData,
) {
    'use strict';

    return Component.extend({

        displayArea: 'row_actions',

        removeItem: function (item_id) {
            let data = {'cart': {[item_id] : {'remove' : true }}};
            let deferred = $.Deferred();

            updateCartAction(data, deferred);
            $.when(deferred).done(function () {
                customerData.invalidate(['customcart-data']);
                customerData.reload(['customcart-data'], true);
            });
        }
    });
});
