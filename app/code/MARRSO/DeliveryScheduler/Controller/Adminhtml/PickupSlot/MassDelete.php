<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupSlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;

class MassDelete extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_slots_delete';

    public function __construct(
        Context $context,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $ids = $this->getRequest()->getParam('selected') ?: [];
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            return $resultRedirect->setPath('*/*/');
        }

        if (!is_array($ids) || empty($ids)) {
            $this->messageManager->addErrorMessage(__('Please select item(s).'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            foreach ($ids as $id) {
                $this->pickupSlotRepository->deleteById((int)$id);
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', count($ids)));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
