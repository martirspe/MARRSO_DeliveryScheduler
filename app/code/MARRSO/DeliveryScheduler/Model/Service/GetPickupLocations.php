<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use MARRSO\DeliveryScheduler\Api\GetPickupLocationsInterface;

/**
 * Get Pickup Locations Service Implementation
 */
class GetPickupLocations implements GetPickupLocationsInterface
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
        ?string $district = null,
        ?float $customerLatitude = null,
        ?float $customerLongitude = null,
        ?string $date = null
    ): array {
        return $this->availabilityEngine->getAvailablePickupLocations(
            $district,
            $customerLatitude,
            $customerLongitude,
            $date
        );
    }
}
