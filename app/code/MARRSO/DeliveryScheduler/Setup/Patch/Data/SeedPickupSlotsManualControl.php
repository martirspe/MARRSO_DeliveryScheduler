<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Data;

use DateInterval;
use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;
use MARRSO\DeliveryScheduler\Model\PickupLocationFactory;
use MARRSO\DeliveryScheduler\Model\PickupSlotFactory;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;

/**
 * Manual slot control demo + checkout test scenarios (today / tomorrow / day+2).
 *
 * Manual locations (auto_generate_slots = No): all seeded stores except lima-cron-demo.
 * Auto location (cron fills gaps): lima-cron-demo — run cron or open checkout to verify.
 */
class SeedPickupSlotsManualControl implements DataPatchInterface
{
    private const TIMEZONE = 'America/Lima';

    /** @var list<string> */
    private const MANUAL_LOCATION_CODES = [
        'lima-miraflores',
        'lima-sanisidro',
        'lima-surco',
        'lima-lamolina',
        'lima-sanborja',
        'lima-jesusmaria',
        'lima-callao',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupLocationFactory $pickupLocationFactory,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly PickupSlotFactory $pickupSlotFactory,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly DeliverySlotFactory $deliverySlotFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $cronDemoId = $this->ensureCronDemoLocation();
        $this->configureManualLocations();
        $this->clearSlotsForLocation($cronDemoId);
        $this->seedCheckoutTestScenarios();
        $this->seedDeliveryScenariosForToday();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function ensureCronDemoLocation(): int
    {
        $definition = [
            'code' => 'lima-cron-demo',
            'name' => 'Click&Collect Cron Demo (auto)',
            'address' => 'Av. Universitaria 1801, San Miguel, Lima, Perú',
            'district' => 'Lima',
            'latitude' => -12.07230000,
            'longitude' => -77.08210000,
            'priority' => 5,
            'brand' => 'MARRSO Demo',
            'location_references' => 'Punto de prueba: slots los crea el cron automáticamente',
            'opening_hours' => 'Lun-Dom 09:00-21:00',
            'retention_days' => 5,
            'auto_generate_slots' => true,
        ];

        return $this->ensurePickupLocation($definition);
    }

    private function configureManualLocations(): void
    {
        foreach (self::MANUAL_LOCATION_CODES as $code) {
            $location = $this->findLocationByCode($code);
            if (!$location) {
                continue;
            }

            $location->setAutoGenerateSlots(false);
            $this->pickupLocationRepository->save($location);
        }
    }

    private function seedCheckoutTestScenarios(): void
    {
        $today = $this->offsetDate(0);
        $tomorrow = $this->offsetDate(1);
        $dayAfter = $this->offsetDate(2);

        $scenarios = [
            // Miraflores — hoy: disponible, lleno e inactivo
            ['lima-miraflores', $today, '11:00:00', '13:00:00', 20, 5, true],
            ['lima-miraflores', $today, '14:00:00', '16:00:00', 10, 10, true],
            ['lima-miraflores', $today, '17:00:00', '19:00:00', 8, 0, false],
            ['lima-miraflores', $tomorrow, '10:00:00', '12:00:00', 18, 0, true],
            ['lima-miraflores', $tomorrow, '16:00:00', '18:00:00', 15, 14, true],

            // San Isidro — hoy con pocos cupos
            ['lima-sanisidro', $today, '09:00:00', '11:00:00', 25, 23, true],
            ['lima-sanisidro', $today, '15:00:00', '17:00:00', 30, 0, true],
            ['lima-sanisidro', $tomorrow, '12:00:00', '14:00:00', 20, 20, true],

            // Surco — mañana
            ['lima-surco', $tomorrow, '10:00:00', '12:00:00', 15, 1, true],
            ['lima-surco', $tomorrow, '16:00:00', '18:00:00', 20, 0, true],
            ['lima-surco', $dayAfter, '11:00:00', '13:00:00', 12, 0, true],

            // La Molina — retiro hoy
            ['lima-lamolina', $today, '12:00:00', '14:00:00', 12, 0, true],
            ['lima-lamolina', $today, '16:00:00', '18:00:00', 10, 9, true],

            // San Borja — casi lleno mañana
            ['lima-sanborja', $tomorrow, '18:00:00', '20:00:00', 15, 14, true],
            ['lima-sanborja', $today, '09:00:00', '12:00:00', 22, 4, true],

            // Jesús María — pasado mañana
            ['lima-jesusmaria', $dayAfter, '10:00:00', '12:00:00', 14, 0, true],
            ['lima-jesusmaria', $dayAfter, '14:00:00', '16:00:00', 14, 7, true],

            // Callao — hoy tarde
            ['lima-callao', $today, '13:00:00', '15:00:00', 18, 3, true],
            ['lima-callao', $today, '17:00:00', '19:00:00', 16, 16, true],
        ];

        foreach ($scenarios as [$code, $date, $start, $end, $capacity, $used, $active]) {
            $location = $this->findLocationByCode($code);
            if (!$location) {
                continue;
            }

            $this->createPickupSlotIfMissing(
                (int)$location->getEntityId(),
                $date,
                $start,
                $end,
                $capacity,
                $used,
                $active
            );
        }
    }

    private function seedDeliveryScenariosForToday(): void
    {
        $today = $this->offsetDate(0);
        $tomorrow = $this->offsetDate(1);

        $deliveryScenarios = [
            ['Lima', $today, '08:00:00', '11:00:00', 14.90, 30, 8],
            ['Lima', $today, '11:00:00', '14:00:00', 12.90, 25, 25],
            ['Lima', $today, '17:00:00', '20:00:00', 9.90, 20, 2],
            ['Miraflores', $today, '09:00:00', '12:00:00', 11.90, 22, 0],
            ['Miraflores', $tomorrow, '15:00:00', '18:00:00', 8.90, 18, 17],
            ['San Isidro', $today, '10:00:00', '13:00:00', 10.90, 24, 5],
            ['Surco', $tomorrow, '16:00:00', '19:00:00', 9.90, 18, 0],
            ['La Molina', $today, '12:00:00', '15:00:00', 11.90, 16, 15],
        ];

        foreach ($deliveryScenarios as [$district, $date, $start, $end, $price, $capacity, $used]) {
            if ($this->deliverySlotExists($district, $date, $start)) {
                continue;
            }

            $slot = $this->deliverySlotFactory->create();
            $slot->setDistrict($district);
            $slot->setSlotDate($date);
            $slot->setStartTime($start);
            $slot->setEndTime($end);
            $slot->setPrice($price);
            $slot->setServiceLevel(ServiceLevel::SCHEDULED);
            $slot->setCapacity($capacity);
            $slot->setUsedCapacity(min($used, $capacity));
            $slot->setCarrierCode('flatrate');
            $slot->setIsActive(true);
            $this->deliverySlotRepository->save($slot);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function ensurePickupLocation(array $definition): int
    {
        $location = $this->findLocationByCode((string)$definition['code']);
        if ($location) {
            $this->applyPickupLocationData($location, $definition);

            return (int)$location->getEntityId();
        }

        $location = $this->pickupLocationFactory->create();
        $this->applyPickupLocationData($location, $definition);

        return (int)$this->pickupLocationRepository->save($location)->getEntityId();
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function applyPickupLocationData(PickupLocationInterface $location, array $definition): void
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
        $location->setAutoGenerateSlots((bool)($definition['auto_generate_slots'] ?? true));
        $location->setIsActive(true);

        if ($location->getEntityId()) {
            $this->pickupLocationRepository->save($location);
        }
    }

    private function findLocationByCode(string $code): ?PickupLocationInterface
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('code', $code)
            ->setPageSize(1)
            ->create();

        $items = $this->pickupLocationRepository->getList($criteria)->getItems();

        return $items ? reset($items) : null;
    }

    private function clearSlotsForLocation(int $locationId): void
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('pickup_location_id', $locationId)
            ->create();

        foreach ($this->pickupSlotRepository->getList($criteria)->getItems() as $slot) {
            $this->pickupSlotRepository->delete($slot);
        }
    }

    private function createPickupSlotIfMissing(
        int $locationId,
        string $date,
        string $start,
        string $end,
        int $capacity,
        int $used,
        bool $active
    ): void {
        if ($this->pickupSlotExists($locationId, $date, $start)) {
            return;
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

    private function offsetDate(int $days): string
    {
        $date = new DateTime('today', $this->getTimezone());
        if ($days > 0) {
            $date->add(new DateInterval('P' . $days . 'D'));
        }

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
        return [SeedExtendedDemoData::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
