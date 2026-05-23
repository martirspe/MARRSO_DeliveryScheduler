<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use DateTime;
use DateTimeZone;
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
    public function __construct(
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
        private readonly DistanceCalculator $distanceCalculator,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailablePickupLocations(
        ?string $district = null,
        ?float $customerLat = null,
        ?float $customerLon = null,
        ?string $date = null
    ): array {
        try {
            if (!$this->configProvider->isEnabled() || !$this->configProvider->isPickupEnabled()) {
                return [];
            }

            $searchCriteria = $this->createSearchCriteriaBuilder()
                ->addFilter('is_active', true)
                ->create();
            $locations = $this->pickupLocationRepository->getList($searchCriteria)->getItems();

            if (!$locations) {
                return [];
            }

            $result = [];
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());

            foreach ($locations as $location) {
                if ($district && strcasecmp((string)$location->getDistrict(), $district) !== 0) {
                    continue;
                }

                $availableSlots = $this->getAvailablePickupSlots((int)$location->getEntityId(), $date);
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

                if ($customerLat !== null && $customerLon !== null
                    && $location->getLatitude() !== null && $location->getLongitude() !== null) {
                    $distance = $this->distanceCalculator->calculateDistance(
                        $customerLat,
                        $customerLon,
                        $location->getLatitude(),
                        $location->getLongitude()
                    );

                    $maxDistance = $this->configProvider->getMaxDistanceKm();
                    if ($maxDistance > 0 && $distance > $maxDistance) {
                        continue;
                    }

                    $locationData['distance_km'] = $distance;
                }

                $result[] = $locationData;
            }

            usort($result, static function (array $a, array $b): int {
                if ($a['priority'] !== $b['priority']) {
                    return $a['priority'] <=> $b['priority'];
                }

                if (isset($a['distance_km'], $b['distance_km'])) {
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
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableDeliverySlots(
        string $district,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        try {
            if (!$this->configProvider->isEnabled() || !$this->configProvider->isDeliveryEnabled()) {
                return [];
            }

            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $start = new DateTime($startDate ?: 'today', $timezone);
            $end = new DateTime($endDate ?: '+30 days', $timezone);

            $searchCriteria = $this->createSearchCriteriaBuilder()
                ->addFilter('district', $district)
                ->addFilter('is_active', true)
                ->addFilter('slot_date', $start->format('Y-m-d'), 'gteq')
                ->addFilter('slot_date', $end->format('Y-m-d'), 'lteq')
                ->create();

            $slots = $this->deliverySlotRepository->getList($searchCriteria)->getItems();
            if (!$slots) {
                return [];
            }

            $holidays = $this->getHolidayDates($start, $end);
            $result = [];

            foreach ($slots as $slot) {
                $slotDate = $slot->getSlotDate();

                if (in_array($slotDate, $holidays, true)) {
                    continue;
                }

                if ($this->configProvider->isDeliveryDisabledOnWeekends()) {
                    $dateObj = new DateTime($slotDate, $timezone);
                    if (in_array((int)$dateObj->format('w'), [0, 6], true)) {
                        continue;
                    }
                }

                if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                    continue;
                }

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
     * @return array<int, array<string, mixed>>
     */
    private function getAvailablePickupSlots(int $locationId, ?string $date = null): array
    {
        try {
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $builder = $this->createSearchCriteriaBuilder()
                ->addFilter('pickup_location_id', $locationId)
                ->addFilter('is_active', true);

            if ($date) {
                $builder->addFilter('slot_date', $date, 'gteq');
            }

            $slots = $this->pickupSlotRepository->getList($builder->create())->getItems();
            $holidays = $this->getHolidayDates(
                new DateTime('today', $timezone),
                new DateTime('+60 days', $timezone)
            );

            $result = [];
            foreach ($slots as $slot) {
                $slotDate = $slot->getSlotDate();

                if (in_array($slotDate, $holidays, true)) {
                    continue;
                }

                if ($this->configProvider->isPickupDisabledOnWeekends()) {
                    $dateObj = new DateTime($slotDate, $timezone);
                    if (in_array((int)$dateObj->format('w'), [0, 6], true)) {
                        continue;
                    }
                }

                if ($slot->getUsedCapacity() >= $slot->getCapacity()) {
                    continue;
                }

                if (!$this->isWithinCutoff($slotDate, $slot->getStartTime())) {
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
     * @return array<int, string>
     */
    private function getHolidayDates(DateTime $start, DateTime $end): array
    {
        try {
            $searchCriteria = $this->createSearchCriteriaBuilder()
                ->addFilter('holiday_date', $start->format('Y-m-d'), 'gteq')
                ->addFilter('holiday_date', $end->format('Y-m-d'), 'lteq')
                ->create();

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

    private function isWithinCutoff(string $slotDate, string $slotStartTime): bool
    {
        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $now = new DateTime('now', $timezone);
        $cutoffHour = $this->configProvider->getDeliveryCutoffHour();

        if ($slotDate === $now->format('Y-m-d')) {
            return (int)$now->format('H') < $cutoffHour;
        }

        return true;
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
