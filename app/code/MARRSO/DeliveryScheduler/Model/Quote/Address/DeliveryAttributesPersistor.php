<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Quote\Address;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressExtensionFactory;
use Magento\Quote\Api\Data\AddressInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;

/**
 * Persists delivery scheduler attributes on the quote shipping address.
 */
class DeliveryAttributesPersistor
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AddressExtensionFactory $addressExtensionFactory
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function save(int $quoteId, OrderDeliveryScheduleInterface $selection): void
    {
        $quote = $this->cartRepository->get($quoteId);
        $shippingAddress = $quote->getShippingAddress();

        if (!$shippingAddress) {
            throw new LocalizedException(__('Quote shipping address is not available.'));
        }

        $this->applySelection($shippingAddress, $selection);
        $quote->setShippingAddress($shippingAddress);
        $this->cartRepository->save($quote);
    }

    public function applySelection(AddressInterface $address, OrderDeliveryScheduleInterface $selection): void
    {
        $extensionAttributes = $address->getExtensionAttributes() ?? $this->addressExtensionFactory->create();

        $extensionAttributes->setDeliveryType($selection->getDeliveryType());
        $extensionAttributes->setPickupLocationId($selection->getPickupLocationId());
        $extensionAttributes->setDeliveryDate($selection->getDeliveryDate());
        $extensionAttributes->setDeliverySlot($selection->getDeliverySlot());
        $extensionAttributes->setDeliveryInstructions($selection->getCustomerComment());
        if (method_exists($extensionAttributes, 'setServiceLevel')) {
            $extensionAttributes->setServiceLevel($selection->getServiceLevel());
        }

        $address->setExtensionAttributes($extensionAttributes);

        $address->setData('marrso_delivery_type', $selection->getDeliveryType());
        $address->setData('marrso_pickup_location_id', $selection->getPickupLocationId());
        $address->setData('marrso_delivery_date', $selection->getDeliveryDate());
        $address->setData('marrso_delivery_slot', $selection->getDeliverySlot());
        $address->setData('marrso_service_level', $selection->getServiceLevel());
        $address->setData('marrso_delivery_instructions', $selection->getCustomerComment());
    }
}
