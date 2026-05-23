<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Psr\Log\LoggerInterface;

/**
 * Distance Calculator Service
 *
 * Uses Haversine formula to calculate distance between two coordinates
 */
class DistanceCalculator
{
    const EARTH_RADIUS_KM = 6371;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1 Latitude 1
     * @param float $lon1 Longitude 1
     * @param float $lat2 Latitude 2
     * @param float $lon2 Longitude 2
     * @return float Distance in kilometers
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        try {
            // Convert degrees to radians
            $lat1Rad = deg2rad($lat1);
            $lon1Rad = deg2rad($lon1);
            $lat2Rad = deg2rad($lat2);
            $lon2Rad = deg2rad($lon2);

            // Haversine formula
            $dLat = $lat2Rad - $lat1Rad;
            $dLon = $lon2Rad - $lon1Rad;

            $a = sin($dLat / 2) * sin($dLat / 2) +
                 cos($lat1Rad) * cos($lat2Rad) *
                 sin($dLon / 2) * sin($dLon / 2);

            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = self::EARTH_RADIUS_KM * $c;

            return round($distance, 2);
        } catch (\Exception $e) {
            $this->logger->error('Distance calculation error: ' . $e->getMessage());
            return 0;
        }
    }
}
