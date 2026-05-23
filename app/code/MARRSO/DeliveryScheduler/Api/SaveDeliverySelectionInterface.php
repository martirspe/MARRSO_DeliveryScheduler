<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api;

/**
 * Save Delivery Selection Service Contract
 *
 * @api
 */
interface SaveDeliverySelectionInterface
{
    /**
     * Save Delivery Selection for Quote/Order
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface $deliverySelection
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(\MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface $deliverySelection): bool;
}
