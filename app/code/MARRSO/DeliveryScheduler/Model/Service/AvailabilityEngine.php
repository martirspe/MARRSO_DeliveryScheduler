<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use DateTime;
use DateTimeZone;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

/**
 * Availability Engine
 *
 * Core service for determining availability of pickup points and delivery slots
 */
class AvailabilityEngine
{
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
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DistanceCalculator
     */
    private $distanceCalculator;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    public function __construct(
        PickupLocationRepositoryInterface $pickupLocationRepository,
        PickupSlotRepositoryInterface $pickupSlotRepository,
        DeliverySlotRepositoryInterface $deliverySlotRepository,
        HolidayRepositoryInterface $holidayRepository,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        DistanceCalculator $distanceCalculator,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder
    ) {
        $this->pickupLocationRepository = $pickupLocationRepository;
        $this->pickupSlotRepository = $pickupSlotRepository;
        $this->deliverySlotRepository = $deliverySlotRepository;
        $this->holidayRepository = $holidayRepository;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->distanceCalculator = $distanceCalculator;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
    }

    /**
     * Get available pickup locations
     *
     * @param string|null $district Filter by district
     * @param float|null $customerLat Customer latitude
     * @param float|null $customerLon Customer longitude
     * @param string|null $date Target date (Y-m-d)
     * @return array
     */
    public function getAvailablePickupLocations(
        ?string $district = null,
        ?float $customerLat = null,
        ?float $customerLon = null,
        ?string $date = null
    ): array {
        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('is_active', true)->create();
            $locations = $this->pickupLocationRepository->getList($searchCriteria)->getItems();

            if (!$locations) {
                return [];
            }

            $result = [];
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $targetDate = new DateTime($date ?: 'today', $timezone);

            foreach ($locations as $location) {
                // Filter by district if provided
                if ($district && $location->getDistrict() !== $district) {
                    continue;
                }

                // Get available slots for this location
                $availableSlots = $this->getAvailablePickupSlots($location->getEntityId(), $date);
                if (!$availableSlots) {
                    continue;
                }

                $locationData = [
                    'entity_id' => $location->getEntityId(),
                    'name' => $location->getName(),
                    'code' => $location->getCode(),
                    'address' => $location->getAddress(),
                    'district' => $location->getDistrict(),
                    'latitude' => $location->getLatitude(),
                    'longitude' => $location->getLongitude(),
                    'priority' => $location->getPriority(),
                    'slots' => $availableSlots,
                ];

                // Calculate distance if coordinates provided
                if ($customerLat && $customerLon && $location->getLatitude() && $location->getLongitude()) {
                    $distance = $this->distanceCalculator->calculateDistance(
                        $customerLat,
                        $customerLon,
                        $location->getLatitude(),
                        $location->getLongitude()
                    );

                    // Filter by max distance if configured
                    $maxDistance = $this->configProvider->getMaxDistanceKm();
                    if ($maxDistance > 0 && $distance > $maxDistance) {
                        continue;
                    }

                    $locationData['distance_km'] = $distance;
                }

                $result[] = $locationData;
            }

            // Sort by priority and distance
            usort($result, function ($a, $b) {
                if ($a['priority'] !== $b['priority']) {
                    return $a['priority'] <=> $b['priority'];
                }

                if (isset($a['distance_km']) && isset($b['distance_km'])) {
                    return $a['distance_km'] <=> $b['distance_km'];
                }

                return 0;
            });

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting available pickup locations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available delivery slots
     *
     * @param string $district Delivery district
     * @param string|null $startDate Start date (Y-m-d)
     * @param string|null $endDate End date (Y-m-d)
     * @return array
     */
    public function getAvailableDeliverySlots(
        string $district,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        try {
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $start = new DateTime($startDate ?: 'today', $timezone);
            $end = new DateTime($endDate ?: '+30 days', $timezone);

            // Build search criteria
            $filters = [
                $this->filterBuilder->setField('district')->setValue($district)->setConditionType('eq')->create(),
                $this->filterBuilder->setField('is_active')->setValue(true)->setConditionType('eq')->create(),
                $this->filterBuilder->setField('slot_date')->setValue($start->format('Y-m-d'))->setConditionType('gteq')->create(),
                $this->filterBuilder->setField('slot_date')->setValue($end->format('Y-m-d'))->setConditionType('lteq')->create(),
            ];

            foreach ($filters as $filter) {
                $this->searchCriteriaBuilder->addFilter($filter->getField(), $filter->getValue(), $filter->getConditionType());
            }

            $searchCriteria = $this->searchCriteriaBuilder->create();
            $slots = $this->deliverySlotRepository->getList($searchCriteria)->getItems();

            if (!$slots) {
                return [];
            }

            $holidays = $this->getHolidayDates($start, $end);
            $result = [];

            foreach ($slots as $slot) {
                $slotDate = $slot->getSlotDate();

                // Skip if holiday
                if (in_array($slotDate, $holidays, true)) {
                    continue;
                }

                // Skip if weekend and weekends disabled
                if ($this->configProvider->isDeliveryDisabledOnWeekends()) {
                    $dateObj = new DateTime($slotDate, $timezone);
                    if (in_array((int)$dateObj->format('w'), [0, 6], true)) {
                        continue;
                    }
                }

                // Check capacity
                if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                    continue;
                }

                // Check cutoff
                if (!$this->isWithinCutoff($slotDate, $slot->getStartTime())) {
                    continue;
                }

                $result[] = [
                    'entity_id' => $slot->getEntityId(),
                    'district' => $slot->getDistrict(),
                    'date' => $slotDate,
                    'start_time' => $slot->getStartTime(),
                    'end_time' => $slot->getEndTime(),
                    'price' => $slot->getPrice(),
                    'capacity' => $slot->getCapacity(),
                    'available_spots' => $slot->getCapacity() - $slot->getUsedCapacity(),
                    'carrier_code' => $slot->getCarrierCode(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting available delivery slots: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available pickup slots for a location
     *
     * @param int $locationId Pickup location ID
     * @param string|null $date Target date
     * @return array
     */
    private function getAvailablePickupSlots(int $locationId, ?string $date = null): array
    {
        try {
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $targetDate = $date ?: 'today';

            // Build criteria
            $this->searchCriteriaBuilder->addFilter('pickup_location_id', $locationId, 'eq');
            $this->searchCriteriaBuilder->addFilter('is_active', true, 'eq');

            if ($date) {
                $this->searchCriteriaBuilder->addFilter('slot_date', $date, 'gteq');
            }

            $searchCriteria = $this->searchCriteriaBuilder->create();
            $slots = $this->pickupSlotRepository->getList($searchCriteria)->getItems();

            $holidays = $this->getHolidayDates(new DateTime('today', $timezone), new DateTime('+60 days', $timezone));
            $result = [];

            foreach ($slots as $slot) {
                $slotDate = $slot->getSlotDate();

                // Skip if holiday
                if (in_array($slotDate, $holidays, true)) {
                    continue;
                }

                // Skip if weekend and weekends disabled
                if ($this->configProvider->isPickupDisabledOnWeekends()) {
                    $dateObj = new DateTime($slotDate, $timezone);
                    if (in_array((int)$dateObj->format('w'), [0, 6], true)) {
                        continue;
                    }
                }

                // Check capacity
                if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                    continue;
                }

                $result[] = [
                    'entity_id' => $slot->getEntityId(),
                    'date' => $slotDate,
                    'start_time' => $slot->getStartTime(),
                    'end_time' => $slot->getEndTime(),
                    'available_spots' => $slot->getCapacity() - $slot->getUsedCapacity(),
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting available pickup slots: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get holiday dates in range
     *
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    private function getHolidayDates(DateTime $start, DateTime $end): array
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('holiday_date', $start->format('Y-m-d'), 'gteq')->addFilter('holiday_date', $end->format('Y-m-d'), 'lteq')->create();
            $holidays = $this->holidayRepository->getList($searchCriteria)->getItems();

            $dates = [];
            foreach ($holidays as $holiday) {
                $dates[] = $holiday->getHolidayDate();
            }

            return $dates;
        } catch (\Exception $e) {
            $this->logger->error('Error getting holidays: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if slot is within cutoff hour
     *
     * @param string $slotDate
     * @param string $slotStartTime
     * @return bool
     */
    private function isWithinCutoff(string $slotDate, string $slotStartTime): bool
    {
        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $now = new DateTime('now', $timezone);
        $cutoffHour = $this->configProvider->getDeliveryCutoffHour();

        $slotDateTime = new DateTime($slotDate . ' ' . $slotStartTime, $timezone);

        // Same day check
        if ($slotDate === $now->format('Y-m-d')) {
            return (int)$now->format('H') < $cutoffHour;
        }

        return true;
    }
}
