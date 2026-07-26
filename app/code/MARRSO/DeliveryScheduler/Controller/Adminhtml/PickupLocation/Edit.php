<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations_update';

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
        $resultPage->setActiveMenu('MARRSO_DeliveryScheduler::pickup_locations');
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Pickup Location') : __('New Pickup Location')
        );

        return $resultPage;
    }

    protected function _isAllowed(): bool
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        $resource = $id
            ? 'MARRSO_DeliveryScheduler::pickup_locations_update'
            : 'MARRSO_DeliveryScheduler::pickup_locations_create';

        return $this->_authorization->isAllowed($resource);
    }
}
