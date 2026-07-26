<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\DeliverySlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::delivery_slots_update';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MARRSO_DeliveryScheduler::delivery_slots');
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Delivery Slot') : __('New Delivery Slot')
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resource = $id
            ? 'MARRSO_DeliveryScheduler::delivery_slots_update'
            : 'MARRSO_DeliveryScheduler::delivery_slots_create';

        return $this->_authorization->isAllowed($resource);
    }
}
