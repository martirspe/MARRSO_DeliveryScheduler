<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Checkout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\AddressInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class DeliverySchedulerConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function getConfig(): array
    {
        return [
            'marrsoDeliveryScheduler' => [
                'enabled' => $this->configProvider->isEnabled(),
                'enablePickup' => $this->configProvider->isPickupEnabled(),
                'enableDelivery' => $this->configProvider->isDeliveryEnabled(),
                'enableExpress180' => $this->configProvider->isExpress180Enabled(),
                'enableExpress24' => $this->configProvider->isExpress24Enabled(),
                'enablePickupMap' => $this->configProvider->isPickupMapEnabled(),
                'mapDefaultLat' => $this->configProvider->getPickupMapDefaultLat(),
                'mapDefaultLng' => $this->configProvider->getPickupMapDefaultLng(),
                'mapDefaultZoom' => $this->configProvider->getPickupMapDefaultZoom(),
                'defaultDeliveryPrice' => $this->configProvider->getDefaultDeliveryPrice(),
                'express180Price' => $this->configProvider->getExpress180Price(),
                'express24Price' => $this->configProvider->getExpress24Price(),
                'savedSelection' => $this->getSavedSelection(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSavedSelection(): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote || $quote->getIsVirtual()) {
            return [];
        }

        $address = $quote->getShippingAddress();
        if (!$address) {
            return [];
        }

        return [
            'delivery_type' => $this->readField($address, 'delivery_type'),
            'pickup_location_id' => $this->readField($address, 'pickup_location_id'),
            'delivery_date' => $this->readField($address, 'delivery_date'),
            'delivery_slot' => $this->readField($address, 'delivery_slot'),
            'service_level' => $this->readField($address, 'service_level'),
            'delivery_instructions' => $this->readField($address, 'delivery_instructions'),
        ];
    }

    private function readField(AddressInterface $address, string $field): ?string
    {
        $value = $address->getData('marrso_' . $field);
        if ($value === null || $value === '') {
            $value = $address->getData($field);
        }

        if (($value === null || $value === '') && $address->getExtensionAttributes()) {
            $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
            if (method_exists($address->getExtensionAttributes(), $method)) {
                $value = $address->getExtensionAttributes()->{$method}();
            }
        }

        return $value === null || $value === '' ? null : (string)$value;
    }
}
