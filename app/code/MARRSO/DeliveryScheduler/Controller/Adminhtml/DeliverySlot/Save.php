<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\DeliverySlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::delivery_slots';

    public function __construct(
        Context $context,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly DeliverySlotFactory $deliverySlotFactory,
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

        $model = $this->deliverySlotFactory->create();
        if ($id) {
            $model = $this->deliverySlotRepository->getById($id);
        }

        $model->addData($this->normalizeData($data));

        try {
            $this->deliverySlotRepository->save($model);
            $this->messageManager->addSuccessMessage(__('Delivery slot saved.'));
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
            DeliverySlotInterface::DISTRICT => trim((string)($data[DeliverySlotInterface::DISTRICT] ?? '')),
            DeliverySlotInterface::SLOT_DATE => trim((string)($data[DeliverySlotInterface::SLOT_DATE] ?? '')),
            DeliverySlotInterface::START_TIME => trim((string)($data[DeliverySlotInterface::START_TIME] ?? '')),
            DeliverySlotInterface::END_TIME => trim((string)($data[DeliverySlotInterface::END_TIME] ?? '')),
            DeliverySlotInterface::PRICE => (float)($data[DeliverySlotInterface::PRICE] ?? 0),
            DeliverySlotInterface::CAPACITY => (int)($data[DeliverySlotInterface::CAPACITY] ?? 20),
            DeliverySlotInterface::USED_CAPACITY => (int)($data[DeliverySlotInterface::USED_CAPACITY] ?? 0),
            DeliverySlotInterface::CARRIER_CODE => $this->nullableString($data[DeliverySlotInterface::CARRIER_CODE] ?? null),
            DeliverySlotInterface::IS_ACTIVE => !empty($data[DeliverySlotInterface::IS_ACTIVE]) ? 1 : 0,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
