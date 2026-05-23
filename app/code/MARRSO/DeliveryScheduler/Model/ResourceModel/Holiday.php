<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Holiday Resource Model
 */
class Holiday extends AbstractDb
{
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    protected function _construct(): void
    {
        $this->_init('marrso_delivery_holiday', 'entity_id');
    }
}
