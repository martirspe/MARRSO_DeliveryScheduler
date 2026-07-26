<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Plugin\Quote;

use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Api\Data\OrderAddressInterface;

class CopyDeliveryAttributesToOrder
{
    private const FIELDS = [
        'marrso_delivery_type',
        'marrso_pickup_location_id',
        'marrso_delivery_date',
        'marrso_delivery_slot',
        'marrso_service_level',
        'marrso_delivery_instructions',
    ];

    public function afterConvert(
        \Magento\Quote\Model\Quote\Address\ToOrderAddress $subject,
        OrderAddressInterface $result,
        QuoteAddress $object
    ): OrderAddressInterface {
        foreach (self::FIELDS as $field) {
            $value = $object->getData($field);
            if ($value !== null && $value !== '') {
                $result->setData($field, $value);
            }
        }

        return $result;
    }
}
