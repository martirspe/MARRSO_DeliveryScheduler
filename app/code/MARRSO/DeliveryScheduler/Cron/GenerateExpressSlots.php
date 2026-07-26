<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Cron;

use MARRSO\DeliveryScheduler\Model\Service\ExpressSlotGenerator;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

/**
 * Daily and hourly regeneration of express delivery slots.
 */
class GenerateExpressSlots
{
    public function __construct(
        private readonly ExpressSlotGenerator $expressSlotGenerator,
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Daily job: express 24 h + express 180 min for all districts.
     */
    public function execute(): void
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                return;
            }

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('MARRSO: starting daily express slot generation');
            }
            $this->expressSlotGenerator->generate();
            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('MARRSO: daily express slot generation completed');
            }
        } catch (\Exception $e) {
            $this->logger->error('MARRSO express slot generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Hourly job: refresh 180-minute windows based on current time.
     */
    public function executeHourly(): void
    {
        try {
            if (!$this->configProvider->isEnabled() || !$this->configProvider->isExpress180Enabled()) {
                return;
            }

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('MARRSO: starting hourly express 180 slot refresh');
            }
            $this->expressSlotGenerator->refreshExpress180();
            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('MARRSO: hourly express 180 slot refresh completed');
            }
        } catch (\Exception $e) {
            $this->logger->error('MARRSO express 180 refresh failed: ' . $e->getMessage());
        }
    }
}
