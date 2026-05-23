<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;

/**
 * Pickup Location Repository Interface
 *
 * @api
 */
interface PickupLocationRepositoryInterface
{
    /**
     * Save Pickup Location
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface $pickupLocation
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(PickupLocationInterface $pickupLocation): PickupLocationInterface;

    /**
     * Get by ID
     *
     * @param int $entityId
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): PickupLocationInterface;

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
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface $pickupLocation
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(PickupLocationInterface $pickupLocation): bool;

    /**
     * Delete by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
