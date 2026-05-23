<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday\CollectionFactory;

class HolidayDataProvider extends AbstractDataProvider
{
    protected array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly HolidayRepositoryInterface $repository,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $id = (int)$this->request->getParam($this->getRequestFieldName());

        if ($id) {
            try {
                $item = $this->repository->getById($id);
                $this->loadedData[$id] = $item->getData();
            } catch (\Exception $e) {
                $this->loadedData[$id] = [];
            }
        }

        $persisted = $this->dataPersistor->get('marrso_holiday');
        if (!empty($persisted)) {
            $persistedId = (int)($persisted['entity_id'] ?? $id);
            $this->loadedData[$persistedId ?: ''] = $persisted;
            $this->dataPersistor->clear('marrso_holiday');
        }

        if (empty($this->loadedData)) {
            $this->loadedData[''] = [];
        }

        return $this->loadedData;
    }
}
