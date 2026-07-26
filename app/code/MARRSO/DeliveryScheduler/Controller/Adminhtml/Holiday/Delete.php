<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\Holiday;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::holidays_delete';

    public function __construct(
        Context $context,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $resultRedirect->setPath('*/*/');
        }

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
