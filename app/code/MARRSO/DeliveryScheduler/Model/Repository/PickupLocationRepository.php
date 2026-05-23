<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\PickupLocation;
use MARRSO\DeliveryScheduler\Model\PickupLocationFactory;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupLocation as PickupLocationResource;
use MARRSO\DeliveryScheduler\Model\ResourceModel\PickupLocation\CollectionFactory;

/**
 * Pickup Location Repository
 */
class PickupLocationRepository implements PickupLocationRepositoryInterface
{
    /**
     * @var PickupLocationFactory
     */
    private $pickupLocationFactory;

    /**
     * @var PickupLocationResource
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
        PickupLocationFactory $pickupLocationFactory,
        PickupLocationResource $resource,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->pickupLocationFactory = $pickupLocationFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(PickupLocationInterface $pickupLocation): PickupLocationInterface
    {
        try {
            /** @var PickupLocation $pickupLocation */
            $this->resource->save($pickupLocation);
            $this->instances[$pickupLocation->getId()] = $pickupLocation;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Unable to save pickup location: %1', $e->getMessage()));
        }
        return $pickupLocation;
    }

    public function getById(int $entityId): PickupLocationInterface
    {
        if (!isset($this->instances[$entityId])) {
            /** @var PickupLocation $pickupLocation */
            $pickupLocation = $this->pickupLocationFactory->create();
            $this->resource->load($pickupLocation, $entityId);
            if (!$pickupLocation->getId()) {
                throw new NoSuchEntityException(__('Pickup location not found with id: %1', $entityId));
            }
            $this->instances[$entityId] = $pickupLocation;
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

    public function delete(PickupLocationInterface $pickupLocation): bool
    {
        try {
            $this->resource->delete($pickupLocation);
            unset($this->instances[$pickupLocation->getId()]);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Unable to delete pickup location: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        $pickupLocation = $this->getById($entityId);
        return $this->delete($pickupLocation);
    }
}
