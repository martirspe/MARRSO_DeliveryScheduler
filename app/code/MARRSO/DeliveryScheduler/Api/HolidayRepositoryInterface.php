<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use MARRSO\DeliveryScheduler\Api\Data\HolidayInterface;

/**
 * Holiday Repository Interface
 *
 * @api
 */
interface HolidayRepositoryInterface
{
    /**
     * Save Holiday
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\HolidayInterface $holiday
     * @return \MARRSO\DeliveryScheduler\Api\Data\HolidayInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(HolidayInterface $holiday): HolidayInterface;

    /**
     * Get by ID
     *
     * @param int $entityId
     * @return \MARRSO\DeliveryScheduler\Api\Data\HolidayInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $entityId): HolidayInterface;

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
     * @param \MARRSO\DeliveryScheduler\Api\Data\HolidayInterface $holiday
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(HolidayInterface $holiday): bool;

    /**
     * Delete by ID
     *
     * @param int $entityId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;
}
