<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\Holiday;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use MARRSO\DeliveryScheduler\Api\Data\HolidayInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Adminhtml\FormDataExtractor;
use MARRSO\DeliveryScheduler\Model\HolidayFactory;

class Save extends Action
{
    private const DATA_PERSISTOR_KEY = 'marrso_holiday';

    public const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::holidays';

    public function __construct(
        Context $context,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly HolidayFactory $holidayFactory,
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

        $model = $this->holidayFactory->create();
        if ($id) {
            $model = $this->holidayRepository->getById($id);
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
            $this->holidayRepository->save($model);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('Holiday saved.'));
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $message = str_contains($e->getMessage(), 'UNQ_HOLIDAY_DATE')
                ? (string)__('A holiday already exists for this date.')
                : $e->getMessage();
            $this->messageManager->addErrorMessage($message);
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
            ? 'MARRSO_DeliveryScheduler::holidays_update'
            : 'MARRSO_DeliveryScheduler::holidays_create';

        return $this->_authorization->isAllowed($resource);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        return [
            HolidayInterface::HOLIDAY_DATE => FormDataExtractor::normalizeDate($data[HolidayInterface::HOLIDAY_DATE] ?? ''),
            HolidayInterface::DESCRIPTION => $this->nullableString($data[HolidayInterface::DESCRIPTION] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateData(array $data): ?\Magento\Framework\Phrase
    {
        if ($data[HolidayInterface::HOLIDAY_DATE] === '') {
            return __('Date is required.');
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
