<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface;

/**
 * Delivery Slot Repository Interface
 *
 * @api
 */
interface DeliverySlotRepositoryInterface
{
    /**
     * Save Delivery Slot
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface $deliverySlot
     * @return \MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(DeliverySlotInterface $deliverySlot): DeliverySlotInterface;

    /**
     * Get by ID
     *
     * @param int $entityId
     * @return \MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): DeliverySlotInterface;

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
     * @param \MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface $deliverySlot
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(DeliverySlotInterface $deliverySlot): bool;

    /**
     * Delete by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
