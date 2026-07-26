<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupSlot;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\PickupSlotFactory;

class Save extends Action
{
    private const DATA_PERSISTOR_KEY = 'marrso_pickup_slot';

    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_slots_update';

    public function __construct(
        Context $context,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotFactory $pickupSlotFactory,
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

        $model = $this->pickupSlotFactory->create();
        if ($id) {
            $model = $this->pickupSlotRepository->getById($id);
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
            $this->pickupSlotRepository->save($model);
            $this->disableAutoGenerationForLocation((int)$normalized[PickupSlotInterface::PICKUP_LOCATION_ID]);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Pickup slot saved.'));
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
            ? 'MARRSO_DeliveryScheduler::pickup_slots_update'
            : 'MARRSO_DeliveryScheduler::pickup_slots_create';

        return $this->_authorization->isAllowed($resource);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            PickupSlotInterface::PICKUP_LOCATION_ID => (int)($data[PickupSlotInterface::PICKUP_LOCATION_ID] ?? 0),
            PickupSlotInterface::SLOT_DATE => FormDataExtractor::normalizeDate($data[PickupSlotInterface::SLOT_DATE] ?? ''),
            PickupSlotInterface::START_TIME => FormDataExtractor::normalizeTime($data[PickupSlotInterface::START_TIME] ?? ''),
            PickupSlotInterface::END_TIME => FormDataExtractor::normalizeTime($data[PickupSlotInterface::END_TIME] ?? ''),
            PickupSlotInterface::CAPACITY => (int)($data[PickupSlotInterface::CAPACITY] ?? 10),
            PickupSlotInterface::USED_CAPACITY => (int)($data[PickupSlotInterface::USED_CAPACITY] ?? 0),
            PickupSlotInterface::IS_ACTIVE => !empty($data[PickupSlotInterface::IS_ACTIVE]) ? 1 : 0,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): ?\Magento\Framework\Phrase
    {
        if ($data[PickupSlotInterface::PICKUP_LOCATION_ID] <= 0) {
            return __('Pickup location is required.');
        }

        if ($data[PickupSlotInterface::SLOT_DATE] === '') {
            return __('Date is required.');
        }

        if ($data[PickupSlotInterface::START_TIME] === '' || $data[PickupSlotInterface::END_TIME] === '') {
            return __('Start and end time are required.');
        }

        if ($data[PickupSlotInterface::USED_CAPACITY] > $data[PickupSlotInterface::CAPACITY]) {
            return __('Used capacity cannot exceed total capacity.');
        }

        return null;
    }

    private function disableAutoGenerationForLocation(int $locationId): void
    {
        if ($locationId <= 0) {
            return;
        }

        try {
            $location = $this->pickupLocationRepository->getById($locationId);
            if ($location->getAutoGenerateSlots()) {
                $location->setAutoGenerateSlots(false);
                $this->pickupLocationRepository->save($location);
            }
        } catch (\Exception $e) {
            // Location lookup failure should not block slot save.
        }
    }
}
