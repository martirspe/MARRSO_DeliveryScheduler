<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\DeliverySlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;
use MARRSO\DeliveryScheduler\Model\ServiceLevel;

class Save extends Action
{
    private const DATA_PERSISTOR_KEY = 'marrso_delivery_slot';

    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::delivery_slots';

    public function __construct(
        Context $context,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly DeliverySlotFactory $deliverySlotFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly DataPersistorInterface $dataPersistor
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

        $post = $this->getRequest()->getPostValue() ?: [];
        if (!isset($post['data']) && $this->getRequest()->getParam('data')) {
            $post['data'] = $this->getRequest()->getParam('data');
        }
        if (!$post) {
            return $resultRedirect->setPath('*/*/');
        }

        $data = FormDataExtractor::extract($post);
        $id = (int)($data['entity_id'] ?? $this->getRequest()->getParam('entity_id'));

        $model = $this->deliverySlotFactory->create();
        if ($id) {
            $model = $this->deliverySlotRepository->getById($id);
        }

        $normalized = $this->normalizeData($data);
        $error = $this->validateData($normalized);
        if ($error !== null) {
            $this->messageManager->addErrorMessage($error);
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, array_merge($data, $normalized));
            return $resultRedirect->setPath('*/*/edit', $id ? ['entity_id' => $id] : []);
        }

        $model->addData($normalized);

        try {
            $this->deliverySlotRepository->save($model);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Delivery slot saved.'));
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, array_merge($data, $normalized));
            return $resultRedirect->setPath('*/*/edit', $id ? ['entity_id' => $id] : []);
        }
    }

    protected function _isAllowed(): bool
    {
        $id = (int)$this->getRequest()->getParam('entity_id');
        if (!$id) {
            $post = $this->getRequest()->getPostValue();
            if (is_array($post)) {
                $data = FormDataExtractor::extract($post);
                $id = (int)($data['entity_id'] ?? 0);
            }
        }

        $resource = $id
            ? 'MARRSO_DeliveryScheduler::delivery_slots_update'
            : 'MARRSO_DeliveryScheduler::delivery_slots_create';

        return $this->_authorization->isAllowed($resource);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            DeliverySlotInterface::DISTRICT => trim((string)($data[DeliverySlotInterface::DISTRICT] ?? '')),
            DeliverySlotInterface::SLOT_DATE => FormDataExtractor::normalizeDate($data[DeliverySlotInterface::SLOT_DATE] ?? ''),
            DeliverySlotInterface::START_TIME => FormDataExtractor::normalizeTime($data[DeliverySlotInterface::START_TIME] ?? ''),
            DeliverySlotInterface::END_TIME => FormDataExtractor::normalizeTime($data[DeliverySlotInterface::END_TIME] ?? ''),
            DeliverySlotInterface::PRICE => (float)($data[DeliverySlotInterface::PRICE] ?? 0),
            DeliverySlotInterface::SERVICE_LEVEL => ServiceLevel::normalize(
                (string)($data[DeliverySlotInterface::SERVICE_LEVEL] ?? ServiceLevel::SCHEDULED)
            ),
            DeliverySlotInterface::CAPACITY => (int)($data[DeliverySlotInterface::CAPACITY] ?? 20),
            DeliverySlotInterface::USED_CAPACITY => (int)($data[DeliverySlotInterface::USED_CAPACITY] ?? 0),
            DeliverySlotInterface::CARRIER_CODE => $this->nullableString($data[DeliverySlotInterface::CARRIER_CODE] ?? null),
            DeliverySlotInterface::IS_ACTIVE => !empty($data[DeliverySlotInterface::IS_ACTIVE]) ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): ?\Magento\Framework\Phrase
    {
        if ($data[DeliverySlotInterface::DISTRICT] === '') {
            return __('District is required.');
        }

        if ($data[DeliverySlotInterface::SLOT_DATE] === '') {
            return __('Date is required.');
        }

        if ($data[DeliverySlotInterface::START_TIME] === '' || $data[DeliverySlotInterface::END_TIME] === '') {
            return __('Start and end time are required.');
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
