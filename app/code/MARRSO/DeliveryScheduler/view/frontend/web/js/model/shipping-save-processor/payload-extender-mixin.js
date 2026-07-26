define([
    'Magento_Checkout/js/model/quote'
], function (quote) {
    'use strict';

    return function (payloadExtender) {
        return function (payload) {
            payloadExtender(payload);

            var moduleConfig = window.checkoutConfig.marrsoDeliveryScheduler || {};
            if (!moduleConfig.enabled || !payload.addressInformation) {
                return;
            }

            var shippingAddress = quote.shippingAddress && quote.shippingAddress();
            if (!shippingAddress) {
                return;
            }

            payload.addressInformation.shipping_address = payload.addressInformation.shipping_address || {};
            payload.addressInformation.shipping_address.extension_attributes =
                payload.addressInformation.shipping_address.extension_attributes || {};

            var source = shippingAddress.extensionAttributes || shippingAddress;
            var target = payload.addressInformation.shipping_address.extension_attributes;
            var fields = [
                'delivery_type',
                'pickup_location_id',
                'delivery_date',
                'delivery_slot',
                'delivery_instructions',
                'service_level'
            ];

            fields.forEach(function (field) {
                var value = source[field];

                if ((value === null || value === undefined || value === '') && shippingAddress['marrso_' + field] !== undefined) {
                    value = shippingAddress['marrso_' + field];
                }

                if (value !== null && value !== undefined && value !== '') {
                    target[field] = value;
                }
            });
        };
    };
});
