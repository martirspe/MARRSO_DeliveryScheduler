<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\OrderDeliveryScheduleRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\SaveDeliverySelectionInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\Quote\Address\DeliveryAttributesPersistor;

/**
 * Save Delivery Selection Service Implementation
 */
class SaveDeliverySelection implements SaveDeliverySelectionInterface
{
    public function __construct(
        private readonly OrderDeliveryScheduleRepositoryInterface $orderDeliveryScheduleRepository,
        private readonly DeliveryAttributesPersistor $deliveryAttributesPersistor,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AvailabilityEngine $availabilityEngine,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(OrderDeliveryScheduleInterface $deliverySelection): bool
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                throw new LocalizedException(__('Delivery scheduler is disabled.'));
            }

            if (!$deliverySelection->getOrderId() && !$deliverySelection->getQuoteId()) {
                throw new LocalizedException(__('Either order_id or quote_id must be provided'));
            }

            if (!$deliverySelection->getDeliveryType()) {
                throw new LocalizedException(__('Delivery type is required'));
            }

            if (!$deliverySelection->getDeliveryDate()) {
                throw new LocalizedException(__('Delivery date is required'));
            }

            $validTypes = [
                OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP,
                OrderDeliveryScheduleInterface::DELIVERY_TYPE_DELIVERY,
            ];

            if (!in_array($deliverySelection->getDeliveryType(), $validTypes, true)) {
                throw new LocalizedException(__('Invalid delivery type: %1', $deliverySelection->getDeliveryType()));
            }

            if ($deliverySelection->getDeliveryType() === OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP
                && !$deliverySelection->getPickupLocationId()) {
                throw new LocalizedException(__('Pickup location is required for pickup orders'));
            }

            $this->assertSelectionIsAvailable($deliverySelection, $this->resolveDistrict($deliverySelection));

            if ($deliverySelection->getQuoteId()) {
                try {
                    $existing = $this->orderDeliveryScheduleRepository->getByQuoteId($deliverySelection->getQuoteId());
                    $existing->setDeliveryType($deliverySelection->getDeliveryType());
                    $existing->setPickupLocationId($deliverySelection->getPickupLocationId());
                    $existing->setDeliveryDate($deliverySelection->getDeliveryDate());
                    $existing->setDeliverySlot($deliverySelection->getDeliverySlot());
                    $existing->setCustomerComment($deliverySelection->getCustomerComment());

                    if ($deliverySelection->getOrderId()) {
                        $existing->setOrderId($deliverySelection->getOrderId());
                    }

                    $deliverySelection = $existing;
                } catch (NoSuchEntityException $e) {
                    // New quote-based selection.
                }

                $this->deliveryAttributesPersistor->save((int)$deliverySelection->getQuoteId(), $deliverySelection);
            }

            $this->orderDeliveryScheduleRepository->save($deliverySelection);

            return true;
        } catch (CouldNotSaveException | LocalizedException $e) {
            $this->logger->error('Could not save delivery selection: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error saving delivery selection: ' . $e->getMessage());
            throw new CouldNotSaveException(__('Unable to save delivery selection: %1', $e->getMessage()));
        }
    }

    /**
     * @throws LocalizedException
     */
    private function resolveDistrict(OrderDeliveryScheduleInterface $deliverySelection): ?string
    {
        $district = trim((string)$deliverySelection->getData('district'));
        if ($district !== '') {
            return $district;
        }

        if (!$deliverySelection->getQuoteId()) {
            return null;
        }

        try {
            $quote = $this->cartRepository->get((int)$deliverySelection->getQuoteId());
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress) {
                return null;
            }

            $city = trim((string)$shippingAddress->getCity());
            return $city !== '' ? $city : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @throws LocalizedException
     */
    private function assertSelectionIsAvailable(
        OrderDeliveryScheduleInterface $deliverySelection,
        ?string $district
    ): void {
        if ($deliverySelection->getDeliveryType() === OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP) {
            $locations = $this->availabilityEngine->getAvailablePickupLocations(
                null,
                null,
                null,
                $deliverySelection->getDeliveryDate()
            );

            foreach ($locations as $location) {
                if ((int)$location['entity_id'] !== (int)$deliverySelection->getPickupLocationId()) {
                    continue;
                }

                foreach ($location['slots'] as $slot) {
                    if ($slot['date'] === $deliverySelection->getDeliveryDate()) {
                        return;
                    }
                }
            }

            throw new LocalizedException(__('Selected pickup option is no longer available.'));
        }

        if ($district === null || $district === '') {
            throw new LocalizedException(__('District is required for home delivery.'));
        }

        $slots = $this->availabilityEngine->getAvailableDeliverySlots(
            $district,
            $deliverySelection->getDeliveryDate(),
            $deliverySelection->getDeliveryDate()
        );

        $selectedRange = (string)$deliverySelection->getDeliverySlot();
        foreach ($slots as $slot) {
            $range = $slot['start_time'] . '-' . $slot['end_time'];
            if ($range === $selectedRange || str_replace(':00', '', $range) === str_replace(':00', '', $selectedRange)) {
                return;
            }
        }

        throw new LocalizedException(__('Selected delivery slot is no longer available.'));
    }
}
