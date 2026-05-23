<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Cron;

use DateTime;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Cleanup Expired Slots Cron Job
 *
 * Removes or deactivates slots that are past the current date
 */
class CleanupExpiredSlots
{
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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        PickupSlotRepositoryInterface $pickupSlotRepository,
        DeliverySlotRepositoryInterface $deliverySlotRepository,
        ConfigProvider $configProvider,
        LoggerInterface $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->pickupSlotRepository = $pickupSlotRepository;
        $this->deliverySlotRepository = $deliverySlotRepository;
        $this->configProvider = $configProvider;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
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

            $this->logger->info('Starting cleanup of expired delivery scheduler slots');

            $today = new DateTime('today');
            $todayStr = $today->format('Y-m-d');

            // Clean up pickup slots
            $this->cleanupPickupSlots($todayStr);

            // Clean up delivery slots
            $this->cleanupDeliverySlots($todayStr);

            $this->logger->info('Completed cleanup of expired delivery scheduler slots');
        } catch (\Exception $e) {
            $this->logger->error('Error in slot cleanup cron: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup expired pickup slots
     */
    private function cleanupPickupSlots(string $todayStr): void
    {
        try {
            // Get all slots before today
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('slot_date', $todayStr, 'lt')
                ->create();

            $slots = $this->pickupSlotRepository->getList($searchCriteria)->getItems();

            $deleted = 0;
            foreach ($slots as $slot) {
                try {
                    $this->pickupSlotRepository->delete($slot);
                    $deleted++;
                } catch (\Exception $e) {
                    $this->logger->warning('Could not delete pickup slot ' . $slot->getId() . ': ' . $e->getMessage());
                }
            }

            if ($deleted > 0) {
                $this->logger->info(sprintf('Cleaned up %d expired pickup slots', $deleted));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up pickup slots: ' . $e->getMessage());
        }
    }

    /**
     * Cleanup expired delivery slots
     */
    private function cleanupDeliverySlots(string $todayStr): void
    {
        try {
            // Get all slots before today
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('slot_date', $todayStr, 'lt')
                ->create();

            $slots = $this->deliverySlotRepository->getList($searchCriteria)->getItems();

            $deleted = 0;
            foreach ($slots as $slot) {
                try {
                    $this->deliverySlotRepository->delete($slot);
                    $deleted++;
                } catch (\Exception $e) {
                    $this->logger->warning('Could not delete delivery slot ' . $slot->getId() . ': ' . $e->getMessage());
                }
            }

            if ($deleted > 0) {
                $this->logger->info(sprintf('Cleaned up %d expired delivery slots', $deleted));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error cleaning up delivery slots: ' . $e->getMessage());
        }
    }
}
