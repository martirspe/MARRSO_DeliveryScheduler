<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;

/**
 * Reserves and releases slot capacity when orders are placed or cancelled.
 */
class SlotCapacityManager
{
    public function __construct(
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function reserveFromAddress($shippingAddress): void
    {
        $deliveryType = $this->readField($shippingAddress, 'delivery_type');
        $deliveryDate = $this->readField($shippingAddress, 'delivery_date');

        if (!$deliveryType || !$deliveryDate) {
            return;
        }

        if ($deliveryType === 'pickup') {
            $this->reservePickupSlot(
                (int)$this->readField($shippingAddress, 'pickup_location_id'),
                $deliveryDate,
                $this->readField($shippingAddress, 'delivery_slot')
            );
            return;
        }

        if ($deliveryType === 'delivery') {
            $this->reserveDeliverySlot(
                $this->readField($shippingAddress, 'district') ?: (string)$shippingAddress->getCity(),
                $deliveryDate,
                (string)$this->readField($shippingAddress, 'delivery_slot')
            );
        }
    }

    /**
     * @throws LocalizedException
     */
    private function reservePickupSlot(int $locationId, string $date, ?string $slotRange): void
    {
        if (!$locationId) {
            return;
        }

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $locationId)
            ->addFilter('slot_date', $date)
            ->addFilter('is_active', true)
            ->create();

        foreach ($this->pickupSlotRepository->getList($criteria)->getItems() as $slot) {
            if ($slotRange && !$this->slotMatchesRange($slot->getStartTime(), $slot->getEndTime(), $slotRange)) {
                continue;
            }

            $this->incrementSlot($slot);
            return;
        }

        throw new LocalizedException(__('Selected pickup slot is no longer available.'));
    }

    /**
     * @throws LocalizedException
     */
    private function reserveDeliverySlot(string $district, string $date, string $slotRange): void
    {
        if ($district === '' || $slotRange === '') {
            return;
        }

        [$startTime, $endTime] = array_pad(explode('-', $slotRange), 2, null);
        if (!$startTime || !$endTime) {
            return;
        }

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('slot_date', $date)
            ->addFilter('is_active', true)
            ->create();

        foreach ($this->deliverySlotRepository->getList($criteria)->getItems() as $slot) {
            if (!$this->timesMatch($slot->getStartTime(), $startTime) || !$this->timesMatch($slot->getEndTime(), $endTime)) {
                continue;
            }

            $this->incrementSlot($slot);
            return;
        }

        throw new LocalizedException(__('Selected delivery slot is no longer available.'));
    }

    /**
     * @param PickupSlotInterface|\MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface $slot
     * @throws LocalizedException
     */
    private function incrementSlot($slot): void
    {
        if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
            throw new LocalizedException(__('Selected slot is fully booked.'));
        }

        $slot->setUsedCapacity((int)$slot->getUsedCapacity() + 1);

        if ($slot instanceof PickupSlotInterface) {
            $this->pickupSlotRepository->save($slot);
            return;
        }

        $this->deliverySlotRepository->save($slot);
    }

    private function slotMatchesRange(string $startTime, string $endTime, string $slotRange): bool
    {
        [$start, $end] = array_pad(explode('-', $slotRange), 2, null);

        return $start && $end
            && $this->timesMatch($startTime, $start)
            && $this->timesMatch($endTime, $end);
    }

    private function timesMatch(string $left, string $right): bool
    {
        return substr($left, 0, 5) === substr($right, 0, 5);
    }

    private function readField($address, string $field): ?string
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

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
