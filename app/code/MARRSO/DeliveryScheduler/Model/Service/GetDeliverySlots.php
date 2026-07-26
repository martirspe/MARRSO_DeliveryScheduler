<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use MARRSO\DeliveryScheduler\Api\GetDeliverySlotsInterface;

class GetDeliverySlots implements GetDeliverySlotsInterface
{
    public function __construct(
        private readonly AvailabilityEngine $availabilityEngine
    ) {
    }

    public function execute(
        ?string $district = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $serviceLevel = null
    ): array {
        $district = trim((string)$district);
        if ($district === '') {
            return [];
        }

        return $this->availabilityEngine->getAvailableDeliverySlots(
            $district,
            $startDate,
            $endDate,
            $serviceLevel
        );
    }
}
