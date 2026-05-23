<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use MARRSO\DeliveryScheduler\Api\GetDeliverySlotsInterface;

/**
 * Get Delivery Slots Service Implementation
 */
class GetDeliverySlots implements GetDeliverySlotsInterface
{
    /**
     * @var AvailabilityEngine
     */
    private $availabilityEngine;

    public function __construct(AvailabilityEngine $availabilityEngine)
    {
        $this->availabilityEngine = $availabilityEngine;
    }

    public function execute(
        string $district,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        return $this->availabilityEngine->getAvailableDeliverySlots($district, $startDate, $endDate);
    }
}
