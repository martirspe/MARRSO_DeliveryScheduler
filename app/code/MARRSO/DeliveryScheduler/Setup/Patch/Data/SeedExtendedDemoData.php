<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Data;

use DateInterval;
use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;
use MARRSO\DeliveryScheduler\Model\HolidayFactory;
use MARRSO\DeliveryScheduler\Model\PickupLocationFactory;
use MARRSO\DeliveryScheduler\Model\PickupSlotFactory;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;

/**
 * Extended demo data: more pickup points, varied pickup slots, multi-district delivery and holidays.
 */
class SeedExtendedDemoData implements DataPatchInterface
{
    private const TIMEZONE = 'America/Lima';
    private const DAYS_AHEAD = 21;

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupLocationFactory $pickupLocationFactory,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly PickupSlotFactory $pickupSlotFactory,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly DeliverySlotFactory $deliverySlotFactory,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly HolidayFactory $holidayFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $locationIds = [];
        foreach ($this->getAllPickupLocationDefinitions() as $definition) {
            $locationIds[$definition['code']] = $this->ensurePickupLocation($definition);
        }

        $this->seedPickupSlotProfiles($locationIds);
        $this->seedMultiDistrictDeliverySlots();
        $this->seedAdditionalHolidays();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getAllPickupLocationDefinitions(): array
    {
        return [
            [
                'code' => 'lima-lamolina',
                'name' => 'Click&Collect La Molina',
                'address' => 'Av. Javier Prado Este 4200, La Molina, Lima, Perú',
                'district' => 'Lima',
                'latitude' => -12.07720000,
                'longitude' => -76.93480000,
                'priority' => 15,
                'brand' => 'Plaza Vea',
                'location_references' => 'Módulo B, zona de retiro express',
                'opening_hours' => 'Lun-Sáb 10:00-20:00',
                'retention_days' => 6,
            ],
            [
                'code' => 'lima-sanborja',
                'name' => 'Click&Collect San Borja',
                'address' => 'Av. San Luis 2850, San Borja, Lima, Perú',
                'district' => 'Lima',
                'latitude' => -12.10850000,
                'longitude' => -77.00120000,
                'priority' => 25,
                'brand' => 'Tottus',
                'location_references' => 'Planta baja, módulo de retiro web',
                'opening_hours' => 'Lun-Dom 09:00-21:00',
                'retention_days' => 5,
            ],
            [
                'code' => 'lima-jesusmaria',
                'name' => 'Click&Collect Jesús María',
                'address' => 'Av. Brasil 770, Jesús María, Lima, Perú',
                'district' => 'Lima',
                'latitude' => -12.07340000,
                'longitude' => -77.04670000,
                'priority' => 35,
                'brand' => 'Ripley',
                'location_references' => 'Nivel 1, counter Click&Collect',
                'opening_hours' => 'Lun-Dom 10:00-19:30',
                'retention_days' => 4,
            ],
            [
                'code' => 'lima-callao',
                'name' => 'Click&Collect Callao',
                'address' => 'Av. Oscar R. Benavides 3866, Callao, Lima, Perú',
                'district' => 'Lima',
                'latitude' => -12.05010000,
                'longitude' => -77.12540000,
                'priority' => 40,
                'brand' => 'Mall del Sur',
                'location_references' => 'Acceso principal, locker 12-18',
                'opening_hours' => 'Lun-Dom 11:00-20:00',
                'retention_days' => 7,
            ],
        ];
    }

    /**
     * @param array<string, int> $locationIds
     */
    private function seedPickupSlotProfiles(array $locationIds): void
    {
        $profiles = [
            'lima-miraflores' => [
                ['10:00:00', '12:00:00', 20, 3, true],
                ['12:00:00', '14:00:00', 18, 0, true],
                ['16:00:00', '19:00:00', 25, 12, true],
                ['19:00:00', '21:00:00', 8, 7, true],
                ['18:00:00', '20:00:00', 10, 0, false],
            ],
            'lima-sanisidro' => [
                ['09:00:00', '11:00:00', 30, 5, true],
                ['11:00:00', '13:00:00', 28, 0, true],
                ['13:00:00', '15:00:00', 22, 18, true],
                ['15:00:00', '17:00:00', 24, 0, true],
                ['17:00:00', '20:00:00', 20, 4, true],
            ],
            'lima-surco' => [
                ['11:00:00', '13:00:00', 16, 2, true],
                ['15:00:00', '18:00:00', 20, 0, true],
                ['18:00:00', '20:00:00', 12, 0, false],
                ['09:00:00', '11:00:00', 14, 14, true],
            ],
            'lima-lamolina' => [
                ['10:00:00', '12:00:00', 15, 0, true],
                ['12:00:00', '14:00:00', 15, 6, true],
                ['16:00:00', '18:00:00', 18, 0, true],
                ['18:00:00', '20:00:00', 10, 1, true],
            ],
            'lima-sanborja' => [
                ['09:00:00', '12:00:00', 22, 0, true],
                ['12:00:00', '15:00:00', 22, 9, true],
                ['15:00:00', '18:00:00', 20, 0, true],
                ['18:00:00', '21:00:00', 15, 3, true],
            ],
            'lima-jesusmaria' => [
                ['10:00:00', '13:00:00', 12, 0, true],
                ['13:00:00', '16:00:00', 12, 8, true],
                ['16:00:00', '19:00:00', 14, 0, true],
            ],
            'lima-callao' => [
                ['11:00:00', '14:00:00', 18, 0, true],
                ['14:00:00', '17:00:00', 18, 11, true],
                ['17:00:00', '20:00:00', 16, 0, true],
            ],
        ];

        foreach ($profiles as $code => $windows) {
            $locationId = $this->resolveLocationId($code, $locationIds);
            if (!$locationId) {
                continue;
            }

            foreach ($this->getUpcomingDates() as $date) {
                foreach ($windows as [$start, $end, $capacity, $used, $active]) {
                    if ($this->pickupSlotExists($locationId, $date, $start)) {
                        continue;
                    }

                    $slot = $this->pickupSlotFactory->create();
                    $slot->setPickupLocationId($locationId);
                    $slot->setSlotDate($date);
                    $slot->setStartTime($start);
                    $slot->setEndTime($end);
                    $slot->setCapacity($capacity);
                    $slot->setUsedCapacity(min($used, $capacity));
                    $slot->setIsActive($active);
                    $this->pickupSlotRepository->save($slot);
                }
            }
        }
    }

    private function seedMultiDistrictDeliverySlots(): void
    {
        $districtProfiles = [
            'Lima' => [
                ['08:00:00', '11:00:00', 14.90, 30],
                ['11:00:00', '14:00:00', 12.90, 28],
                ['14:00:00', '17:00:00', 9.90, 25],
                ['17:00:00', '20:00:00', 11.90, 20],
            ],
            'Miraflores' => [
                ['09:00:00', '12:00:00', 11.90, 22],
                ['12:00:00', '15:00:00', 9.90, 22],
                ['15:00:00', '18:00:00', 8.90, 18],
                ['18:00:00', '21:00:00', 10.90, 15],
            ],
            'San Isidro' => [
                ['09:00:00', '12:00:00', 12.90, 24],
                ['12:00:00', '15:00:00', 10.90, 24],
                ['15:00:00', '18:00:00', 9.90, 20],
            ],
            'Surco' => [
                ['10:00:00', '13:00:00', 10.90, 20],
                ['13:00:00', '16:00:00', 9.90, 20],
                ['16:00:00', '19:00:00', 8.90, 18],
            ],
            'La Molina' => [
                ['10:00:00', '13:00:00', 11.90, 16],
                ['15:00:00', '18:00:00', 9.90, 16],
                ['18:00:00', '21:00:00', 12.90, 12],
            ],
            'San Borja' => [
                ['09:00:00', '12:00:00', 10.90, 18],
                ['14:00:00', '17:00:00', 9.90, 18],
                ['17:00:00', '20:00:00', 11.90, 14],
            ],
        ];

        foreach ($districtProfiles as $district => $windows) {
            foreach ($this->getUpcomingDates() as $date) {
                foreach ($windows as [$start, $end, $price, $capacity]) {
                    if ($this->deliverySlotExists($district, $date, $start)) {
                        continue;
                    }

                    $used = 0;
                    if ($district === 'San Isidro' && $start === '12:00:00') {
                        $used = (int)floor($capacity * 0.75);
                    }
                    if ($district === 'Miraflores' && $start === '15:00:00') {
                        $used = $capacity - 2;
                    }

                    $slot = $this->deliverySlotFactory->create();
                    $slot->setDistrict($district);
                    $slot->setSlotDate($date);
                    $slot->setStartTime($start);
                    $slot->setEndTime($end);
                    $slot->setPrice($price);
                    $slot->setServiceLevel(ServiceLevel::SCHEDULED);
                    $slot->setCapacity($capacity);
                    $slot->setUsedCapacity($used);
                    $slot->setCarrierCode('flatrate');
                    $slot->setIsActive(true);
                    $this->deliverySlotRepository->save($slot);
                }
            }
        }
    }

    private function seedAdditionalHolidays(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'description' => 'Año Nuevo'],
            ['date' => '2026-05-01', 'description' => 'Día del Trabajo'],
            ['date' => '2026-06-29', 'description' => 'San Pedro y San Pablo'],
            ['date' => '2026-08-30', 'description' => 'Santa Rosa de Lima'],
            ['date' => '2026-10-08', 'description' => 'Combate de Angamos'],
            ['date' => '2026-11-01', 'description' => 'Día de Todos los Santos'],
            [
                'date' => $this->formatDate((new DateTime('now', $this->getTimezone()))->add(new DateInterval('P45D'))),
                'description' => 'Mantenimiento programado (demo)',
            ],
        ];

        foreach ($holidays as $holidayData) {
            if ($this->holidayExists($holidayData['date'])) {
                continue;
            }

            $holiday = $this->holidayFactory->create();
            $holiday->setHolidayDate($holidayData['date']);
            $holiday->setDescription($holidayData['description']);
            $this->holidayRepository->save($holiday);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function ensurePickupLocation(array $definition): int
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('code', $definition['code'])
            ->setPageSize(1)
            ->create();

        $items = $this->pickupLocationRepository->getList($criteria)->getItems();
        if ($items) {
            $location = reset($items);
            $this->applyPickupLocationData($location, $definition);

            return (int)$location->getEntityId();
        }

        $location = $this->pickupLocationFactory->create();
        $this->applyPickupLocationData($location, $definition);

        return (int)$this->pickupLocationRepository->save($location)->getEntityId();
    }

    /**
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface $location
     * @param array<string, mixed> $definition
     */
    private function applyPickupLocationData($location, array $definition): void
    {
        $location->setCode((string)$definition['code']);
        $location->setName((string)$definition['name']);
        $location->setAddress((string)$definition['address']);
        $location->setDistrict((string)$definition['district']);
        $location->setLatitude((float)$definition['latitude']);
        $location->setLongitude((float)$definition['longitude']);
        $location->setPriority((int)$definition['priority']);
        $location->setBrand((string)($definition['brand'] ?? 'MARRSO'));
        $location->setLocationReferences((string)($definition['location_references'] ?? ''));
        $location->setOpeningHours((string)($definition['opening_hours'] ?? ''));
        $location->setRetentionDays((int)($definition['retention_days'] ?? 5));
        $location->setIsActive(true);

        if ($location->getEntityId()) {
            $this->pickupLocationRepository->save($location);
        }
    }

    /**
     * @param array<string, int> $locationIds
     */
    private function resolveLocationId(string $code, array $locationIds): ?int
    {
        if (isset($locationIds[$code])) {
            return $locationIds[$code];
        }

        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('code', $code)
            ->setPageSize(1)
            ->create();

        $items = $this->pickupLocationRepository->getList($criteria)->getItems();
        if (!$items) {
            return null;
        }

        $location = reset($items);

        return (int)$location->getEntityId();
    }

    /**
     * @return list<string>
     */
    private function getUpcomingDates(): array
    {
        $dates = [];
        $current = new DateTime('today', $this->getTimezone());

        for ($day = 0; $day < self::DAYS_AHEAD; $day++) {
            $dates[] = $this->formatDate($current);
            $current->add(new DateInterval('P1D'));
        }

        return $dates;
    }

    private function pickupSlotExists(int $locationId, string $date, string $startTime): bool
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $locationId)
            ->addFilter('slot_date', $date)
            ->addFilter('start_time', $startTime)
            ->setPageSize(1)
            ->create();

        return $this->pickupSlotRepository->getList($criteria)->getTotalCount() > 0;
    }

    private function deliverySlotExists(string $district, string $date, string $startTime): bool
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('slot_date', $date)
            ->addFilter('start_time', $startTime)
            ->setPageSize(1)
            ->create();

        return $this->deliverySlotRepository->getList($criteria)->getTotalCount() > 0;
    }

    private function holidayExists(string $date): bool
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('holiday_date', $date)
            ->setPageSize(1)
            ->create();

        return $this->holidayRepository->getList($criteria)->getTotalCount() > 0;
    }

    private function formatDate(DateTime $date): string
    {
        return $date->format('Y-m-d');
    }

    private function getTimezone(): DateTimeZone
    {
        return new DateTimeZone(self::TIMEZONE);
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }

    public static function getDependencies(): array
    {
        return [SeedLimaDemoData::class, SeedExpressDeliverySlots::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
