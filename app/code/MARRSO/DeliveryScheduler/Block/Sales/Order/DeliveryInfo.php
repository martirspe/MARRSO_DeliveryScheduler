<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Sales\Order;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class DeliveryInfo extends Template
{
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->configProvider->isEnabled();
    }

    public function getOrder(): ?OrderInterface
    {
        $order = $this->getData('order');
        if ($order instanceof OrderInterface) {
            return $order;
        }

        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    public function getDeliveryType(): ?string
    {
        return $this->readAddressField('delivery_type');
    }

    public function getDeliveryDate(): ?string
    {
        return $this->readAddressField('delivery_date');
    }

    public function getDeliverySlot(): ?string
    {
        return $this->readAddressField('delivery_slot');
    }

    public function getServiceLevel(): ?string
    {
        return $this->readAddressField('service_level');
    }

    public function getServiceLevelLabel(): ?string
    {
        $level = $this->getServiceLevel();
        if (!$level) {
            return null;
        }

        $labels = [
            'express_180' => (string)__('180 min delivery'),
            'express_24' => (string)__('24 hour delivery'),
            'scheduled' => (string)__('Scheduled delivery'),
        ];

        return $labels[$level] ?? $level;
    }

    public function getDeliveryInstructions(): ?string
    {
        return $this->readAddressField('delivery_instructions');
    }

    public function getPickupLocationName(): ?string
    {
        $locationId = (int)$this->readAddressField('pickup_location_id');
        if (!$locationId) {
            return null;
        }

        try {
            return $this->pickupLocationRepository->getById($locationId)->getName();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function hasDeliverySelection(): bool
    {
        return (bool)$this->getDeliveryType();
    }

    private function readAddressField(string $field): ?string
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        $address = $order->getShippingAddress();
        if (!$address) {
            return null;
        }

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
