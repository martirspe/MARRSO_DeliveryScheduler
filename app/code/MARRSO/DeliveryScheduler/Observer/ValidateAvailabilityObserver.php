<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Observer;

use DateTime;
use DateTimeZone;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

/**
 * Validate Availability Observer
 *
 * Validates that the selected delivery/pickup option is still available
 */
class ValidateAvailabilityObserver implements ObserverInterface
{
    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var PickupLocationRepositoryInterface
     */
    private $pickupLocationRepository;

    /**
     * @var PickupSlotRepositoryInterface
     */
    private $pickupSlotRepository;

    /**
     * @var DeliverySlotRepositoryInterface
     */
    private $deliverySlotRepository;

    /**
     * @var HolidayRepositoryInterface
     */
    private $holidayRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        CheckoutSession $checkoutSession,
        PickupLocationRepositoryInterface $pickupLocationRepository,
        PickupSlotRepositoryInterface $pickupSlotRepository,
        DeliverySlotRepositoryInterface $deliverySlotRepository,
        HolidayRepositoryInterface $holidayRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->pickupLocationRepository = $pickupLocationRepository;
        $this->pickupSlotRepository = $pickupSlotRepository;
        $this->deliverySlotRepository = $deliverySlotRepository;
        $this->holidayRepository = $holidayRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function execute(Observer $observer): void
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                return;
            }

            $quote = $this->checkoutSession->getQuote();
            if (!$quote) {
                return;
            }

            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress) {
                return;
            }

            $deliveryType = $this->getValue($shippingAddress, 'delivery_type');
            if (!$deliveryType) {
                return;
            }

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
        }
    }

    private function validatePickupSelection($shippingAddress): void
    {
        $pickupLocationId = (int)$this->getValue($shippingAddress, 'pickup_location_id');
        if (!$pickupLocationId) {
            return;
        }

        $pickupLocation = $this->pickupLocationRepository->getById($pickupLocationId);
        if (!$pickupLocation->getIsActive()) {
            throw new LocalizedException(__('Selected pickup location is no longer active.'));
        }

        $deliveryDate = $this->getValue($shippingAddress, 'delivery_date');
        if (!$deliveryDate) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('pickup_location_id', $pickupLocationId)
            ->addFilter('slot_date', $deliveryDate)
            ->addFilter('is_active', true)
            ->create();

        $slots = $this->pickupSlotRepository->getList($searchCriteria)->getItems();
        foreach ($slots as $slot) {
            if ($slot->getUsedCapacity() < $slot->getCapacity()) {
                return;
            }
        }

        throw new LocalizedException(__('Selected pickup slot is no longer available.'));
    }

    private function validateDeliverySelection($shippingAddress): void
    {
        $deliveryDate = $this->getValue($shippingAddress, 'delivery_date');
        $slotRange = $this->getValue($shippingAddress, 'delivery_slot');

        if (!$deliveryDate || !$slotRange) {
            return;
        }

        [$startTime, $endTime] = array_pad(explode('-', $slotRange), 2, null);
        if (!$startTime || !$endTime) {
            throw new LocalizedException(__('Please select a valid delivery slot.'));
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('slot_date', $deliveryDate)
            ->addFilter('is_active', true)
            ->create();

        $slots = $this->deliverySlotRepository->getList($searchCriteria)->getItems();
        $selectedSlot = null;

        foreach ($slots as $slot) {
            if ($slot->getStartTime() === $startTime && $slot->getEndTime() === $endTime) {
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

            if ((int)date('H') >= $this->configProvider->getSameDayDeliveryCutoffHour()) {
                throw new LocalizedException(__('Same-day delivery is no longer available.'));
            }
        }
    }

    private function isHoliday(string $deliveryDate): bool
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('holiday_date', $deliveryDate)
            ->create();

        return $this->holidayRepository->getList($searchCriteria)->getTotalCount() > 0;
    }

    private function getValue($shippingAddress, string $field): ?string
    {
        $value = $shippingAddress->getData($field);
        if ($value === null || $value === '') {
            $value = $shippingAddress->getData('custom_' . $field);
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

        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }
}
