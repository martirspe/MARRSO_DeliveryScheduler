<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\DeliverySlot;
use MARRSO\DeliveryScheduler\Model\DeliverySlotFactory;
use MARRSO\DeliveryScheduler\Model\ResourceModel\DeliverySlot as DeliverySlotResource;
use MARRSO\DeliveryScheduler\Model\ResourceModel\DeliverySlot\CollectionFactory;

/**
 * Delivery Slot Repository
 */
class DeliverySlotRepository implements DeliverySlotRepositoryInterface
{
    /**
     * @var DeliverySlotFactory
     */
    private $deliverySlotFactory;

    /**
     * @var DeliverySlotResource
     */
    private $resource;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SearchResultsFactory
     */
    private $searchResultsFactory;

    /**
     * @var array
     */
    private $instances = [];

    public function __construct(
        DeliverySlotFactory $deliverySlotFactory,
        DeliverySlotResource $resource,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->deliverySlotFactory = $deliverySlotFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(DeliverySlotInterface $deliverySlot): DeliverySlotInterface
    {
        try {
            /** @var DeliverySlot $deliverySlot */
            $this->resource->save($deliverySlot);
            $this->instances[$deliverySlot->getId()] = $deliverySlot;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Unable to save delivery slot: %1', $e->getMessage()));
        }
        return $deliverySlot;
    }

    public function getById(int $entityId): DeliverySlotInterface
    {
        if (!isset($this->instances[$entityId])) {
            /** @var DeliverySlot $deliverySlot */
            $deliverySlot = $this->deliverySlotFactory->create();
            $this->resource->load($deliverySlot, $entityId);
            if (!$deliverySlot->getId()) {
                throw new NoSuchEntityException(__('Delivery slot not found with id: %1', $entityId));
            }
            $this->instances[$entityId] = $deliverySlot;
        }
        return $this->instances[$entityId];
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        
        // Add filters from search criteria
        foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
            foreach ($filterGroup->getFilters() as $filter) {
                $collection->addFieldToFilter($filter->getField(), [$filter->getConditionType() => $filter->getValue()]);
            }
        }
        
        // Add sorting
        $sortOrders = $searchCriteria->getSortOrders();
        if ($sortOrders) {
            foreach ($sortOrders as $sortOrder) {
                $direction = $sortOrder->getDirection() === 'ASC' ? 'ASC' : 'DESC';
                $collection->addOrder($sortOrder->getField(), $direction);
            }
        }
        
        // Add pagination
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->setCurPage($searchCriteria->getCurrentPage());
        
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function delete(DeliverySlotInterface $deliverySlot): bool
    {
        try {
            $this->resource->delete($deliverySlot);
            unset($this->instances[$deliverySlot->getId()]);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Unable to delete delivery slot: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        $deliverySlot = $this->getById($entityId);
        return $this->delete($deliverySlot);
    }
}
