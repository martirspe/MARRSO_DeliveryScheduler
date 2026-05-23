<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\Holiday;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::holidays_delete';

    public function __construct(
        Context $context,
        private readonly HolidayRepositoryInterface $holidayRepository
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
            $this->holidayRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Holiday deleted.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
