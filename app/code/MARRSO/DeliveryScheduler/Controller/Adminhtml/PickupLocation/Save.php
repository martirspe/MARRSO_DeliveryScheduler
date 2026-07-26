<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\PickupLocationFactory;

class Save extends Action
{
    private const DATA_PERSISTOR_KEY = 'marrso_pickup_location';

    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations';

    public function __construct(
        Context $context,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupLocationFactory $pickupLocationFactory,
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

        $model = $this->pickupLocationFactory->create();
        if ($id) {
            $model = $this->pickupLocationRepository->getById($id);
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
            $this->pickupLocationRepository->save($model);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Pickup location saved.'));
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
            ? 'MARRSO_DeliveryScheduler::pickup_locations_update'
            : 'MARRSO_DeliveryScheduler::pickup_locations_create';

        return $this->_authorization->isAllowed($resource);
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
            PickupLocationInterface::BRAND => $this->nullableString($data[PickupLocationInterface::BRAND] ?? null),
            PickupLocationInterface::LOCATION_REFERENCES => $this->nullableString(
                $data[PickupLocationInterface::LOCATION_REFERENCES] ?? null
            ),
            PickupLocationInterface::OPENING_HOURS => $this->nullableString($data[PickupLocationInterface::OPENING_HOURS] ?? null),
            PickupLocationInterface::RETENTION_DAYS => max(1, (int)($data[PickupLocationInterface::RETENTION_DAYS] ?? 5)),
            PickupLocationInterface::AUTO_GENERATE_SLOTS => !isset($data[PickupLocationInterface::AUTO_GENERATE_SLOTS])
                || !empty($data[PickupLocationInterface::AUTO_GENERATE_SLOTS]) ? 1 : 0,
            PickupLocationInterface::IS_ACTIVE => !empty($data[PickupLocationInterface::IS_ACTIVE]) ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): ?\Magento\Framework\Phrase
    {
        if ($data[PickupLocationInterface::NAME] === '' || $data[PickupLocationInterface::CODE] === '') {
            return __('Name and Code are required.');
        }

        if ($data[PickupLocationInterface::ADDRESS] === '') {
            return __('Address is required.');
        }

        return null;
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
