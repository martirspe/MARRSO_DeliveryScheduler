<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Cron;

use DateTime;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterfaceFactory;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterfaceFactory;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\Service\DistrictResolver;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Generate Slots Cron Job
 *
 * Automatically generates pickup and delivery slots for the next X days
 */
class GenerateSlots
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
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PickupSlotInterfaceFactory
     */
    private $pickupSlotFactory;

    /**
     * @var DeliverySlotInterfaceFactory
     */
    private $deliverySlotFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var DistrictResolver
     */
    private $districtResolver;

    public function __construct(
        PickupLocationRepositoryInterface $pickupLocationRepository,
        PickupSlotRepositoryInterface $pickupSlotRepository,
        DeliverySlotRepositoryInterface $deliverySlotRepository,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        PickupSlotInterfaceFactory $pickupSlotFactory,
        DeliverySlotInterfaceFactory $deliverySlotFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        DistrictResolver $districtResolver
    ) {
        $this->pickupLocationRepository = $pickupLocationRepository;
        $this->pickupSlotRepository = $pickupSlotRepository;
        $this->deliverySlotRepository = $deliverySlotRepository;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->pickupSlotFactory = $pickupSlotFactory;
        $this->deliverySlotFactory = $deliverySlotFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->districtResolver = $districtResolver;
    }

    /**
     * Execute cron job
     */
    public function execute(): void
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                return;
            }

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('Starting delivery scheduler slot generation');
            }

            $daysAhead = $this->configProvider->getSlotGenerationDaysAhead();
            $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());

            // Generate pickup slots
            if ($this->configProvider->isPickupEnabled()) {
                $this->generatePickupSlots($daysAhead, $timezone);
            }

            // Generate delivery slots
            if ($this->configProvider->isDeliveryEnabled()) {
                $this->generateDeliverySlots($daysAhead, $timezone);
            }

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('Completed delivery scheduler slot generation');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in slot generation cron: ' . $e->getMessage());
        }
    }

    /**
     * Generate pickup slots
     */
    private function generatePickupSlots(int $daysAhead, DateTimeZone $timezone): void
    {
        try {
            // Get all active pickup locations
            $searchCriteria = $this->createSearchCriteriaBuilder()->addFilter('is_active', 1)->create();
            $locations = $this->pickupLocationRepository->getList($searchCriteria)->getItems();

            if (!$locations) {
                $this->logger->info('No active pickup locations found');
                return;
            }

            $startTime = $this->configProvider->getSlotGenerationStartTime();
            $endTime = $this->configProvider->getSlotGenerationEndTime();
            $interval = $this->configProvider->getSlotIntervalMinutes();
            $capacity = $this->configProvider->getPickupDefaultCapacity();
            $disableWeekends = $this->configProvider->isPickupDisabledOnWeekends();

            $now = new DateTime('now', $timezone);
            $startDate = clone $now;
            $startDate->setTime(0, 0, 0);
            $endDate = (new DateTime('+' . $daysAhead . ' days', $timezone))->setTime(23, 59, 59);

            $generated = 0;

            foreach ($locations as $location) {
                if (!$location->getAutoGenerateSlots()) {
                    continue;
                }

                $currentDate = clone $startDate;

                while ($currentDate <= $endDate) {
                    // Skip weekends if configured
                    if ($disableWeekends && in_array((int)$currentDate->format('w'), [0, 6], true)) {
                        $currentDate->modify('+1 day');
                        continue;
                    }

                    // Check if slots already exist for this date
                    $dateStr = $currentDate->format('Y-m-d');
                    if (!$this->slotExistsForDate($location->getEntityId(), $dateStr, 'pickup')) {
                        // Generate slots for the day
                        $this->generateSlotsForDay(
                            $location->getEntityId(),
                            $dateStr,
                            $startTime,
                            $endTime,
                            $interval,
                            $capacity,
                            'pickup'
                        );
                        $generated++;
                    }

                    $currentDate->modify('+1 day');
                }
            }

            $this->logger->info(sprintf('Generated %d pickup slot sets', $generated));
        } catch (\Exception $e) {
            $this->logger->error('Error generating pickup slots: ' . $e->getMessage());
        }
    }

    /**
     * Generate delivery slots
     */
    private function generateDeliverySlots(int $daysAhead, DateTimeZone $timezone): void
    {
        try {
            $districts = $this->getDeliveryGenerationDistricts();
            if (!$districts) {
                $this->logger->info('No districts available for delivery slot generation');
                return;
            }

            $startTime = $this->configProvider->getSlotGenerationStartTime();
            $endTime = $this->configProvider->getSlotGenerationEndTime();
            $interval = $this->configProvider->getSlotIntervalMinutes();
            $capacity = $this->configProvider->getDeliveryDefaultCapacity();
            $price = $this->configProvider->getDefaultDeliveryPrice();
            $disableWeekends = $this->configProvider->isDeliveryDisabledOnWeekends();

            $now = new DateTime('now', $timezone);
            $startDate = clone $now;
            $startDate->setTime(0, 0, 0);
            $endDate = (new DateTime('+' . $daysAhead . ' days', $timezone))->setTime(23, 59, 59);

            $generated = 0;

            foreach ($districts as $district) {
                $currentDate = clone $startDate;

                while ($currentDate <= $endDate) {
                    if ($disableWeekends && in_array((int)$currentDate->format('w'), [0, 6], true)) {
                        $currentDate->modify('+1 day');
                        continue;
                    }

                    $dateStr = $currentDate->format('Y-m-d');
                    if (!$this->slotExistsForDate($district, $dateStr, 'delivery')) {
                        $this->generateDeliverySlotsForDay(
                            $district,
                            $dateStr,
                            $startTime,
                            $endTime,
                            $interval,
                            $capacity,
                            $price
                        );
                        $generated++;
                    }

                    $currentDate->modify('+1 day');
                }
            }

            $this->logger->info(sprintf('Generated %d delivery slot sets', $generated));
        } catch (\Exception $e) {
            $this->logger->error('Error generating delivery slots: ' . $e->getMessage());
        }
    }

    /**
     * Get districts that should be used for delivery slot generation
     */
    private function getDeliveryGenerationDistricts(): array
    {
        return $this->districtResolver->getActiveDistricts();
    }

    /**
     * Check if slots exist for a specific date
     */
    private function slotExistsForDate(string $scopeValue, string $date, string $type): bool
    {
        try {
            if ($type === 'pickup') {
                $searchCriteria = $this->createSearchCriteriaBuilder()
                    ->addFilter('pickup_location_id', (int)$scopeValue)
                    ->addFilter('slot_date', $date)
                    ->create();
                $collection = $this->pickupSlotRepository->getList($searchCriteria);
            } elseif ($type === 'delivery') {
                $searchCriteria = $this->createSearchCriteriaBuilder()
                    ->addFilter('district', $scopeValue)
                    ->addFilter('slot_date', $date)
                    ->create();
                $collection = $this->deliverySlotRepository->getList($searchCriteria);
            } else {
                return false;
            }

            return $collection->getTotalCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate delivery slots for a specific day
     */
    private function generateDeliverySlotsForDay(
        string $district,
        string $date,
        string $startTime,
        string $endTime,
        int $interval,
        int $capacity,
        float $price
    ): void {
        try {
            $start = DateTime::createFromFormat('H:i', $startTime);
            $end = DateTime::createFromFormat('H:i', $endTime);

            if (!$start || !$end) {
                throw new \InvalidArgumentException('Invalid slot generation time format');
            }

            while ($start < $end) {
                $slotEnd = clone $start;
                $slotEnd->add(new \DateInterval('PT' . $interval . 'M'));

                if ($slotEnd > $end) {
                    $slotEnd = $end;
                }

                $slot = $this->deliverySlotFactory->create();
                $slot->setDistrict($district);
                $slot->setSlotDate($date);
                $slot->setStartTime($start->format('H:i:s'));
                $slot->setEndTime($slotEnd->format('H:i:s'));
                $slot->setPrice($price);
                $slot->setServiceLevel(\MARRSO\DeliveryScheduler\Model\ServiceLevel::SCHEDULED);
                $slot->setCapacity($capacity);
                $slot->setUsedCapacity(0);
                $slot->setCarrierCode(null);
                $slot->setIsActive(true);

                $this->deliverySlotRepository->save($slot);

                $start = $slotEnd;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error generating delivery slots for day: ' . $e->getMessage());
        }
    }

    /**
     * Generate slots for a specific day
     */
    private function generateSlotsForDay(
        int $locationId,
        string $date,
        string $startTime,
        string $endTime,
        int $interval,
        int $capacity,
        string $type
    ): void {
        try {
            $start = DateTime::createFromFormat('H:i', $startTime);
            $end = DateTime::createFromFormat('H:i', $endTime);

            if (!$start || !$end) {
                throw new \InvalidArgumentException('Invalid slot generation time format');
            }

            while ($start < $end) {
                $slotEnd = clone $start;
                $slotEnd->add(new \DateInterval('PT' . $interval . 'M'));

                if ($slotEnd > $end) {
                    $slotEnd = $end;
                }

                if ($type === 'pickup') {
                    $slot = $this->pickupSlotFactory->create();
                    $slot->setPickupLocationId($locationId);
                    $slot->setSlotDate($date);
                    $slot->setStartTime($start->format('H:i:s'));
                    $slot->setEndTime($slotEnd->format('H:i:s'));
                    $slot->setCapacity($capacity);
                    $slot->setUsedCapacity(0);
                    $slot->setIsActive(true);

                    $this->pickupSlotRepository->save($slot);
                }

                $start = $slotEnd;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error generating slots for day: ' . $e->getMessage());
        }
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
