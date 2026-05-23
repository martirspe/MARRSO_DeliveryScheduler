<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;

class MassDelete extends Action
{
    const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations_delete';

    private PickupLocationRepositoryInterface $pickupLocationRepository;
    private FormKeyValidator $formKeyValidator;

    public function __construct(Context $context, PickupLocationRepositoryInterface $pickupLocationRepository, FormKeyValidator $formKeyValidator)
    {
        parent::__construct($context);
        $this->pickupLocationRepository = $pickupLocationRepository;
        $this->formKeyValidator = $formKeyValidator;
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
                $this->pickupLocationRepository->deleteById((int)$id);
            }
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', count($ids)));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
