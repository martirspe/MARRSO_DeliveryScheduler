<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Generate Slots Observer
 */
class GenerateSlotsObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        // Triggered by scheduled cron event
    }
}
