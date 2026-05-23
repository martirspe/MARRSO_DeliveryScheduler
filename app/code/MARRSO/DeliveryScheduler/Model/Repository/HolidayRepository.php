<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MARRSO\DeliveryScheduler\Api\Data\HolidayInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Holiday;
use MARRSO\DeliveryScheduler\Model\HolidayFactory;
use MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday as HolidayResource;
use MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday\CollectionFactory;

/**
 * Holiday Repository
 */
class HolidayRepository implements HolidayRepositoryInterface
{
    /**
     * @var HolidayFactory
     */
    private $holidayFactory;

    /**
     * @var HolidayResource
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
        HolidayFactory $holidayFactory,
        HolidayResource $resource,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->holidayFactory = $holidayFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(HolidayInterface $holiday): HolidayInterface
    {
        try {
            /** @var Holiday $holiday */
            $this->resource->save($holiday);
            $this->instances[$holiday->getId()] = $holiday;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Unable to save holiday: %1', $e->getMessage()));
        }
        return $holiday;
    }

    public function getById(int $entityId): HolidayInterface
    {
        if (!isset($this->instances[$entityId])) {
            /** @var Holiday $holiday */
            $holiday = $this->holidayFactory->create();
            $this->resource->load($holiday, $entityId);
            if (!$holiday->getId()) {
                throw new NoSuchEntityException(__('Holiday not found with id: %1', $entityId));
            }
            $this->instances[$entityId] = $holiday;
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

    public function delete(HolidayInterface $holiday): bool
    {
        try {
            $this->resource->delete($holiday);
            unset($this->instances[$holiday->getId()]);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Unable to delete holiday: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        $holiday = $this->getById($entityId);
        return $this->delete($holiday);
    }
}
