<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\ResourceModel\PickupSlot\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class Collection extends SearchResult
{
    protected function _initSelect(): void
    {
        parent::_initSelect();

        $this->getSelect()->joinLeft(
            ['location' => $this->getTable('marrso_pickup_location')],
            'main_table.pickup_location_id = location.entity_id',
            [
                'location_name' => 'location.name',
                'location_code' => 'location.code',
                'location_district' => 'location.district',
            ]
        );
    }
}
