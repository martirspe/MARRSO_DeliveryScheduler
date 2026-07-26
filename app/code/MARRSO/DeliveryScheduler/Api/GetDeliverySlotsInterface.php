<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

/**
 * Get Delivery Slots Service Contract
 *
 * @api
 */
interface GetDeliverySlotsInterface
{
    /**
     * Get Available Delivery Slots
     *
     * @param string|null $district Delivery district
     * @param string|null $startDate Start date to fetch (Y-m-d format)
     * @param string|null $endDate End date to fetch (Y-m-d format)
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        ?string $district = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $serviceLevel = null
    ): array;
}
