<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Cron;

use DateTime;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use Psr\Log\LoggerInterface;

/**
 * Cleanup Expired Slots Cron Job
 *
 * Removes slots that are past the current date using bulk SQL deletes.
 */
class CleanupExpiredSlots
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function execute(): void
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                return;
            }

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info('Starting cleanup of expired delivery scheduler slots');
            }

            $todayStr = (new DateTime('today', new DateTimeZone($this->configProvider->getDefaultTimezone())))->format('Y-m-d');
            $connection = $this->resourceConnection->getConnection();

            $pickupDeleted = $connection->delete(
                $connection->getTableName('marrso_pickup_slot'),
                ['slot_date < ?' => $todayStr]
            );

            $deliveryDeleted = $connection->delete(
                $connection->getTableName('marrso_delivery_slot'),
                ['slot_date < ?' => $todayStr]
            );

            if ($this->configProvider->isLoggingEnabled() && ($pickupDeleted > 0 || $deliveryDeleted > 0)) {
                $this->logger->info(sprintf(
                    'Cleaned up %d pickup and %d delivery expired slots',
                    $pickupDeleted,
                    $deliveryDeleted
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in slot cleanup cron: ' . $e->getMessage());
        }
    }
}
