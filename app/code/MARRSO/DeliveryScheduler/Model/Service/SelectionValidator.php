<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;

/**
 * Lightweight server-side validation and price resolution for checkout selections.
 */
class SelectionValidator
{
    private const MAX_COMMENT_LENGTH = 500;

    public function __construct(
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly ConfigProvider $configProvider,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @return array{price: float, carrier_code: string|null}
     * @throws LocalizedException
     */
    public function validateAndResolve(
        OrderDeliveryScheduleInterface $selection,
        ?string $district
    ): array {
        $this->assertValidDate((string)$selection->getDeliveryDate());
        $this->sanitizeComment($selection);

        if ($selection->getDeliveryType() === OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP) {
            $this->validatePickupSelection($selection);

            return ['price' => 0.0, 'carrier_code' => null];
        }

        if ($district === null || trim($district) === '') {
            throw new LocalizedException(__('District is required for home delivery.'));
        }

        return $this->validateDeliverySelection($selection, trim($district));
    }

    /**
     * @throws LocalizedException
     */
    private function validatePickupSelection(OrderDeliveryScheduleInterface $selection): void
    {
        $locationId = (int)$selection->getPickupLocationId();
        $location = $this->pickupLocationRepository->getById($locationId);

        if (!$location->getIsActive()) {
            throw new LocalizedException(__('Selected pickup location is no longer available.'));
        }

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $locationId)
            ->addFilter('slot_date', $selection->getDeliveryDate())
            ->addFilter('is_active', true)
            ->create();

        $selectedRange = (string)$selection->getDeliverySlot();
        foreach ($this->pickupSlotRepository->getList($criteria)->getItems() as $slot) {
            if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                continue;
            }

            if ($selectedRange !== ''
                && !$this->slotMatchesRange($slot->getStartTime(), $slot->getEndTime(), $selectedRange)) {
                continue;
            }

            return;
        }

        throw new LocalizedException(__('Selected pickup option is no longer available.'));
    }

    /**
     * @return array{price: float, carrier_code: string|null}
     * @throws LocalizedException
     */
    private function validateDeliverySelection(
        OrderDeliveryScheduleInterface $selection,
        string $district
    ): array {
        $selectedRange = (string)$selection->getDeliverySlot();
        [$startTime, $endTime] = array_pad(explode('-', $selectedRange), 2, null);

        if (!$startTime || !$endTime) {
            throw new LocalizedException(__('Please select a valid delivery slot.'));
        }

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('slot_date', $selection->getDeliveryDate())
            ->addFilter('is_active', true)
            ->create();

        $selectedLevel = ServiceLevel::normalize((string)$selection->getServiceLevel());

        foreach ($this->deliverySlotRepository->getList($criteria)->getItems() as $slot) {
            if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                continue;
            }

            if (ServiceLevel::normalize($slot->getServiceLevel()) !== $selectedLevel) {
                continue;
            }

            if (!$this->timesMatch($slot->getStartTime(), $startTime)
                || !$this->timesMatch($slot->getEndTime(), $endTime)) {
                continue;
            }

            if (!$this->isServiceLevelAvailable($selectedLevel, $slot->getSlotDate(), $slot->getStartTime())) {
                throw new LocalizedException(__('Selected delivery slot is no longer available.'));
            }

            return [
                'price' => (float)$slot->getPrice(),
                'carrier_code' => trim((string)$slot->getCarrierCode()) ?: 'flatrate',
            ];
        }

        throw new LocalizedException(__('Selected delivery slot is no longer available.'));
    }

    /**
     * @throws LocalizedException
     */
    private function assertValidDate(string $date): void
    {
        if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
            throw new LocalizedException(__('Please select a valid delivery date.'));
        }
    }

    private function sanitizeComment(OrderDeliveryScheduleInterface $selection): void
    {
        $comment = trim((string)$selection->getCustomerComment());
        if (strlen($comment) > self::MAX_COMMENT_LENGTH) {
            $comment = substr($comment, 0, self::MAX_COMMENT_LENGTH);
        }

        $selection->setCustomerComment($comment);
    }

    private function isServiceLevelAvailable(string $serviceLevel, string $slotDate, string $startTime): bool
    {
        if (!$this->configProvider->isExpress180Enabled() && $serviceLevel === ServiceLevel::EXPRESS_180) {
            return false;
        }

        if (!$this->configProvider->isExpress24Enabled() && $serviceLevel === ServiceLevel::EXPRESS_24) {
            return false;
        }

        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $now = new DateTime('now', $timezone);
        $today = (new DateTime('today', $timezone))->format('Y-m-d');
        $tomorrow = (new DateTime('tomorrow', $timezone))->format('Y-m-d');

        if ($serviceLevel === ServiceLevel::EXPRESS_180) {
            if ($slotDate !== $today) {
                return false;
            }

            $slotStart = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $slotDate . ' ' . $this->normalizeTimeValue($startTime),
                $timezone
            );
            if (!$slotStart) {
                return false;
            }

            $minutesUntilStart = ($slotStart->getTimestamp() - $now->getTimestamp()) / 60;

            return $minutesUntilStart >= 0 && $minutesUntilStart <= 180;
        }

        if ($serviceLevel === ServiceLevel::EXPRESS_24) {
            return in_array($slotDate, [$today, $tomorrow], true);
        }

        return true;
    }

    private function normalizeTimeValue(string $time): string
    {
        $time = trim($time);
        return strlen($time) === 5 ? $time . ':00' : $time;
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

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
