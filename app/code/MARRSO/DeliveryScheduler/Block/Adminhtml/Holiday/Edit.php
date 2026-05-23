<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Adminhtml\Holiday;

class Edit extends \Magento\Backend\Block\Widget\Form\Container
{
    protected function _construct()
    {
        $this->_objectId = 'entity_id';
        $this->_controller = 'adminhtml_holiday';
        $this->_blockGroup = 'MARRSO_DeliveryScheduler';
        parent::_construct();
        $this->buttonList->update('save', 'label', __('Save Holiday'));
        $this->buttonList->update('delete', 'label', __('Delete Holiday'));
    }
}
