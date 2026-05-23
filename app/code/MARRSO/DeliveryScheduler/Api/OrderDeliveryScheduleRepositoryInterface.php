<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;

/**
 * Order Delivery Schedule Repository Interface
 *
 * @api
 */
interface OrderDeliveryScheduleRepositoryInterface
{
    /**
     * Save Order Delivery Schedule
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface $orderDeliverySchedule
     * @return \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(OrderDeliveryScheduleInterface $orderDeliverySchedule): OrderDeliveryScheduleInterface;

    /**
     * Get by ID
     *
     * @param int $entityId
     * @return \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): OrderDeliveryScheduleInterface;

    /**
     * Get by Order ID
     *
     * @param int $orderId
     * @return \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByOrderId(int $orderId): OrderDeliveryScheduleInterface;

    /**
     * Get by Quote ID
     *
     * @param int $quoteId
     * @return \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): OrderDeliveryScheduleInterface;

    /**
     * Get List
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface $orderDeliverySchedule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(OrderDeliveryScheduleInterface $orderDeliverySchedule): bool;

    /**
     * Delete by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
