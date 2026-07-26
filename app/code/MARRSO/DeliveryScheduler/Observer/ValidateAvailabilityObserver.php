<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Observer;

use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

/**
 * Validates delivery selection before quote is submitted as an order.
 */
class ValidateAvailabilityObserver implements ObserverInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->configProvider->isEnabled()) {
            return;
        }

        $quote = $observer->getEvent()->getQuote();
        if (!$quote instanceof Quote || $quote->getIsVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress) {
            return;
        }

        $deliveryType = $this->getValue($shippingAddress, 'delivery_type');
        if (!$deliveryType) {
            throw new LocalizedException(__('Please select pickup or home delivery.'));
        }

        try {
            if ($deliveryType === 'pickup') {
                $this->validatePickupSelection($shippingAddress);
                return;
            }

            if ($deliveryType === 'delivery') {
                $this->validateDeliverySelection($shippingAddress);
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error in validate availability observer: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to validate delivery selection. Please try again.'));
        }
    }

    private function validatePickupSelection($shippingAddress): void
    {
        $pickupLocationId = (int)$this->getValue($shippingAddress, 'pickup_location_id');
        if (!$pickupLocationId) {
            throw new LocalizedException(__('Please select a pickup location.'));
        }

        $pickupLocation = $this->pickupLocationRepository->getById($pickupLocationId);
        if (!$pickupLocation->getIsActive()) {
            throw new LocalizedException(__('Selected pickup location is no longer active.'));
        }

        $deliveryDate = $this->getValue($shippingAddress, 'delivery_date');
        if (!$deliveryDate) {
            throw new LocalizedException(__('Please select a pickup date.'));
        }

        $searchCriteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $pickupLocationId)
            ->addFilter('slot_date', $deliveryDate)
            ->addFilter('is_active', true)
            ->create();

        $slotRange = $this->getValue($shippingAddress, 'delivery_slot');
        foreach ($this->pickupSlotRepository->getList($searchCriteria)->getItems() as $slot) {
            if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                continue;
            }

            if ($slotRange && !$this->slotMatchesRange($slot->getStartTime(), $slot->getEndTime(), $slotRange)) {
                continue;
            }

            return;
        }

        throw new LocalizedException(__('Selected pickup slot is no longer available.'));
    }

    private function validateDeliverySelection($shippingAddress): void
    {
        $deliveryDate = $this->getValue($shippingAddress, 'delivery_date');
        $slotRange = $this->getValue($shippingAddress, 'delivery_slot');

        if (!$deliveryDate || !$slotRange) {
            throw new LocalizedException(__('Please select a delivery date and time slot.'));
        }

        [$startTime, $endTime] = array_pad(explode('-', $slotRange), 2, null);
        if (!$startTime || !$endTime) {
            throw new LocalizedException(__('Please select a valid delivery slot.'));
        }

        $district = trim((string)$shippingAddress->getCity());
        if ($district === '') {
            throw new LocalizedException(__('District is required for home delivery.'));
        }

        $searchCriteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('slot_date', $deliveryDate)
            ->addFilter('is_active', true)
            ->create();

        $selectedSlot = null;
        foreach ($this->deliverySlotRepository->getList($searchCriteria)->getItems() as $slot) {
            if ($this->timesMatch($slot->getStartTime(), $startTime) && $this->timesMatch($slot->getEndTime(), $endTime)) {
                $selectedSlot = $slot;
                break;
            }
        }

        if (!$selectedSlot) {
            throw new LocalizedException(__('Selected delivery slot is no longer available.'));
        }

        if ($selectedSlot->getUsedCapacity() >= $selectedSlot->getCapacity()) {
            throw new LocalizedException(__('Selected delivery slot is no longer available.'));
        }

        if ($this->isHoliday($deliveryDate)) {
            throw new LocalizedException(__('Selected delivery date is not available.'));
        }

        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $today = (new DateTime('today', $timezone))->format('Y-m-d');

        if ($deliveryDate === $today) {
            if (!$this->configProvider->isSameDayDeliveryEnabled()) {
                throw new LocalizedException(__('Same-day delivery is not enabled.'));
            }

            if ((int)(new DateTime('now', $timezone))->format('H') >= $this->configProvider->getSameDayDeliveryCutoffHour()) {
                throw new LocalizedException(__('Same-day delivery is no longer available.'));
            }
        }
    }

    private function isHoliday(string $deliveryDate): bool
    {
        $searchCriteria = $this->createSearchCriteriaBuilder()
            ->addFilter('holiday_date', $deliveryDate)
            ->create();

        return $this->holidayRepository->getList($searchCriteria)->getTotalCount() > 0;
    }

    private function getValue($shippingAddress, string $field): ?string
    {
        $value = $shippingAddress->getData('marrso_' . $field);
        if ($value === null || $value === '') {
            $value = $shippingAddress->getData($field);
        }

        if ($value === null || $value === '') {
            $extensionAttributes = $shippingAddress->getExtensionAttributes();
            if ($extensionAttributes) {
                $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
                if (method_exists($extensionAttributes, $method)) {
                    $value = $extensionAttributes->{$method}();
                }
            }
        }

        return $value === null || $value === '' ? null : (string)$value;
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }

    private function timesMatch(string $left, string $right): bool
    {
        return substr($left, 0, 5) === substr($right, 0, 5);
    }

    private function slotMatchesRange(string $startTime, string $endTime, string $slotRange): bool
    {
        [$start, $end] = array_pad(explode('-', $slotRange), 2, null);

        return $start && $end
            && $this->timesMatch($startTime, $start)
            && $this->timesMatch($endTime, $end);
    }
}
