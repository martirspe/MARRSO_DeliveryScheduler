<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Plugin\Repository;

use Magento\Framework\App\CacheInterface;
use MARRSO\DeliveryScheduler\Model\Service\AvailabilityEngine;

/**
 * Clears availability cache when slot data changes.
 */
class InvalidateAvailabilityCachePlugin
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function afterSave($subject, $result)
    {
        $this->invalidate();

        return $result;
    }

    public function afterDelete($subject, $result)
    {
        $this->invalidate();

        return $result;
    }

    public function afterDeleteById($subject, $result)
    {
        $this->invalidate();

        return $result;
    }

    private function invalidate(): void
    {
        $this->cache->clean([AvailabilityEngine::CACHE_TAG]);
    }
}
