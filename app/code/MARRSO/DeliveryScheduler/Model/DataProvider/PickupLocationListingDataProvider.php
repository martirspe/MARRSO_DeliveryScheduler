<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupLocation\CollectionFactory;

class PickupLocationListingDataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
}
