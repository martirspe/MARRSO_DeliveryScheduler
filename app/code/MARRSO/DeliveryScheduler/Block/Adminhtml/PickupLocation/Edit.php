<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Adminhtml\PickupLocation;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_controller = 'adminhtml_pickup_location';
        $this->_blockGroup = 'MARRSO_DeliveryScheduler';
        parent::_construct();
        $this->buttonList->update('save', 'label', __('Save Pickup Location'));
        $this->buttonList->update('delete', 'label', __('Delete Pickup Location'));
    }
}
