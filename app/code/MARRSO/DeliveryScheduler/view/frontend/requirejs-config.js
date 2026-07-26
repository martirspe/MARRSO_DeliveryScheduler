var config = {
    paths: {
        'leaflet': 'MARRSO_DeliveryScheduler/js/lib/leaflet/leaflet'
    },
    shim: {
        'leaflet': {
            'exports': 'L'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'MARRSO_DeliveryScheduler/js/view/shipping-mixin': true
            },
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'MARRSO_DeliveryScheduler/js/model/shipping-save-processor/payload-extender-mixin': true
            }
        }
    }
};
