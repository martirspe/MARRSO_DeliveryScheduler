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

/**
 * Demo seed data for Lima, Peru (pickup points, slots and holidays).
 */
class SeedLimaDemoData implements DataPatchInterface
{
    private const DISTRICT = 'Lima';
    private const TIMEZONE = 'America/Lima';
    private const DAYS_AHEAD = 14;

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

        foreach ($this->getPickupLocationDefinitions() as $definition) {
            $locationId = $this->ensurePickupLocation($definition);
            $this->seedPickupSlots($locationId);
        }

        $this->seedDeliverySlots();
        $this->seedHolidays();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getPickupLocationDefinitions(): array
    {
        return [
            [
                'code' => 'lima-miraflores',
                'name' => 'Click&Collect Miraflores',
                'address' => 'Av. Larco 345, Miraflores, Lima, Perú',
                'district' => self::DISTRICT,
                'latitude' => -12.12100000,
                'longitude' => -77.02850000,
                'priority' => 10,
                'brand' => 'Mallplaza',
                'location_references' => 'Nivel 2, frente a cajas centrales',
                'opening_hours' => 'Lun-Dom 11:00-19:00',
                'retention_days' => 7,
            ],
            [
                'code' => 'lima-sanisidro',
                'name' => 'Click&Collect San Isidro',
                'address' => 'Av. República de Panamá 3450, San Isidro, Lima, Perú',
                'district' => self::DISTRICT,
                'latitude' => -12.09690000,
                'longitude' => -77.03370000,
                'priority' => 20,
                'brand' => 'Falabella',
                'location_references' => 'Nivel 1, al costado de atención al cliente',
                'opening_hours' => 'Lun-Dom 09:30-20:30',
                'retention_days' => 5,
            ],
            [
                'code' => 'lima-surco',
                'name' => 'Click&Collect Santiago de Surco',
                'address' => 'Av. Primavera 1234, Santiago de Surco, Lima, Perú',
                'district' => self::DISTRICT,
                'latitude' => -12.14010000,
                'longitude' => -76.99180000,
                'priority' => 30,
                'brand' => 'Open Plaza',
                'location_references' => 'Nivel 2, estacionamiento naranja',
                'opening_hours' => 'Lun-Dom 11:00-19:00',
                'retention_days' => 5,
            ],
        ];
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

    private function seedPickupSlots(int $locationId): void
    {
        $windows = [
            ['09:00:00', '12:00:00', 15],
            ['14:00:00', '18:00:00', 15],
        ];

        foreach ($this->getUpcomingDates() as $date) {
            foreach ($windows as [$startTime, $endTime, $capacity]) {
                if ($this->pickupSlotExists($locationId, $date, $startTime)) {
                    continue;
                }

                $slot = $this->pickupSlotFactory->create();
                $slot->setPickupLocationId($locationId);
                $slot->setSlotDate($date);
                $slot->setStartTime($startTime);
                $slot->setEndTime($endTime);
                $slot->setCapacity($capacity);
                $slot->setUsedCapacity(0);
                $slot->setIsActive(true);

                $this->pickupSlotRepository->save($slot);
            }
        }
    }

    private function seedDeliverySlots(): void
    {
        $windows = [
            ['10:00:00', '13:00:00', 12.90, 25],
            ['16:00:00', '19:00:00', 9.90, 25],
        ];

        foreach ($this->getUpcomingDates() as $date) {
            foreach ($windows as [$startTime, $endTime, $price, $capacity]) {
                if ($this->deliverySlotExists(self::DISTRICT, $date, $startTime)) {
                    continue;
                }

                $slot = $this->deliverySlotFactory->create();
                $slot->setDistrict(self::DISTRICT);
                $slot->setSlotDate($date);
                $slot->setStartTime($startTime);
                $slot->setEndTime($endTime);
                $slot->setPrice($price);
                $slot->setServiceLevel(\MARRSO\DeliveryScheduler\Model\ServiceLevel::SCHEDULED);
                $slot->setCapacity($capacity);
                $slot->setUsedCapacity(0);
                $slot->setCarrierCode('flatrate');
                $slot->setIsActive(true);

                $this->deliverySlotRepository->save($slot);
            }
        }
    }

    private function seedHolidays(): void
    {
        $holidays = [
            [
                'date' => $this->formatDate((new DateTime('now', $this->getTimezone()))->add(new DateInterval('P90D'))),
                'description' => 'Día festivo demo (sin entregas)',
            ],
            [
                'date' => '2026-07-28',
                'description' => 'Fiestas Patrias del Perú',
            ],
            [
                'date' => '2026-12-25',
                'description' => 'Navidad',
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
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
