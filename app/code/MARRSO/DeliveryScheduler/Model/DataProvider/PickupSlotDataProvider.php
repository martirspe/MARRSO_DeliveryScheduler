<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupSlot\CollectionFactory;

class PickupSlotDataProvider extends AbstractDataProvider
{
    protected array $loadedData = [];

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly PickupSlotRepositoryInterface $repository,
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
                $this->loadedData[$id] = $this->prepareItemData($item->getData());
            } catch (\Exception $e) {
                $this->loadedData[$id] = [];
            }
        }

        $persisted = $this->dataPersistor->get('marrso_pickup_slot');
        if (!empty($persisted)) {
            $persistedId = (int)($persisted['entity_id'] ?? $id);
            $this->loadedData[$persistedId ?: ''] = $this->prepareItemData($persisted);
            $this->dataPersistor->clear('marrso_pickup_slot');
        }

        if (empty($this->loadedData)) {
            $this->loadedData[''] = [
                'is_active' => 1,
                'capacity' => 10,
                'used_capacity' => 0,
            ];
        }

        return $this->loadedData;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareItemData(array $data): array
    {
        if (isset($data['is_active'])) {
            $data['is_active'] = (int)$data['is_active'];
        }

        return $data;
    }
}
