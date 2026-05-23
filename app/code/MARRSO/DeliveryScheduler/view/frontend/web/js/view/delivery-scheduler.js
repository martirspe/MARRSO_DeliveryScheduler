define([
    'jquery',
    'knockout',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/step-navigator',
    'Magento_Ui/js/form/form',
    'mage/storage',
    'mage/url',
    'mageTemplate',
    'text!MARRSO_DeliveryScheduler/template/delivery-scheduler.html'
], function (
    $,
    ko,
    Component,
    quote,
    stepNavigator,
    Form,
    storage,
    urlBuilder,
    mageTemplate,
    deliverySchedulerTemplate
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'MARRSO_DeliveryScheduler/delivery-scheduler'
        },

        isLoading: ko.observable(false),
        activeTab: ko.observable('pickup'),
        pickupLocations: ko.observableArray([]),
        deliverySlots: ko.observableArray([]),
        selectedPickupLocation: ko.observable(null),
        selectedDeliverySlot: ko.observable(null),
        customerDistrict: ko.observable(''),
        deliveryInstructions: ko.observable(''),
        errorMessage: ko.observable(''),

        initialize: function () {
            this._super();

            if (quote.shippingAddress) {
                quote.shippingAddress.subscribe(this.onAddressChange.bind(this));
            }

            var initialAddress = quote.shippingAddress && quote.shippingAddress();
            this.restoreSelectionFromAddress(initialAddress);

            if (!initialAddress) {
                return;
            }

            var initialTab = this.getAddressValue(initialAddress, 'delivery_type') === 'delivery' ? 'delivery' : 'pickup';
            this.activeTab(initialTab);

            if (initialTab === 'pickup') {
                this.loadPickupLocations(function () {
                    this.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
                }.bind(this));
                return;
            }

            this.loadDeliverySlots(function () {
                this.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
            }.bind(this));
        },

        loadPickupLocations: function (callback) {
            var self = this;
            this.isLoading(true);

            var url = urlBuilder.build('rest/V1/delivery/pickup-locations');
            var params = {};

            if (this.customerDistrict()) {
                params.district = this.customerDistrict();
            }

            storage.get(url, params).done(function (response) {
                self.pickupLocations(response.items || response);
                self.isLoading(false);

                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function (error) {
                console.error('Error loading pickup locations:', error);
                self.errorMessage('Failed to load pickup locations');
                self.isLoading(false);
            });
        },

        loadDeliverySlots: function (callback) {
            var self = this;
            this.isLoading(true);

            var url = urlBuilder.build('rest/V1/delivery/delivery-slots');
            var params = {};

            if (this.customerDistrict()) {
                params.district = this.customerDistrict();
            }

            storage.get(url, params).done(function (response) {
                self.deliverySlots(response.items || response);
                self.isLoading(false);

                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function (error) {
                console.error('Error loading delivery slots:', error);
                self.errorMessage('Failed to load delivery slots');
                self.isLoading(false);
            });
        },

        selectPickupLocation: function (location) {
            this.selectedPickupLocation(location);
            this.saveSelection('pickup', location.entity_id);
        },

        selectDeliverySlot: function (slot) {
            this.selectedDeliverySlot(slot);
            this.saveSelection('delivery', null, slot);
        },

        saveSelection: function (deliveryType, pickupLocationId, deliverySlot) {
            var self = this;

            if (!quote.shippingAddress()) {
                return;
            }

            var quoteId = this.getQuoteId();
            if (!quoteId) {
                this.errorMessage('Unable to determine quote id');
                return;
            }

            var url = urlBuilder.build('rest/V1/delivery/save');
            var payload = {
                deliverySelection: {
                    quote_id: quoteId,
                    delivery_type: deliveryType,
                    delivery_date: deliverySlot ? deliverySlot.date : (this.selectedPickupLocation() ? this.selectedPickupLocation().slots[0].date : ''),
                    pickup_location_id: pickupLocationId,
                    delivery_slot: deliverySlot ? (deliverySlot.start_time + '-' + deliverySlot.end_time) : null,
                    customer_comment: this.deliveryInstructions()
                }
            };

            storage.post(url, JSON.stringify(payload)).done(function () {
                if (quote.shippingAddress()) {
                    quote.shippingAddress()['custom_delivery_type'] = deliveryType;
                    quote.shippingAddress()['custom_pickup_location_id'] = pickupLocationId;
                    quote.shippingAddress()['custom_delivery_date'] = payload.deliverySelection.delivery_date;
                    quote.shippingAddress()['custom_delivery_slot'] = payload.deliverySelection.delivery_slot;
                    quote.shippingAddress()['custom_delivery_instructions'] = payload.deliverySelection.customer_comment;
                }
                self.errorMessage('');
            }).fail(function (error) {
                console.error('Error saving delivery selection:', error);
                self.errorMessage('Failed to save delivery selection');
            });
        },

        getSelectedDeliveryType: function () {
            if (this.selectedPickupLocation()) {
                return 'pickup';
            }

            if (this.selectedDeliverySlot()) {
                return 'delivery';
            }

            return this.activeTab() === 'delivery' ? 'delivery' : 'pickup';
        },

        saveComment: function () {
            if (!this.selectedPickupLocation() && !this.selectedDeliverySlot()) {
                return;
            }

            var deliveryType = this.getSelectedDeliveryType();
            var pickupLocationId = this.selectedPickupLocation() ? this.selectedPickupLocation().entity_id : null;
            var selectedSlot = this.selectedDeliverySlot();

            this.saveSelection(deliveryType, pickupLocationId, selectedSlot);
        },

        getQuoteId: function () {
            if (typeof quote.getQuoteId === 'function') {
                return quote.getQuoteId();
            }

            if (window.checkoutConfig && window.checkoutConfig.quoteData) {
                return window.checkoutConfig.quoteData.entity_id || window.checkoutConfig.quoteData.id;
            }

            return null;
        },

        getAddressValue: function (address, field) {
            if (!address) {
                return null;
            }

            var value = null;

            if (typeof address.getData === 'function') {
                value = address.getData(field);
                if (value === null || value === undefined || value === '') {
                    value = address.getData('custom_' + field);
                }
            } else {
                value = address[field];
                if (value === null || value === undefined || value === '') {
                    value = address['custom_' + field];
                }
            }

            if (value === null || value === undefined || value === '') {
                if (typeof address.getExtensionAttributes === 'function') {
                    var extensionAttributes = address.getExtensionAttributes();
                    if (extensionAttributes && typeof extensionAttributes[field] !== 'undefined') {
                        value = extensionAttributes[field];
                    }
                }
            }

            return value === undefined || value === null ? null : value;
        },

        restoreSelectionFromAddress: function (address) {
            if (!address) {
                return;
            }

            var deliveryType = this.getAddressValue(address, 'delivery_type');
            var pickupLocationId = this.getAddressValue(address, 'pickup_location_id');
            var deliverySlot = this.getAddressValue(address, 'delivery_slot');
            var deliveryInstructions = this.getAddressValue(address, 'delivery_instructions');

            if (deliveryInstructions !== null) {
                this.deliveryInstructions(deliveryInstructions);
            }

            if (deliveryType === 'pickup') {
                this.activeTab('pickup');
                this.selectedDeliverySlot(null);

                if (pickupLocationId !== null) {
                    var matchedPickup = ko.utils.arrayFirst(this.pickupLocations(), function (location) {
                        return String(location.entity_id) === String(pickupLocationId);
                    });
                    this.selectedPickupLocation(matchedPickup || null);
                } else {
                    this.selectedPickupLocation(null);
                }

                return;
            }

            if (deliveryType === 'delivery') {
                this.activeTab('delivery');
                this.selectedPickupLocation(null);

                if (deliverySlot !== null) {
                    var slotParts = String(deliverySlot).split('-');
                    var matchedSlot = ko.utils.arrayFirst(this.deliverySlots(), function (slot) {
                        if (!slot.start_time || !slot.end_time || slotParts.length !== 2) {
                            return false;
                        }

                        return slot.start_time.substring(0, 5) === slotParts[0].substring(0, 5)
                            && slot.end_time.substring(0, 5) === slotParts[1].substring(0, 5);
                    });
                    this.selectedDeliverySlot(matchedSlot || null);
                } else {
                    this.selectedDeliverySlot(null);
                }

                return;
            }

            this.selectedPickupLocation(null);
            this.selectedDeliverySlot(null);
        },

        onAddressChange: function (address) {
            if (!address) {
                return;
            }

            this.customerDistrict(address.region || '');

            var savedTab = this.getAddressValue(address, 'delivery_type') === 'delivery' ? 'delivery' : 'pickup';
            this.activeTab(savedTab);

            var self = this;

            if (savedTab === 'pickup') {
                this.loadPickupLocations(function () {
                    self.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
                });
                return;
            }

            this.loadDeliverySlots(function () {
                self.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
            });
        },

        switchTab: function (tab) {
            this.activeTab(tab);

            if (tab === 'pickup') {
                this.loadPickupLocations(function () {
                    this.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
                }.bind(this));
                return;
            }

            this.loadDeliverySlots(function () {
                this.restoreSelectionFromAddress(quote.shippingAddress && quote.shippingAddress());
            }.bind(this));
        },

        formatTime: function (time) {
            return time ? time.substring(0, 5) : '';
        },

        isValid: function () {
            return !!(this.selectedPickupLocation() || this.selectedDeliverySlot());
        }
    });
});
