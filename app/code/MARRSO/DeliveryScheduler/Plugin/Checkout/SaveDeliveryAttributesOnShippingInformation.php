<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Quote\Api\Data\AddressExtensionFactory;

class SaveDeliveryAttributesOnShippingInformation
{
    public function __construct(
        private readonly AddressExtensionFactory $addressExtensionFactory
    ) {
    }

    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $address = $addressInformation->getShippingAddress();
        if (!$address) {
            return [$cartId, $addressInformation];
        }

        $addressExtension = $address->getExtensionAttributes() ?? $this->addressExtensionFactory->create();
        $hasValue = false;

        $map = [
            'delivery_type' => ['getDeliveryType', 'setDeliveryType'],
            'pickup_location_id' => ['getPickupLocationId', 'setPickupLocationId'],
            'delivery_date' => ['getDeliveryDate', 'setDeliveryDate'],
            'delivery_slot' => ['getDeliverySlot', 'setDeliverySlot'],
            'delivery_instructions' => ['getDeliveryInstructions', 'setDeliveryInstructions'],
            'service_level' => ['getServiceLevel', 'setServiceLevel'],
        ];

        foreach ($map as $field => [$getter, $setter]) {
            if (!method_exists($addressExtension, $getter)) {
                continue;
            }

            $value = $addressExtension->{$getter}();
            if ($value === null || $value === '') {
                continue;
            }

            $hasValue = true;
            $address->setData('marrso_' . $field, $value);
        }

        if ($hasValue) {
            $address->setExtensionAttributes($addressExtension);
            $addressInformation->setShippingAddress($address);
        }

        return [$cartId, $addressInformation];
    }
}
