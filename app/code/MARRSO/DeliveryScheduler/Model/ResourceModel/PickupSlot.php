<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * PickupSlot Resource Model
 */
class PickupSlot extends AbstractDb
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    protected function _construct(): void
    {
        $this->_init('marrso_pickup_slot', 'entity_id');
    }
}
