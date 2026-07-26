<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;

/**
 * Core service for determining availability of pickup points and delivery slots.
 */
class AvailabilityEngine
{
    public const CACHE_TAG = 'marrso_delivery_scheduler';

    public function __construct(
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
        private readonly DistanceCalculator $distanceCalculator,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer
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

            $cacheKey = $this->buildCacheKey('pickup', [
                $district,
                $customerLat !== null ? round($customerLat, 2) : null,
                $customerLon !== null ? round($customerLon, 2) : null,
                $date,
            ]);
            $cached = $this->loadCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $searchCriteria = $this->createSearchCriteriaBuilder()
                ->addFilter('is_active', true)
                ->create();
            $locations = $this->pickupLocationRepository->getList($searchCriteria)->getItems();

            if (!$locations) {
                return [];
            }

            $result = [];
            $district = $this->normalizeDistrict($district);
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $daysAhead = max($this->configProvider->getSlotGenerationDaysAhead(), 90);
            $holidayEnd = new DateTime('+' . $daysAhead . ' days', $timezone);
            $holidays = $this->getHolidayDates(new DateTime('today', $timezone), $holidayEnd);

            $locationIds = [];
            foreach ($locations as $location) {
                $locationDistrict = $this->normalizeDistrict($location->getDistrict());
                if ($district !== '' && $locationDistrict !== '' && strcasecmp($locationDistrict, $district) !== 0) {
                    continue;
                }
                $locationIds[] = (int)$location->getEntityId();
            }

            $slotsByLocation = $this->loadPickupSlotsByLocation($locationIds, $date, $timezone, $daysAhead, $holidays);

            foreach ($locations as $location) {
                $locationId = (int)$location->getEntityId();
                if (!isset($slotsByLocation[$locationId])) {
                    continue;
                }

                $locationDistrict = $this->normalizeDistrict($location->getDistrict());
                if ($district !== '' && $locationDistrict !== '' && strcasecmp($locationDistrict, $district) !== 0) {
                    continue;
                }

                $availableSlots = $slotsByLocation[$locationId];
                if (!$availableSlots) {
                    continue;
                }

                $locationData = [
                    'entity_id' => $location->getEntityId(),
                    'name' => $location->getName(),
                    'code' => $location->getCode(),
                    'address' => $location->getAddress(),
                    'district' => $location->getDistrict(),
                    'brand' => $location->getBrand(),
                    'location_references' => $location->getLocationReferences(),
                    'opening_hours' => $location->getOpeningHours(),
                    'retention_days' => $location->getRetentionDays(),
                    'latitude' => $location->getLatitude(),
                    'longitude' => $location->getLongitude(),
                    'priority' => $location->getPriority(),
                    'distance_km' => null,
                    'badge' => $this->buildPickupBadge($availableSlots),
                    'slots' => $availableSlots,
                    'dates' => $this->groupSlotsByDate($availableSlots),
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

            $this->saveCache($cacheKey, $result);

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
        ?string $endDate = null,
        ?string $serviceLevel = null
    ): array {
        try {
            if (!$this->configProvider->isEnabled() || !$this->configProvider->isDeliveryEnabled()) {
                return [];
            }

            $district = $this->normalizeDistrict($district);
            if ($district === '') {
                return [];
            }

            $cacheKey = $this->buildCacheKey('delivery', [$district, $startDate, $endDate, $serviceLevel]);
            $cached = $this->loadCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $start = new DateTime($startDate ?: 'today', $timezone);
            $daysAhead = max($this->configProvider->getSlotGenerationDaysAhead(), 90);
            $end = new DateTime($endDate ?: ('+' . $daysAhead . ' days'), $timezone);

            $searchCriteria = $this->createSearchCriteriaBuilder()
                ->addFilter('is_active', true)
                ->addFilter('district', $district)
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
                if (!$this->districtMatches($slot->getDistrict(), $district)) {
                    continue;
                }

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

                $level = ServiceLevel::normalize($slot->getServiceLevel());
                if ($serviceLevel !== null && $serviceLevel !== '' && $level !== ServiceLevel::normalize($serviceLevel)) {
                    continue;
                }

                if (!$this->isServiceLevelAvailable($level, $slotDate, $slot->getStartTime())) {
                    continue;
                }

                $result[] = [
                    'entity_id' => $slot->getEntityId(),
                    'district' => $slot->getDistrict(),
                    'date' => $slotDate,
                    'start_time' => $slot->getStartTime(),
                    'end_time' => $slot->getEndTime(),
                    'price' => $slot->getPrice(),
                    'service_level' => $level,
                    'capacity' => $slot->getCapacity(),
                    'available_spots' => $slot->getCapacity() - $slot->getUsedCapacity(),
                    'carrier_code' => $slot->getCarrierCode(),
                ];
            }

            $this->saveCache($cacheKey, $result);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Error getting available delivery slots: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<int, string> $holidays
     * @return array<int, list<array<string, mixed>>>
     */
    private function loadPickupSlotsByLocation(
        array $locationIds,
        ?string $date,
        DateTimeZone $timezone,
        int $daysAhead,
        array $holidays
    ): array {
        if (!$locationIds) {
            return [];
        }

        $startDate = $date ?: (new DateTime('today', $timezone))->format('Y-m-d');
        $endDate = (new DateTime('+' . $daysAhead . ' days', $timezone))->format('Y-m-d');

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $locationIds, 'in')
            ->addFilter('is_active', true)
            ->addFilter('slot_date', $startDate, 'gteq')
            ->addFilter('slot_date', $endDate, 'lteq')
            ->create();

        $grouped = array_fill_keys($locationIds, []);
        foreach ($this->pickupSlotRepository->getList($criteria)->getItems() as $slot) {
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

            if (!$this->isWithinCutoff($slotDate, $slot->getStartTime(), true)) {
                continue;
            }

            $locationId = (int)$slot->getPickupLocationId();
            $grouped[$locationId][] = [
                'entity_id' => $slot->getEntityId(),
                'date' => $slotDate,
                'start_time' => $slot->getStartTime(),
                'end_time' => $slot->getEndTime(),
                'available_spots' => $slot->getCapacity() - $slot->getUsedCapacity(),
            ];
        }

        return $grouped;
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

    private function isWithinCutoff(string $slotDate, string $slotStartTime, bool $isPickup = false): bool
    {
        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $now = new DateTime('now', $timezone);
        $cutoffHour = $isPickup
            ? $this->configProvider->getPickupCutoffHour()
            : $this->configProvider->getDeliveryCutoffHour();

        if ($slotDate === $now->format('Y-m-d')) {
            return (int)$now->format('H') < $cutoffHour;
        }

        return true;
    }

    private function districtMatches(?string $slotDistrict, string $customerDistrict): bool
    {
        $slotDistrict = $this->normalizeDistrict($slotDistrict);
        if ($slotDistrict === '') {
            return true;
        }

        return strcasecmp($slotDistrict, $customerDistrict) === 0;
    }

    private function normalizeDistrict(mixed $district): string
    {
        return trim((string)$district);
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     * @return array<int, array<string, mixed>>
     */
    private function groupSlotsByDate(array $slots): array
    {
        $grouped = [];

        foreach ($slots as $slot) {
            $date = (string)($slot['date'] ?? '');
            if ($date === '') {
                continue;
            }

            if (!isset($grouped[$date])) {
                $grouped[$date] = [
                    'date' => $date,
                    'label' => $this->formatDateLabel($date),
                    'slots' => [],
                ];
            }

            $grouped[$date]['slots'][] = $slot;
        }

        ksort($grouped);

        return array_values($grouped);
    }

    /**
     * @param array<int, array<string, mixed>> $slots
     */
    private function buildPickupBadge(array $slots): string
    {
        if (!$slots) {
            return '';
        }

        $dates = array_unique(array_map(static fn (array $slot): string => (string)$slot['date'], $slots));
        sort($dates);
        $earliest = $dates[0] ?? '';

        if ($earliest === '') {
            return '';
        }

        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $today = (new DateTime('today', $timezone))->format('Y-m-d');
        $tomorrow = (new DateTime('tomorrow', $timezone))->format('Y-m-d');

        if ($earliest === $today) {
            return (string)__('Pick up today');
        }

        if ($earliest === $tomorrow) {
            return (string)__('Pick up tomorrow');
        }

        return (string)__('Pick up from %1', $this->formatDateLabel($earliest));
    }

    private function formatDateLabel(string $date): string
    {
        try {
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
            $dateObj = new DateTime($date, $timezone);
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            return $dayNames[(int)$dateObj->format('w')] . ' ' . $dateObj->format('d/m');
        } catch (\Exception $e) {
            return $date;
        }
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
        if (strlen($time) === 5) {
            return $time . ':00';
        }

        return $time;
    }

    /**
     * @param array<int, mixed> $parts
     */
    private function buildCacheKey(string $type, array $parts): string
    {
        return self::CACHE_TAG . '_' . $type . '_' . sha1($this->serializer->serialize($parts));
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function loadCache(string $cacheKey): ?array
    {
        $lifetime = $this->configProvider->getCacheLifetime();
        if ($lifetime <= 0) {
            return null;
        }

        $cached = $this->cache->load($cacheKey);
        if ($cached === false) {
            return null;
        }

        try {
            $data = $this->serializer->unserialize($cached);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    private function saveCache(string $cacheKey, array $data): void
    {
        $lifetime = $this->configProvider->getCacheLifetime();
        if ($lifetime <= 0) {
            return;
        }

        $this->cache->save(
            $this->serializer->serialize($data),
            $cacheKey,
            [self::CACHE_TAG],
            $lifetime
        );
    }
}
