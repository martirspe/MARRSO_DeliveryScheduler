<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface;

/**
 * Pickup Slot Repository Interface
 *
 * @api
 */
interface PickupSlotRepositoryInterface
{
    /**
     * Save Pickup Slot
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface $pickupSlot
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PickupSlotInterface $pickupSlot): PickupSlotInterface;

    /**
     * Get by ID
     *
     * @param int $entityId
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): PickupSlotInterface;

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
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface $pickupSlot
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(PickupSlotInterface $pickupSlot): bool;

    /**
     * Delete by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
