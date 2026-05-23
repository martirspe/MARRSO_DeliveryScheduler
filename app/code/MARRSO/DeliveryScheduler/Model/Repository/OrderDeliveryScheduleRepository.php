<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Repository;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\OrderDeliveryScheduleRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\OrderDeliverySchedule;
use MARRSO\DeliveryScheduler\Model\OrderDeliveryScheduleFactory;
use MARRSO\DeliveryScheduler\Model\ResourceModel\OrderDeliverySchedule as OrderDeliveryScheduleResource;
use MARRSO\DeliveryScheduler\Model\ResourceModel\OrderDeliverySchedule\CollectionFactory;

/**
 * Order Delivery Schedule Repository
 */
class OrderDeliveryScheduleRepository implements OrderDeliveryScheduleRepositoryInterface
{
    /**
     * @var OrderDeliveryScheduleFactory
     */
    private $orderDeliveryScheduleFactory;

    /**
     * @var OrderDeliveryScheduleResource
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

    /**
     * @var array
     */
    private $instancesByOrderId = [];

    /**
     * @var array
     */
    private $instancesByQuoteId = [];

    public function __construct(
        OrderDeliveryScheduleFactory $orderDeliveryScheduleFactory,
        OrderDeliveryScheduleResource $resource,
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->orderDeliveryScheduleFactory = $orderDeliveryScheduleFactory;
        $this->resource = $resource;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function save(OrderDeliveryScheduleInterface $orderDeliverySchedule): OrderDeliveryScheduleInterface
    {
        try {
            /** @var OrderDeliverySchedule $orderDeliverySchedule */
            $this->resource->save($orderDeliverySchedule);
            $this->instances[$orderDeliverySchedule->getId()] = $orderDeliverySchedule;

            if ($orderDeliverySchedule->getOrderId()) {
                $this->instancesByOrderId[$orderDeliverySchedule->getOrderId()] = $orderDeliverySchedule;
            }

            if ($orderDeliverySchedule->getQuoteId()) {
                $this->instancesByQuoteId[$orderDeliverySchedule->getQuoteId()] = $orderDeliverySchedule;
            }
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Unable to save order delivery schedule: %1', $e->getMessage()));
        }
        return $orderDeliverySchedule;
    }

    public function getById(int $entityId): OrderDeliveryScheduleInterface
    {
        if (!isset($this->instances[$entityId])) {
            /** @var OrderDeliverySchedule $orderDeliverySchedule */
            $orderDeliverySchedule = $this->orderDeliveryScheduleFactory->create();
            $this->resource->load($orderDeliverySchedule, $entityId);
            if (!$orderDeliverySchedule->getId()) {
                throw new NoSuchEntityException(__('Order delivery schedule not found with id: %1', $entityId));
            }
            $this->instances[$entityId] = $orderDeliverySchedule;
        }
        return $this->instances[$entityId];
    }

    public function getByOrderId(int $orderId): OrderDeliveryScheduleInterface
    {
        if (!isset($this->instancesByOrderId[$orderId])) {
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('order_id', $orderId)
                ->setPageSize(1);

            if ($collection->getSize() === 0) {
                throw new NoSuchEntityException(__('Order delivery schedule not found for order id: %1', $orderId));
            }

            $orderDeliverySchedule = $collection->getFirstItem();
            $this->instancesByOrderId[$orderId] = $orderDeliverySchedule;
            $this->instances[$orderDeliverySchedule->getId()] = $orderDeliverySchedule;

            if ($orderDeliverySchedule->getQuoteId()) {
                $this->instancesByQuoteId[$orderDeliverySchedule->getQuoteId()] = $orderDeliverySchedule;
            }
        }
        return $this->instancesByOrderId[$orderId];
    }

    public function getByQuoteId(int $quoteId): OrderDeliveryScheduleInterface
    {
        if (!isset($this->instancesByQuoteId[$quoteId])) {
            $collection = $this->collectionFactory->create()
                ->addFieldToFilter('quote_id', $quoteId)
                ->setPageSize(1);

            if ($collection->getSize() === 0) {
                throw new NoSuchEntityException(__('Order delivery schedule not found for quote id: %1', $quoteId));
            }

            $orderDeliverySchedule = $collection->getFirstItem();
            $this->instancesByQuoteId[$quoteId] = $orderDeliverySchedule;
            $this->instances[$orderDeliverySchedule->getId()] = $orderDeliverySchedule;

            if ($orderDeliverySchedule->getOrderId()) {
                $this->instancesByOrderId[$orderDeliverySchedule->getOrderId()] = $orderDeliverySchedule;
            }
        }
        return $this->instancesByQuoteId[$quoteId];
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

    public function delete(OrderDeliveryScheduleInterface $orderDeliverySchedule): bool
    {
        try {
            $this->resource->delete($orderDeliverySchedule);
            unset($this->instances[$orderDeliverySchedule->getId()]);

            if ($orderDeliverySchedule->getOrderId()) {
                unset($this->instancesByOrderId[$orderDeliverySchedule->getOrderId()]);
            }

            if ($orderDeliverySchedule->getQuoteId()) {
                unset($this->instancesByQuoteId[$orderDeliverySchedule->getQuoteId()]);
            }
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Unable to delete order delivery schedule: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $entityId): bool
    {
        $orderDeliverySchedule = $this->getById($entityId);
        return $this->delete($orderDeliverySchedule);
    }
}
