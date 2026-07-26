define([
    'leaflet'
], function (L) {
    'use strict';

    /**
     * Lightweight Leaflet wrapper for pickup point selection.
     */
    return {
        /**
         * @param {HTMLElement} container
         * @param {Object} options
         * @returns {{updateMarkers: Function, setCustomerPosition: Function, invalidateSize: Function, destroy: Function}}
         */
        create: function (container, options) {
            options = options || {};

            var defaultCenter = options.defaultCenter || [-12.0464, -77.0428];
            var defaultZoom = options.defaultZoom || 12;
            var onSelect = typeof options.onSelect === 'function' ? options.onSelect : function () {};

            var map = L.map(container, {
                scrollWheelZoom: true,
                zoomControl: true
            }).setView(defaultCenter, defaultZoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            var markersLayer = L.layerGroup().addTo(map);
            var customerMarker = null;
            var markerById = {};

            var buildMarkerIcon = function (selected) {
                return L.divIcon({
                    className: selected ? 'marrso-map-marker is-selected' : 'marrso-map-marker',
                    html: '<span></span>',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14]
                });
            };

            var buildCustomerIcon = function () {
                return L.divIcon({
                    className: 'marrso-map-marker is-customer',
                    html: '<span></span>',
                    iconSize: [18, 18],
                    iconAnchor: [9, 9]
                });
            };

            return {
                updateMarkers: function (locations, selectedId) {
                    markersLayer.clearLayers();
                    markerById = {};

                    var bounds = [];

                    (locations || []).forEach(function (location) {
                        var lat = parseFloat(location.latitude);
                        var lng = parseFloat(location.longitude);

                        if (isNaN(lat) || isNaN(lng)) {
                            return;
                        }

                        var isSelected = String(location.entity_id) === String(selectedId);
                        var marker = L.marker([lat, lng], {
                            icon: buildMarkerIcon(isSelected),
                            title: location.name || ''
                        });

                        marker.bindPopup(
                            '<strong>' + (location.name || '') + '</strong><br>' +
                            (location.address || '')
                        );

                        marker.on('click', function () {
                            onSelect(location);
                        });

                        marker.addTo(markersLayer);
                        markerById[location.entity_id] = marker;
                        bounds.push([lat, lng]);
                    });

                    if (customerMarker) {
                        bounds.push(customerMarker.getLatLng());
                    }

                    if (bounds.length > 1) {
                        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
                    } else if (bounds.length === 1) {
                        map.setView(bounds[0], Math.max(defaultZoom, 14));
                    }
                },

                setCustomerPosition: function (lat, lng) {
                    if (customerMarker) {
                        markersLayer.removeLayer(customerMarker);
                    }

                    if (lat === null || lng === null || isNaN(lat) || isNaN(lng)) {
                        customerMarker = null;
                        return;
                    }

                    customerMarker = L.marker([lat, lng], {
                        icon: buildCustomerIcon(),
                        title: 'You are here',
                        zIndexOffset: 1000
                    }).addTo(markersLayer);
                },

                focusLocation: function (location) {
                    if (!location) {
                        return;
                    }

                    var lat = parseFloat(location.latitude);
                    var lng = parseFloat(location.longitude);

                    if (isNaN(lat) || isNaN(lng)) {
                        return;
                    }

                    map.setView([lat, lng], Math.max(map.getZoom(), 14));

                    var marker = markerById[location.entity_id];
                    if (marker) {
                        marker.openPopup();
                    }
                },

                invalidateSize: function () {
                    map.invalidateSize(true);
                },

                destroy: function () {
                    map.remove();
                }
            };
        }
    };
});
