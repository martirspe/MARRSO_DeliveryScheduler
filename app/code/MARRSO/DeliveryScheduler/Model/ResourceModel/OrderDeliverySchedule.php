<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * OrderDeliverySchedule Resource Model
 */
class OrderDeliverySchedule extends AbstractDb
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    protected function _construct(): void
    {
        $this->_init('marrso_order_delivery_schedule', 'entity_id');
    }
}
