define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/get-totals',
    'mage/storage',
    'mage/url',
    'mage/translate'
], function ($, ko, Component, quote, getTotalsAction, storage, urlBuilder, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'MARRSO_DeliveryScheduler/delivery-scheduler'
        },

        isLoading: ko.observable(false),
        isVisible: ko.observable(false),
        activeTab: ko.observable('pickup'),
        activeMethod: ko.observable('pickup'),
        pickupLocations: ko.observableArray([]),
        deliverySlots: ko.observableArray([]),
        selectedPickupLocation: ko.observable(null),
        selectedPickupSlot: ko.observable(null),
        selectedDeliverySlot: ko.observable(null),
        selectedPickupDate: ko.observable(null),
        selectedDeliveryDate: ko.observable(null),
        pickupSearchQuery: ko.observable(''),
        showPickupMap: ko.observable(false),
        sortByNearest: ko.observable(true),
        customerLat: ko.observable(null),
        customerLng: ko.observable(null),
        geolocationMessage: ko.observable(''),
        customerDistrict: ko.observable(''),
        deliveryInstructions: ko.observable(''),
        errorMessage: ko.observable(''),
        moduleConfig: {},

        initialize: function () {
            this._super();
            this.moduleConfig = window.checkoutConfig.marrsoDeliveryScheduler || {};

            if (this.moduleConfig.enabled === false || this.moduleConfig.enabled === 0 || this.moduleConfig.enabled === '0') {
                return;
            }

            this.isVisible(true);

            if (this.moduleConfig.enablePickup === false) {
                this.activeMethod('scheduled');
                this.activeTab('delivery');
            }

            if (this.moduleConfig.enableDelivery === false) {
                this.activeMethod('pickup');
                this.activeTab('pickup');
            }

            this.filteredPickupLocations = ko.pureComputed(this._filterPickupLocations, this);
            this.filteredDeliverySlots = ko.pureComputed(this._filterDeliverySlotsByMethod, this);
            this.deliveryDateGroups = ko.pureComputed(this._buildDeliveryDateGroups, this);
            this.pickupDateGroups = ko.pureComputed(this._buildPickupDateGroups, this);
            this.pickupSlotsForSelectedDate = ko.pureComputed(this._getPickupSlotsForSelectedDate, this);
            this.deliverySlotsForSelectedDate = ko.pureComputed(this._getDeliverySlotsForSelectedDate, this);
            this.summaryText = ko.pureComputed(this._buildSummaryText, this);

            this._mapManager = null;
            this._mapInitializing = false;
            this._deliverySlotsDistrict = null;
            this._deliverySlotsLoaded = false;

            this.filteredPickupLocations.subscribe(this.syncPickupMap.bind(this));
            this.selectedPickupLocation.subscribe(this.onSelectedPickupLocationChange.bind(this));
            this.showPickupMap.subscribe(this.onShowPickupMapChange.bind(this));

            this.restoreFromConfig(this.moduleConfig.savedSelection || {});

            if (quote.shippingAddress) {
                quote.shippingAddress.subscribe(this.onAddressChange.bind(this));
            }

            var address = quote.shippingAddress && quote.shippingAddress();
            if (address) {
                this.updateDistrictFromAddress(address);
            }

            this.loadActiveTabData();

            if (this.moduleConfig.enablePickupMap !== false) {
                this.requestGeolocation(false);
            }
        },

        loadActiveTabData: function () {
            if (this.activeMethod() === 'pickup' && this.moduleConfig.enablePickup !== false) {
                this.loadPickupLocations();
                return;
            }

            if (this.moduleConfig.enableDelivery !== false) {
                this.loadDeliverySlots();
            }
        },

        switchMethod: function (method) {
            if (method === 'pickup' && this.moduleConfig.enablePickup === false) {
                return;
            }

            if (method !== 'pickup' && this.moduleConfig.enableDelivery === false) {
                return;
            }

            if (method !== 'pickup' && !this.isMethodEnabled(method)) {
                return;
            }

            this.activeMethod(method);
            this.activeTab(method === 'pickup' ? 'pickup' : 'delivery');

            if (method === 'pickup') {
                this.selectedDeliverySlot(null);
                this.selectedDeliveryDate(null);
                this.errorMessage('');
                this.loadPickupLocations();
                return;
            }

            this.selectedPickupLocation(null);
            this.selectedPickupSlot(null);
            this.selectedPickupDate(null);
            this.selectedDeliverySlot(null);
            this.selectedDeliveryDate(null);

            if (!this.customerDistrict()) {
                this.errorMessage($t('Enter your city in the shipping address to see delivery slots.'));
                return;
            }

            this.loadDeliverySlots(function () {
                if (this.getSlotsForMethod(method).length === 0) {
                    this.errorMessage($t('No delivery slots available for this option.'));
                } else {
                    this.errorMessage('');
                }
            }.bind(this));
        },

        switchTab: function (tab) {
            this.switchMethod(tab === 'pickup' ? 'pickup' : this.activeMethod() === 'pickup' ? 'scheduled' : this.activeMethod());
        },

        isDeliveryMethod: function () {
            return this.activeMethod() !== 'pickup';
        },

        isMethodEnabled: function (method) {
            if (method === 'pickup') {
                return this.moduleConfig.enablePickup !== false;
            }

            if (method === 'express_180') {
                return this.moduleConfig.enableDelivery !== false && this.moduleConfig.enableExpress180 !== false;
            }

            if (method === 'express_24') {
                return this.moduleConfig.enableDelivery !== false && this.moduleConfig.enableExpress24 !== false;
            }

            return this.moduleConfig.enableDelivery !== false;
        },

        isMethodAvailable: function (method) {
            if (method === 'pickup') {
                return this.pickupLocations().length > 0;
            }

            if (!this.customerDistrict()) {
                return false;
            }

            return this.getSlotsForMethod(method).length > 0 || this.deliverySlots().length === 0;
        },

        getSlotsForMethod: function (method) {
            return this.deliverySlots().filter(function (slot) {
                return (slot.service_level || 'scheduled') === method;
            });
        },

        getMethodPriceLabel: function (method) {
            if (method === 'pickup') {
                return $t('FREE');
            }

            var minPrice = this.getMinimumPriceForMethod(method);

            return $t('From S/ %1').replace('%1', this.formatPrice(minPrice));
        },

        getMinimumPriceForMethod: function (method) {
            var prices = this.getSlotsForMethod(method).map(function (slot) {
                return parseFloat(slot.price || 0);
            }).filter(function (price) {
                return !isNaN(price) && price > 0;
            });

            if (prices.length) {
                return Math.min.apply(null, prices);
            }

            return this.getConfiguredMethodPrice(method);
        },

        getConfiguredMethodPrice: function (method) {
            var cfg = this.moduleConfig || {};

            if (method === 'express_180') {
                return parseFloat(cfg.express180Price || 19.90);
            }

            if (method === 'express_24') {
                return parseFloat(cfg.express24Price || 14.90);
            }

            return parseFloat(cfg.defaultDeliveryPrice || 9.90);
        },

        getActiveMethodLabel: function () {
            return this.getServiceLevelLabel(this.activeMethod());
        },

        getServiceLevelLabel: function (level) {
            var labels = {
                pickup: $t('Pickup point'),
                express_180: $t('180 min delivery'),
                express_24: $t('24 hour delivery'),
                scheduled: $t('Scheduled delivery')
            };

            return labels[level] || labels.scheduled;
        },

        normalizeServiceLevel: function (slot) {
            return slot && slot.service_level ? slot.service_level : 'scheduled';
        },

        loadPickupLocations: function (callback) {
            var self = this;

            this.isLoading(true);
            this.errorMessage('');

            var params = {};
            if (this.customerLat() !== null && this.customerLng() !== null) {
                params.customerLatitude = this.customerLat();
                params.customerLongitude = this.customerLng();
            }

            storage.get(urlBuilder.build('rest/V1/delivery/pickup-locations'), params).done(function (response) {
                self.pickupLocations(self.normalizeListResponse(response));
                self.isLoading(false);
                self.restorePickupSelection();
                self.syncPickupMap();

                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function () {
                self.errorMessage($t('Failed to load pickup locations.'));
                self.isLoading(false);
            });
        },

        requestGeolocation: function (reload) {
            if (this.moduleConfig.enablePickupMap === false) {
                return;
            }

            if (!navigator.geolocation) {
                this.geolocationMessage($t('Geolocation is not supported by your browser.'));
                return;
            }

            var self = this;

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    self.customerLat(position.coords.latitude);
                    self.customerLng(position.coords.longitude);
                    self.geolocationMessage('');

                    if (reload !== false) {
                        self.loadPickupLocations();
                    } else {
                        self.syncPickupMap();
                    }
                },
                function () {
                    self.geolocationMessage($t('Unable to get your location. Showing all pickup points.'));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                }
            );
        },

        togglePickupMapView: function () {
            this.showPickupMap(!this.showPickupMap());
        },

        toggleSortByNearest: function () {
            this.sortByNearest(!this.sortByNearest());
            if (this.sortByNearest() && this.customerLat() === null) {
                this.requestGeolocation(true);
            }
        },

        hasMappableLocations: function () {
            return this.getMappableLocations().length > 0;
        },

        getMappableLocations: function () {
            return this.filteredPickupLocations().filter(function (location) {
                return location.latitude !== null && location.longitude !== null
                    && !isNaN(parseFloat(location.latitude))
                    && !isNaN(parseFloat(location.longitude));
            });
        },

        getMapDefaultCenter: function () {
            var lat = parseFloat(this.moduleConfig.mapDefaultLat);
            var lng = parseFloat(this.moduleConfig.mapDefaultLng);

            if (!isNaN(lat) && !isNaN(lng)) {
                return [lat, lng];
            }

            return [-12.0464, -77.0428];
        },

        onMapContainerRendered: function (elements) {
            if (!elements || !elements.length || this.moduleConfig.enablePickupMap === false) {
                return;
            }

            if (this._mapManager) {
                this._mapManager.invalidateSize();
                this.syncPickupMap();
                return;
            }

            if (this._mapInitializing) {
                return;
            }

            this._mapInitializing = true;

            var self = this;
            var el = elements[0];

            require(['MARRSO_DeliveryScheduler/js/model/pickup-map'], function (PickupMap) {
                self._mapManager = PickupMap.create(el, {
                    onSelect: function (location) {
                        self.selectPickupLocation(location);
                    },
                    defaultCenter: self.getMapDefaultCenter(),
                    defaultZoom: parseInt(self.moduleConfig.mapDefaultZoom, 10) || 12
                });
                self._mapInitializing = false;
                self.syncPickupMap();
            });
        },

        syncPickupMap: function () {
            if (!this._mapManager || !this.showPickupMap()) {
                return;
            }

            var selected = this.selectedPickupLocation();

            this._mapManager.updateMarkers(
                this.getMappableLocations(),
                selected ? selected.entity_id : null
            );

            if (this.customerLat() !== null && this.customerLng() !== null) {
                this._mapManager.setCustomerPosition(this.customerLat(), this.customerLng());
            }
        },

        onSelectedPickupLocationChange: function (location) {
            if (location && this._mapManager) {
                this._mapManager.focusLocation(location);
            }
            this.syncPickupMap();
        },

        onShowPickupMapChange: function (visible) {
            if (!visible) {
                if (this._mapManager) {
                    this._mapManager.destroy();
                    this._mapManager = null;
                }
                return;
            }

            var self = this;
            window.setTimeout(function () {
                if (self._mapManager) {
                    self._mapManager.invalidateSize();
                }
                self.syncPickupMap();
            }, 250);
        },

        formatDistance: function (distance) {
            var value = parseFloat(distance);
            if (isNaN(value)) {
                return '';
            }

            return value.toFixed(1);
        },

        normalizeListResponse: function (response) {
            if (Array.isArray(response)) {
                return response;
            }

            if (response && Array.isArray(response.items)) {
                return response.items;
            }

            if (response && typeof response === 'object') {
                return Object.keys(response).map(function (key) {
                    return response[key];
                });
            }

            return [];
        },

        loadDeliverySlots: function (callback, forceReload) {
            var self = this;
            var district = this.customerDistrict();

            if (!district) {
                this.deliverySlots([]);
                this.selectedDeliveryDate(null);
                this._deliverySlotsDistrict = null;
                this._deliverySlotsLoaded = false;
                this.errorMessage($t('Enter your city in the shipping address to see delivery slots.'));
                return;
            }

            if (!forceReload
                && this._deliverySlotsLoaded
                && this._deliverySlotsDistrict === district) {
                if (typeof callback === 'function') {
                    callback();
                }
                return;
            }

            this.isLoading(true);
            this.errorMessage('');

            storage.get(urlBuilder.build('rest/V1/delivery/delivery-slots'), {
                district: district
            }).done(function (response) {
                self.deliverySlots(self.normalizeListResponse(response));
                self._deliverySlotsDistrict = district;
                self._deliverySlotsLoaded = true;
                self.isLoading(false);

                if (!self.deliverySlots().length) {
                    self.errorMessage(
                        $t('No delivery slots found. The shipping city must match the District field in Delivery Slots admin.')
                    );
                }

                self.restoreDeliverySelection();
                self.ensureDeliveryDateSelected();

                if (!self.selectedDeliverySlot() && self.deliveryDateGroups().length) {
                    self.selectDeliveryDate(self.deliveryDateGroups()[0].date);
                }

                if (typeof callback === 'function') {
                    callback();
                }
            }).fail(function () {
                self.errorMessage($t('Failed to load delivery slots.'));
                self.isLoading(false);
                self._deliverySlotsLoaded = false;
            });
        },

        selectPickupLocation: function (location) {
            this.activeMethod('pickup');
            this.activeTab('pickup');
            this.selectedPickupLocation(location);
            this.selectedDeliverySlot(null);
            this.selectedPickupSlot(null);
            this.selectedDeliveryDate(null);

            var dates = location.dates || this.groupSlotsByDate(location.slots || []);
            if (dates.length) {
                this.selectedPickupDate(dates[0].date);
                if (dates[0].slots && dates[0].slots.length) {
                    this.selectPickupSlot(dates[0].slots[0], false);
                }
            } else {
                this.selectedPickupDate(null);
            }
        },

        selectPickupDate: function (date) {
            this.selectedPickupDate(date);
            this.selectedPickupSlot(null);

            var slots = this.pickupSlotsForSelectedDate();
            if (slots.length) {
                this.selectPickupSlot(slots[0], false);
            }
        },

        selectPickupSlot: function (slot, shouldPersist) {
            this.activeMethod('pickup');
            this.activeTab('pickup');
            this.selectedPickupSlot(slot);

            if (slot && slot.date) {
                this.selectedPickupDate(slot.date);
            }

            if (!this.selectedPickupLocation()) {
                return;
            }

            if (shouldPersist !== false) {
                this.persistSelection('pickup', this.selectedPickupLocation().entity_id, slot);
            }
        },

        selectDeliveryDate: function (date) {
            this.selectedDeliveryDate(date);
            this.selectedDeliverySlot(null);

            var slots = this.deliverySlotsForSelectedDate();
            if (slots.length) {
                this.selectDeliverySlot(slots[0], false);
            }
        },

        ensurePickupSelection: function () {
            var location = this.selectedPickupLocation();

            if (!location) {
                return false;
            }

            if (!this.selectedPickupDate()) {
                var dates = location.dates || this.groupSlotsByDate(location.slots || []);
                if (dates.length) {
                    this.selectedPickupDate(dates[0].date);
                }
            }

            if (!this.selectedPickupSlot()) {
                var slots = this.pickupSlotsForSelectedDate();
                if (slots.length) {
                    this.selectPickupSlot(slots[0], false);
                }
            }

            return !!this.selectedPickupSlot();
        },

        selectDeliverySlot: function (slot, shouldPersist) {
            var level = this.normalizeServiceLevel(slot);
            this.activeMethod(level);
            this.activeTab('delivery');
            this.selectedDeliverySlot(slot);
            this.selectedPickupLocation(null);
            this.selectedPickupSlot(null);
            this.selectedPickupDate(null);

            if (slot && slot.date) {
                this.selectedDeliveryDate(slot.date);
            }

            if (shouldPersist !== false) {
                this.persistSelection('delivery', null, slot);
            }
        },

        ensureDeliveryDateSelected: function () {
            if (this.selectedDeliveryDate()) {
                return;
            }

            var groups = this.deliveryDateGroups();
            if (groups.length) {
                this.selectedDeliveryDate(groups[0].date);
            }
        },

        persistSelection: function (deliveryType, pickupLocationId, slot) {
            var self = this;
            var quoteId = this.getQuoteId();

            if (!quoteId) {
                this.errorMessage($t('Unable to determine quote id.'));
                return;
            }

            var shippingAddress = quote.shippingAddress && quote.shippingAddress();
            var slotRange = slot ? this.buildSlotRange(slot.start_time, slot.end_time) : null;

            var payload = {
                deliverySelection: {
                    quote_id: quoteId,
                    delivery_type: deliveryType,
                    delivery_date: slot ? slot.date : '',
                    pickup_location_id: pickupLocationId,
                    delivery_slot: slotRange,
                    service_level: deliveryType === 'pickup' ? null : this.activeMethod(),
                    delivery_price: slot ? parseFloat(slot.price || 0) : 0,
                    carrier_code: slot && slot.carrier_code ? slot.carrier_code : 'flatrate',
                    customer_comment: this.deliveryInstructions(),
                    district: this.customerDistrict() || (shippingAddress ? shippingAddress.city : '')
                }
            };

            storage.post(
                urlBuilder.build('rest/V1/delivery/save'),
                JSON.stringify(payload),
                true,
                'application/json'
            ).done(function () {
                self.applySelectionToAddress(shippingAddress, deliveryType, pickupLocationId, payload.deliverySelection);
                self.errorMessage('');
                getTotalsAction([], false);
            }).fail(function (response) {
                var message = $t('Failed to save delivery selection.');

                if (response && response.responseJSON && response.responseJSON.message) {
                    message = response.responseJSON.message;
                }

                self.errorMessage(message);
            });
        },

        saveComment: function () {
            if (!this.isValid()) {
                return;
            }

            var deliveryType = this.activeMethod() === 'pickup' ? 'pickup' : 'delivery';
            var slot = deliveryType === 'pickup' ? this.selectedPickupSlot() : this.selectedDeliverySlot();
            var pickupId = deliveryType === 'pickup' && this.selectedPickupLocation()
                ? this.selectedPickupLocation().entity_id
                : null;

            this.persistSelection(deliveryType, pickupId, slot);
        },

        applySelectionToAddress: function (shippingAddress, deliveryType, pickupLocationId, selection) {
            if (!shippingAddress) {
                return;
            }

            if (typeof shippingAddress.extensionAttributes === 'undefined') {
                shippingAddress.extensionAttributes = {};
            }

            shippingAddress.extensionAttributes.delivery_type = deliveryType;
            shippingAddress.extensionAttributes.pickup_location_id = pickupLocationId;
            shippingAddress.extensionAttributes.delivery_date = selection.delivery_date;
            shippingAddress.extensionAttributes.delivery_slot = selection.delivery_slot;
            shippingAddress.extensionAttributes.delivery_instructions = selection.customer_comment;
            if (typeof shippingAddress.extensionAttributes.service_level !== 'undefined') {
                shippingAddress.extensionAttributes.service_level = selection.service_level;
            }

            shippingAddress.marrso_delivery_type = deliveryType;
            shippingAddress.marrso_pickup_location_id = pickupLocationId;
            shippingAddress.marrso_delivery_date = selection.delivery_date;
            shippingAddress.marrso_delivery_slot = selection.delivery_slot;
            shippingAddress.marrso_service_level = selection.service_level;
            shippingAddress.marrso_delivery_instructions = selection.customer_comment;
        },

        onAddressChange: function (address) {
            if (!address) {
                return;
            }

            var district = address.city || address.region || '';
            if (district === this.customerDistrict()) {
                return;
            }

            this.updateDistrictFromAddress(address);
            this._deliverySlotsLoaded = false;
            this._deliverySlotsDistrict = null;

            if (this.activeMethod() === 'pickup') {
                this.loadPickupLocations();
                return;
            }

            if (this.moduleConfig.enableDelivery !== false) {
                this.loadDeliverySlots();
            }
        },

        updateDistrictFromAddress: function (address) {
            var district = address.city || address.region || '';
            this.customerDistrict(district);
        },

        restoreFromConfig: function (saved) {
            if (!saved || !saved.delivery_type) {
                return;
            }

            this.activeTab(saved.delivery_type === 'delivery' ? 'delivery' : 'pickup');
            this.activeMethod(saved.service_level || (saved.delivery_type === 'delivery' ? 'scheduled' : 'pickup'));

            if (saved.delivery_instructions) {
                this.deliveryInstructions(saved.delivery_instructions);
            }

            if (saved.delivery_date) {
                if (saved.delivery_type === 'delivery') {
                    this.selectedDeliveryDate(saved.delivery_date);
                } else {
                    this.selectedPickupDate(saved.delivery_date);
                }
            }
        },

        restorePickupSelection: function () {
            var saved = this.moduleConfig.savedSelection || {};
            if (saved.delivery_type !== 'pickup' || !saved.pickup_location_id) {
                return;
            }

            var location = ko.utils.arrayFirst(this.pickupLocations(), function (item) {
                return String(item.entity_id) === String(saved.pickup_location_id);
            });

            if (location) {
                this.selectedPickupLocation(location);
            }

            if (!location || !saved.delivery_date) {
                return;
            }

            this.selectedPickupDate(saved.delivery_date);

            var slots = location.slots || [];
            var slot = ko.utils.arrayFirst(slots, function (item) {
                return item.date === saved.delivery_date && (
                    !saved.delivery_slot || this.slotEqualsSaved(item, saved.delivery_slot)
                );
            }.bind(this));

            if (slot) {
                this.selectedPickupSlot(slot);
            }
        },

        restoreDeliverySelection: function () {
            var saved = this.moduleConfig.savedSelection || {};
            if (saved.delivery_type !== 'delivery' || !saved.delivery_slot) {
                return;
            }

            if (saved.delivery_date) {
                this.selectedDeliveryDate(saved.delivery_date);
            }

            var slot = ko.utils.arrayFirst(this.deliverySlots(), function (item) {
                return item.date === saved.delivery_date && this.slotEqualsSaved(item, saved.delivery_slot);
            }.bind(this));

            if (slot) {
                this.selectedDeliverySlot(slot);
            }
        },

        slotEqualsSaved: function (slot, savedRange) {
            return this.buildSlotRange(slot.start_time, slot.end_time) === savedRange
                || this.buildSlotRange(slot.start_time, slot.end_time).replace(/:00/g, '') === String(savedRange).replace(/:00/g, '');
        },

        buildSlotRange: function (startTime, endTime) {
            return this.formatTime(startTime) + '-' + this.formatTime(endTime);
        },

        isPickupLocationSelected: function (location) {
            var selected = this.selectedPickupLocation();
            return selected && String(selected.entity_id) === String(location.entity_id);
        },

        isPickupSlotSelected: function (slot) {
            var selected = this.selectedPickupSlot();
            return selected
                && selected.date === slot.date
                && this.formatTime(selected.start_time) === this.formatTime(slot.start_time);
        },

        isDeliverySlotSelected: function (slot) {
            var selected = this.selectedDeliverySlot();
            return selected
                && selected.date === slot.date
                && this.formatTime(selected.start_time) === this.formatTime(slot.start_time)
                && this.formatTime(selected.end_time) === this.formatTime(slot.end_time);
        },

        isPickupDateSelected: function (date) {
            return this.selectedPickupDate() === date;
        },

        isDeliveryDateSelected: function (date) {
            return this.selectedDeliveryDate() === date;
        },

        getQuoteId: function () {
            if (typeof quote.getQuoteId === 'function') {
                return quote.getQuoteId();
            }

            if (window.checkoutConfig && window.checkoutConfig.quoteData) {
                return window.checkoutConfig.quoteData.entity_id || window.checkoutConfig.quoteData.quote_id;
            }

            return null;
        },

        formatTime: function (time) {
            return time ? String(time).substring(0, 5) : '';
        },

        formatPrice: function (price) {
            return parseFloat(price || 0).toFixed(2);
        },

        formatSlotPrice: function (slot) {
            var method = this.normalizeServiceLevel(slot);
            var price = parseFloat(slot && slot.price ? slot.price : 0);

            if (price <= 0 && method !== 'pickup') {
                price = this.getConfiguredMethodPrice(method);
            }

            if (price <= 0) {
                return $t('FREE');
            }

            return 'S/ ' + this.formatPrice(price);
        },

        getLowestDeliveryPrice: function () {
            var methods = ['express_180', 'express_24', 'scheduled'];
            var prices = methods.map(this.getMinimumPriceForMethod.bind(this));

            return $t('From S/ %1').replace('%1', this.formatPrice(Math.min.apply(null, prices)));
        },

        groupSlotsByDate: function (slots) {
            var grouped = {};
            var self = this;

            (slots || []).forEach(function (slot) {
                if (!slot.date) {
                    return;
                }

                if (!grouped[slot.date]) {
                    grouped[slot.date] = {
                        date: slot.date,
                        label: self.formatDateLabel(slot.date),
                        slots: []
                    };
                }

                grouped[slot.date].slots.push(slot);
            });

            return Object.keys(grouped).sort().map(function (date) {
                return grouped[date];
            });
        },

        formatDateLabel: function (dateStr) {
            if (!dateStr) {
                return '';
            }

            var parts = String(dateStr).split('-');
            if (parts.length !== 3) {
                return dateStr;
            }

            var dateObj = new Date(parts[0], parseInt(parts[1], 10) - 1, parts[2]);
            var dayNames = [
                $t('Sun'), $t('Mon'), $t('Tue'), $t('Wed'), $t('Thu'), $t('Fri'), $t('Sat')
            ];

            var day = ('0' + dateObj.getDate()).slice(-2);
            var month = ('0' + (dateObj.getMonth() + 1)).slice(-2);

            return dayNames[dateObj.getDay()] + ' ' + day + '/' + month;
        },

        _filterPickupLocations: function () {
            var query = this.pickupSearchQuery().toLowerCase().trim();
            var self = this;

            var list = this.pickupLocations().filter(function (location) {
                if (!query) {
                    return true;
                }

                var haystack = [
                    location.name,
                    location.address,
                    location.district,
                    location.brand,
                    location.location_references
                ].join(' ').toLowerCase();

                return haystack.indexOf(query) !== -1;
            });

            if (this.sortByNearest()) {
                list.sort(function (a, b) {
                    var distanceA = parseFloat(a.distance_km);
                    var distanceB = parseFloat(b.distance_km);

                    if (!isNaN(distanceA) && !isNaN(distanceB) && distanceA !== distanceB) {
                        return distanceA - distanceB;
                    }

                    return (parseInt(a.priority, 10) || 0) - (parseInt(b.priority, 10) || 0);
                });
            }

            return list;
        },

        _buildDeliveryDateGroups: function () {
            return this.groupSlotsByDate(this.filteredDeliverySlots());
        },

        _filterDeliverySlotsByMethod: function () {
            var method = this.activeMethod();
            if (method === 'pickup') {
                return [];
            }

            return this.getSlotsForMethod(method);
        },

        _buildPickupDateGroups: function () {
            var location = this.selectedPickupLocation();
            if (!location) {
                return [];
            }

            if (location.dates && location.dates.length) {
                return location.dates;
            }

            return this.groupSlotsByDate(location.slots || []);
        },

        _getPickupSlotsForSelectedDate: function () {
            var date = this.selectedPickupDate();
            if (!date) {
                return [];
            }

            var group = ko.utils.arrayFirst(this.pickupDateGroups(), function (item) {
                return item.date === date;
            });

            return group && group.slots ? group.slots : [];
        },

        _getDeliverySlotsForSelectedDate: function () {
            var date = this.selectedDeliveryDate();
            if (!date) {
                return [];
            }

            var group = ko.utils.arrayFirst(this.deliveryDateGroups(), function (item) {
                return item.date === date;
            });

            return group && group.slots ? group.slots : [];
        },

        _buildSummaryText: function () {
            if (this.selectedPickupLocation() && this.selectedPickupSlot()) {
                return this.selectedPickupLocation().name + ' · ' +
                    this.formatDateLabel(this.selectedPickupSlot().date) + ' · ' +
                    this.formatTime(this.selectedPickupSlot().start_time) + '-' +
                    this.formatTime(this.selectedPickupSlot().end_time);
            }

            if (this.selectedDeliverySlot()) {
                return this.getServiceLevelLabel(this.normalizeServiceLevel(this.selectedDeliverySlot())) + ' · ' +
                    this.formatDateLabel(this.selectedDeliverySlot().date) + ' · ' +
                    this.formatTime(this.selectedDeliverySlot().start_time) + '-' +
                    this.formatTime(this.selectedDeliverySlot().end_time);
            }

            return '';
        },

        isValid: function () {
            if (this.moduleConfig.enabled === false) {
                return true;
            }

            if (this.activeMethod() === 'pickup') {
                return this.ensurePickupSelection();
            }

            var slot = this.selectedDeliverySlot();
            return !!slot && this.normalizeServiceLevel(slot) === this.activeMethod();
        }
    });
});
