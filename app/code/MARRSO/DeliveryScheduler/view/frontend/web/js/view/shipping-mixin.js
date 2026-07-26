define([
    'uiRegistry',
    'mage/translate'
], function (registry, $t) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            getDeliverySchedulerComponent: function () {
                return registry.get(
                    'checkout.steps.shipping-step.shippingAddress.before-shipping-method-form.marrso-delivery-scheduler'
                ) || registry.get('checkout.steps.shipping-step.marrso-delivery-scheduler');
            },

            validateShippingInformation: function () {
                if (!this._super()) {
                    return false;
                }

                var moduleConfig = window.checkoutConfig.marrsoDeliveryScheduler || {};

                if (moduleConfig.enabled === false) {
                    return true;
                }

                var component = this.getDeliverySchedulerComponent();

                if (!component || typeof component.isValid !== 'function') {
                    return true;
                }

                if (typeof component.ensurePickupSelection === 'function') {
                    component.ensurePickupSelection();
                }

                if (!component.isValid()) {
                    if (typeof component.errorMessage === 'function') {
                        component.errorMessage($t('Please select a pickup point or delivery slot.'));
                    }

                    return false;
                }

                return true;
            }
        });
    };
});
