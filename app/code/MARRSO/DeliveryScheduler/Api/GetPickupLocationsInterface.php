<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

/**
 * Get Pickup Locations Service Contract
 *
 * @api
 */
interface GetPickupLocationsInterface
{
    /**
     * Get Available Pickup Locations
     *
     * @param string|null $district District to filter by
     * @param float|null $customerLatitude Customer latitude for distance calculation
     * @param float|null $customerLongitude Customer longitude for distance calculation
     * @param string|null $date Date to check availability (Y-m-d format)
     * @return array
     */
    public function execute(
        ?string $district = null,
        ?float $customerLatitude = null,
        ?float $customerLongitude = null,
        ?string $date = null
    ): array;
}
