define([
    'jquery',
    'ko',
    'uiComponent',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'mage/storage',
    'mage/url',
    'mage/translate'
], function ($, ko, Component, registry, quote, priceUtils, storage, urlBuilder, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'MARRSO_DeliveryScheduler/express-checkout',
            expressCheckout: {}
        },

        isSaving: ko.observable(false),
        errorMessage: ko.observable(''),
        firstname: ko.observable(''),
        lastname: ko.observable(''),
        email: ko.observable(''),
        street: ko.observable(''),
        city: ko.observable('Lima'),
        postcode: ko.observable('15001'),
        telephone: ko.observable(''),
        countryId: ko.observable('PE'),
        expressConfig: {},

        initialize: function () {
            this._super();
            this.expressConfig = this.expressCheckout || {};

            this.restoreAddress(this.expressConfig.savedAddress || {});

            if (!this.expressConfig.isGuest && this.expressConfig.customerEmail) {
                this.email(this.expressConfig.customerEmail);
            }

            this.firstname.subscribe(this.syncQuoteAddress.bind(this));
            this.lastname.subscribe(this.syncQuoteAddress.bind(this));
            this.street.subscribe(this.syncQuoteAddress.bind(this));
            this.city.subscribe(this.syncQuoteAddress.bind(this));
            this.postcode.subscribe(this.syncQuoteAddress.bind(this));
            this.telephone.subscribe(this.syncQuoteAddress.bind(this));

            this.syncQuoteAddress();
        },

        restoreAddress: function (saved) {
            if (!saved) {
                return;
            }

            this.firstname(saved.firstname || '');
            this.lastname(saved.lastname || '');
            this.street(saved.street || '');
            this.city(saved.city || 'Lima');
            this.postcode(saved.postcode || '15001');
            this.telephone(saved.telephone || '');
            this.countryId(saved.country_id || 'PE');
        },

        syncQuoteAddress: function () {
            if (!quote.shippingAddress) {
                return;
            }

            quote.shippingAddress({
                firstname: this.firstname(),
                lastname: this.lastname(),
                street: [this.street()],
                city: this.city(),
                postcode: this.postcode(),
                countryId: this.countryId(),
                telephone: this.telephone(),
                email: this.email()
            });
        },

        formatPrice: function (amount) {
            var format = window.checkoutConfig && window.checkoutConfig.priceFormat
                ? window.checkoutConfig.priceFormat
                : {
                    pattern: 'S/ %s',
                    precision: 2,
                    requiredPrecision: 2,
                    decimalSymbol: '.',
                    groupSymbol: ',',
                    groupLength: 3,
                    integerRequired: false
                };

            return priceUtils.formatPrice(parseFloat(amount || 0), format);
        },

        getDeliveryComponent: function () {
            return registry.get('marrso-express-checkout.marrso-delivery-scheduler');
        },

        validateAddress: function () {
            if (!this.firstname().trim() || !this.lastname().trim()) {
                this.errorMessage($t('Please enter your first and last name.'));
                return false;
            }

            if (this.expressConfig.isGuest && !this.email().trim()) {
                this.errorMessage($t('Please enter your email address.'));
                return false;
            }

            if (!this.telephone().trim()) {
                this.errorMessage($t('Please enter your phone number.'));
                return false;
            }

            var deliveryComponent = this.getDeliveryComponent();

            if (deliveryComponent && deliveryComponent.activeMethod() !== 'pickup') {
                if (!this.street().trim()) {
                    this.errorMessage($t('Please enter your street address.'));
                    return false;
                }

                if (!this.city().trim()) {
                    this.errorMessage($t('Please enter your city.'));
                    return false;
                }
            }

            return true;
        },

        validateDeliverySelection: function () {
            var deliveryComponent = this.getDeliveryComponent();

            if (!deliveryComponent || typeof deliveryComponent.isValid !== 'function') {
                return true;
            }

            if (typeof deliveryComponent.ensurePickupSelection === 'function') {
                deliveryComponent.ensurePickupSelection();
            }

            if (!deliveryComponent.isValid()) {
                deliveryComponent.errorMessage($t('Please select a pickup point or delivery slot.'));
                this.errorMessage($t('Please select a pickup point or delivery slot.'));
                return false;
            }

            return true;
        },

        buildShippingPayload: function (deliveryComponent) {
            var carrierCode = 'flatrate';
            var methodCode = 'flatrate';
            var extensionAttributes = {};
            var deliveryType = 'delivery';
            var slot = null;

            if (deliveryComponent) {
                deliveryType = deliveryComponent.activeMethod() === 'pickup' ? 'pickup' : 'delivery';
                slot = deliveryType === 'pickup'
                    ? deliveryComponent.selectedPickupSlot()
                    : deliveryComponent.selectedDeliverySlot();

                if (slot && slot.carrier_code) {
                    carrierCode = slot.carrier_code;
                    methodCode = slot.carrier_code;
                }

                var pickupId = deliveryType === 'pickup' && deliveryComponent.selectedPickupLocation()
                    ? deliveryComponent.selectedPickupLocation().entity_id
                    : null;
                var slotRange = slot
                    ? deliveryComponent.buildSlotRange(slot.start_time, slot.end_time)
                    : null;

                extensionAttributes = {
                    delivery_type: deliveryType,
                    pickup_location_id: pickupId,
                    delivery_date: slot ? slot.date : '',
                    delivery_slot: slotRange,
                    delivery_instructions: deliveryComponent.deliveryInstructions(),
                    service_level: deliveryType === 'pickup' ? null : deliveryComponent.activeMethod()
                };
            }

            var shippingAddress = {
                firstname: this.firstname(),
                lastname: this.lastname(),
                street: [this.street()],
                city: this.city(),
                postcode: this.postcode(),
                country_id: this.countryId(),
                telephone: this.telephone(),
                email: this.email(),
                extension_attributes: extensionAttributes
            };

            return {
                addressInformation: {
                    shipping_address: shippingAddress,
                    billing_address: $.extend(true, {}, shippingAddress),
                    shipping_carrier_code: carrierCode,
                    shipping_method_code: methodCode
                }
            };
        },

        buildDeliverySavePayload: function (deliveryComponent) {
            var quoteId = window.checkoutConfig.quoteData
                ? (window.checkoutConfig.quoteData.entity_id || window.checkoutConfig.quoteData.quote_id)
                : null;
            var deliveryType = deliveryComponent.activeMethod() === 'pickup' ? 'pickup' : 'delivery';
            var slot = deliveryType === 'pickup'
                ? deliveryComponent.selectedPickupSlot()
                : deliveryComponent.selectedDeliverySlot();
            var pickupId = deliveryType === 'pickup' && deliveryComponent.selectedPickupLocation()
                ? deliveryComponent.selectedPickupLocation().entity_id
                : null;
            var slotRange = slot
                ? deliveryComponent.buildSlotRange(slot.start_time, slot.end_time)
                : null;

            return {
                deliverySelection: {
                    quote_id: quoteId,
                    delivery_type: deliveryType,
                    delivery_date: slot ? slot.date : '',
                    pickup_location_id: pickupId,
                    delivery_slot: slotRange,
                    service_level: deliveryType === 'pickup' ? null : deliveryComponent.activeMethod(),
                    delivery_price: slot ? parseFloat(slot.price || 0) : 0,
                    carrier_code: slot && slot.carrier_code ? slot.carrier_code : 'flatrate',
                    customer_comment: deliveryComponent.deliveryInstructions(),
                    district: deliveryComponent.customerDistrict() || this.city()
                }
            };
        },

        getShippingInformationUrl: function () {
            if (this.expressConfig.isGuest) {
                return urlBuilder.build(
                    'rest/V1/guest-carts/' + this.expressConfig.maskedQuoteId + '/shipping-information'
                );
            }

            return urlBuilder.build('rest/V1/carts/mine/shipping-information');
        },

        continueToPayment: function () {
            var self = this;

            this.errorMessage('');

            if (!this.validateAddress() || !this.validateDeliverySelection()) {
                return;
            }

            var deliveryComponent = this.getDeliveryComponent();

            if (this.expressConfig.isGuest && !this.expressConfig.maskedQuoteId) {
                this.errorMessage($t('Unable to determine guest cart id.'));
                return;
            }

            this.isSaving(true);

            var shippingPayload = this.buildShippingPayload(deliveryComponent);
            var deliveryPayload = this.buildDeliverySavePayload(deliveryComponent);

            storage.post(
                this.getShippingInformationUrl(),
                JSON.stringify(shippingPayload),
                true,
                'application/json'
            ).done(function () {
                return storage.post(
                    urlBuilder.build('rest/V1/delivery/save'),
                    JSON.stringify(deliveryPayload),
                    true,
                    'application/json'
                );
            }).done(function () {
                window.location.href = self.expressConfig.checkoutUrl + '#payment';
            }).fail(function (response) {
                var message = $t('Unable to continue to payment. Please check your details and try again.');

                if (response && response.responseJSON && response.responseJSON.message) {
                    message = response.responseJSON.message;
                }

                self.errorMessage(message);
            }).always(function () {
                self.isSaving(false);
            });
        }
    });
});
