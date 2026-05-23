<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\PickupLocationFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations';

    public function __construct(
        Context $context,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupLocationFactory $pickupLocationFactory,
        private readonly FormKeyValidator $formKeyValidator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            return $resultRedirect->setPath('*/*/');
        }

        $post = $this->getRequest()->getPostValue();
        if (!$post) {
            return $resultRedirect->setPath('*/*/');
        }

        $data = FormDataExtractor::extract($post);
        $id = (int)($data['entity_id'] ?? $this->getRequest()->getParam('entity_id'));

        $model = $this->pickupLocationFactory->create();
        if ($id) {
            $model = $this->pickupLocationRepository->getById($id);
        }

        $normalized = $this->normalizeData($data);
        if ($normalized[PickupLocationInterface::NAME] === '' || $normalized[PickupLocationInterface::CODE] === '') {
            $this->messageManager->addErrorMessage(__('Name and Code are required.'));
            return $resultRedirect->setPath('*/*/edit', $id ? ['entity_id' => $id] : []);
        }

        $model->addData($normalized);

        try {
            $this->pickupLocationRepository->save($model);
            $this->messageManager->addSuccessMessage(__('Pickup location saved.'));
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/edit', $id ? ['entity_id' => $id] : []);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            PickupLocationInterface::NAME => trim((string)($data[PickupLocationInterface::NAME] ?? '')),
            PickupLocationInterface::CODE => trim((string)($data[PickupLocationInterface::CODE] ?? '')),
            PickupLocationInterface::ADDRESS => trim((string)($data[PickupLocationInterface::ADDRESS] ?? '')),
            PickupLocationInterface::DISTRICT => $this->nullableString($data[PickupLocationInterface::DISTRICT] ?? null),
            PickupLocationInterface::LATITUDE => $this->nullableFloat($data[PickupLocationInterface::LATITUDE] ?? null),
            PickupLocationInterface::LONGITUDE => $this->nullableFloat($data[PickupLocationInterface::LONGITUDE] ?? null),
            PickupLocationInterface::PRIORITY => (int)($data[PickupLocationInterface::PRIORITY] ?? 0),
            PickupLocationInterface::IS_ACTIVE => !empty($data[PickupLocationInterface::IS_ACTIVE]) ? 1 : 0,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float)$value;
    }
}
