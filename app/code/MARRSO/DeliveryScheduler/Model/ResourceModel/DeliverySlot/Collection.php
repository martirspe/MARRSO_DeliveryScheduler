<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel\DeliverySlot;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * DeliverySlot Collection
 */
class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\DeliverySlot', 'MARRSO\DeliveryScheduler\Model\ResourceModel\DeliverySlot');
    }
}
