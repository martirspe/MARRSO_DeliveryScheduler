<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Framework\App\ResourceConnection;

/**
 * Resolves delivery districts without loading entire slot collections into memory.
 */
class DistrictResolver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return list<string>
     */
    public function getActiveDistricts(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $districts = [];

        $pickupSelect = $connection->select()
            ->distinct(true)
            ->from($connection->getTableName('marrso_pickup_location'), ['district'])
            ->where('is_active = ?', 1)
            ->where('district IS NOT NULL')
            ->where('district != ?', '');

        foreach ($connection->fetchCol($pickupSelect) as $district) {
            $district = trim((string)$district);
            if ($district !== '') {
                $districts[$district] = true;
            }
        }

        $deliverySelect = $connection->select()
            ->distinct(true)
            ->from($connection->getTableName('marrso_delivery_slot'), ['district'])
            ->where('is_active = ?', 1)
            ->where('district IS NOT NULL')
            ->where('district != ?', '');

        foreach ($connection->fetchCol($deliverySelect) as $district) {
            $district = trim((string)$district);
            if ($district !== '') {
                $districts[$district] = true;
            }
        }

        return array_keys($districts);
    }
}
