<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations_delete';

    public function __construct(
        Context $context,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository
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
            $this->pickupLocationRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Pickup location deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
