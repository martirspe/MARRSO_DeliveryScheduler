<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\DeliverySlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::delivery_slots_delete';

    public function __construct(
        Context $context,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('entity_id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('We can\'t find a record to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->deliverySlotRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Delivery slot deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
