<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Holiday Collection
 */
class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\Holiday', 'MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday');
    }
}
