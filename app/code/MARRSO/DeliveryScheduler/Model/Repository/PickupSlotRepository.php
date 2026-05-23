<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\PickupSlot;
use MARRSO\DeliveryScheduler\Model\PickupSlotFactory;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupSlot as PickupSlotResource;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupSlot\CollectionFactory;

/**
 * Pickup Slot Repository
 */
class PickupSlotRepository implements PickupSlotRepositoryInterface
{
    /**
     * @var PickupSlotFactory
     */
    private $pickupSlotFactory;

    /**
     * @var PickupSlotResource
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
        PickupSlotFactory $pickupSlotFactory,
        PickupSlotResource $resource,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->pickupSlotFactory = $pickupSlotFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(PickupSlotInterface $pickupSlot): PickupSlotInterface
    {
        try {
            /** @var PickupSlot $pickupSlot */
            $this->resource->save($pickupSlot);
            $this->instances[$pickupSlot->getId()] = $pickupSlot;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Unable to save pickup slot: %1', $e->getMessage()));
        }
        return $pickupSlot;
    }

    public function getById(int $entityId): PickupSlotInterface
    {
        if (!isset($this->instances[$entityId])) {
            /** @var PickupSlot $pickupSlot */
            $pickupSlot = $this->pickupSlotFactory->create();
            $this->resource->load($pickupSlot, $entityId);
            if (!$pickupSlot->getId()) {
                throw new NoSuchEntityException(__('Pickup slot not found with id: %1', $entityId));
            }
            $this->instances[$entityId] = $pickupSlot;
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

    public function delete(PickupSlotInterface $pickupSlot): bool
    {
        try {
            $this->resource->delete($pickupSlot);
            unset($this->instances[$pickupSlot->getId()]);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Unable to delete pickup slot: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        $pickupSlot = $this->getById($entityId);
        return $this->delete($pickupSlot);
    }
}
