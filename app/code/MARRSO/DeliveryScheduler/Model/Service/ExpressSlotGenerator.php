<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use DateInterval;
use DateTime;
use DateTimeZone;
use Magento\Framework\Api\SearchCriteriaBuilder;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;
use Psr\Log\LoggerInterface;

/**
 * Generates and refreshes express delivery slots (180 min and 24 h).
 */
class ExpressSlotGenerator
{
    private const EXPRESS_CAPACITY = 15;
    private const MAX_END_HOUR = 21;

    public function __construct(
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly DeliverySlotFactory $deliverySlotFactory,
        private readonly ConfigProvider $configProvider,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly DistrictResolver $districtResolver,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Regenerate all express slot types for every configured district.
     */
    public function generate(): int
    {
        return $this->generateInternal(false);
    }

    /**
     * Refresh only 180-minute windows (intended for hourly cron).
     */
    public function refreshExpress180(): int
    {
        return $this->generateInternal(true);
    }

    private function generateInternal(bool $express180Only): int
    {
        if (!$this->configProvider->isEnabled() || !$this->configProvider->isDeliveryEnabled()) {
            return 0;
        }

        $timezone = new DateTimeZone($this->configProvider->getDefaultTimezone());
        $today = new DateTime('today', $timezone);
        $tomorrow = (clone $today)->add(new DateInterval('P1D'));
        $now = new DateTime('now', $timezone);
        $created = 0;

        foreach ($this->districtResolver->getActiveDistricts() as $district) {
            if ($this->configProvider->isExpress180Enabled()) {
                $this->deactivateStaleExpress180Slots($district, $now, $timezone);
                $created += $this->ensureExpress180Slots($district, $today, $now);
            }

            if (!$express180Only && $this->configProvider->isExpress24Enabled()) {
                $created += $this->ensureExpress24Slots($district, $today, $tomorrow);
            }
        }

        if ($created > 0 && $this->configProvider->isLoggingEnabled()) {
            $this->logger->info(sprintf('MARRSO express slots: %d slot(s) created or ensured.', $created));
        }

        return $created;
    }

    private function ensureExpress180Slots(string $district, DateTime $today, DateTime $now): int
    {
        $created = 0;
        $baseHour = (int)$now->format('H') + 2;
        $windows = [
            [max($baseHour, 9), max($baseHour + 1, 10), $this->configProvider->getExpress180Price()],
            [max($baseHour + 1, 10), min(max($baseHour + 3, 12), self::MAX_END_HOUR), $this->configProvider->getExpress180Price()],
        ];

        foreach ($windows as [$startHour, $endHour, $price]) {
            if ($startHour >= self::MAX_END_HOUR || $endHour > self::MAX_END_HOUR || $startHour >= $endHour) {
                continue;
            }

            if ($this->createSlotIfMissing(
                $district,
                $today->format('Y-m-d'),
                sprintf('%02d:00:00', $startHour),
                sprintf('%02d:00:00', $endHour),
                $price,
                ServiceLevel::EXPRESS_180
            )) {
                $created++;
            }
        }

        return $created;
    }

    private function ensureExpress24Slots(string $district, DateTime $today, DateTime $tomorrow): int
    {
        $created = 0;

        foreach ([$today, $tomorrow] as $date) {
            if ($this->configProvider->isDeliveryDisabledOnWeekends()
                && in_array((int)$date->format('w'), [0, 6], true)) {
                continue;
            }

            foreach ([
                ['09:00:00', '13:00:00'],
                ['14:00:00', '18:00:00'],
            ] as [$startTime, $endTime]) {
                if ($this->createSlotIfMissing(
                    $district,
                    $date->format('Y-m-d'),
                    $startTime,
                    $endTime,
                    $this->configProvider->getExpress24Price(),
                    ServiceLevel::EXPRESS_24
                )) {
                    $created++;
                }
            }
        }

        return $created;
    }

    private function deactivateStaleExpress180Slots(string $district, DateTime $now, DateTimeZone $timezone): void
    {
        $today = (new DateTime('today', $timezone))->format('Y-m-d');
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('service_level', ServiceLevel::EXPRESS_180)
            ->addFilter('slot_date', $today)
            ->addFilter('is_active', true)
            ->create();

        foreach ($this->deliverySlotRepository->getList($criteria)->getItems() as $slot) {
            $slotStart = DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $slot->getSlotDate() . ' ' . $this->normalizeTime($slot->getStartTime()),
                $timezone
            );

            if (!$slotStart) {
                continue;
            }

            $minutesUntilStart = ($slotStart->getTimestamp() - $now->getTimestamp()) / 60;
            if ($minutesUntilStart < 0) {
                $slot->setIsActive(false);
                $this->deliverySlotRepository->save($slot);
            }
        }
    }

    private function createSlotIfMissing(
        string $district,
        string $date,
        string $startTime,
        string $endTime,
        float $price,
        string $serviceLevel
    ): bool {
        $criteria = $this->createSearchCriteriaBuilder()
            ->addFilter('district', $district)
            ->addFilter('slot_date', $date)
            ->addFilter('start_time', $startTime)
            ->addFilter('service_level', $serviceLevel)
            ->setPageSize(1)
            ->create();

        if ($this->deliverySlotRepository->getList($criteria)->getTotalCount() > 0) {
            return false;
        }

        $slot = $this->deliverySlotFactory->create();
        $slot->setDistrict($district);
        $slot->setSlotDate($date);
        $slot->setStartTime($startTime);
        $slot->setEndTime($endTime);
        $slot->setPrice($price);
        $slot->setServiceLevel($serviceLevel);
        $slot->setCapacity(self::EXPRESS_CAPACITY);
        $slot->setUsedCapacity(0);
        $slot->setCarrierCode('flatrate');
        $slot->setIsActive(true);

        $this->deliverySlotRepository->save($slot);

        return true;
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
