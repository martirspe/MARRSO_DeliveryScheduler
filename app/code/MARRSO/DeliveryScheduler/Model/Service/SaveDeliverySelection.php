<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\OrderDeliveryScheduleRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\SaveDeliverySelectionInterface;

/**
 * Save Delivery Selection Service Implementation
 */
class SaveDeliverySelection implements SaveDeliverySelectionInterface
{
    /**
     * @var OrderDeliveryScheduleRepositoryInterface
     */
    private $orderDeliveryScheduleRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderDeliveryScheduleRepositoryInterface $orderDeliveryScheduleRepository,
        LoggerInterface $logger
    ) {
        $this->orderDeliveryScheduleRepository = $orderDeliveryScheduleRepository;
        $this->logger = $logger;
    }

    public function execute(OrderDeliveryScheduleInterface $deliverySelection): bool
    {
        try {
            if (!$deliverySelection->getOrderId() && !$deliverySelection->getQuoteId()) {
                throw new LocalizedException(__('Either order_id or quote_id must be provided'));
            }

            if ($deliverySelection->getQuoteId()) {
                try {
                    $existing = $this->orderDeliveryScheduleRepository->getByQuoteId($deliverySelection->getQuoteId());
                    $existing->setDeliveryType($deliverySelection->getDeliveryType());
                    $existing->setPickupLocationId($deliverySelection->getPickupLocationId());
                    $existing->setDeliveryDate($deliverySelection->getDeliveryDate());
                    $existing->setDeliverySlot($deliverySelection->getDeliverySlot());
                    $existing->setCustomerComment($deliverySelection->getCustomerComment());

                    if ($deliverySelection->getOrderId()) {
                        $existing->setOrderId($deliverySelection->getOrderId());
                    }

                    $deliverySelection = $existing;
                } catch (NoSuchEntityException $e) {
                    // New quote-based selection, use the incoming payload.
                }
            }

            if (!$deliverySelection->getDeliveryType()) {
                throw new LocalizedException(__('Delivery type is required'));
            }

            if (!$deliverySelection->getDeliveryDate()) {
                throw new LocalizedException(__('Delivery date is required'));
            }

            $validTypes = [
                OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP,
                OrderDeliveryScheduleInterface::DELIVERY_TYPE_DELIVERY,
            ];

            if (!in_array($deliverySelection->getDeliveryType(), $validTypes, true)) {
                throw new LocalizedException(__('Invalid delivery type: %1', $deliverySelection->getDeliveryType()));
            }

            if ($deliverySelection->getDeliveryType() === OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP
                && !$deliverySelection->getPickupLocationId()) {
                throw new LocalizedException(__('Pickup location is required for pickup orders'));
            }

            $this->orderDeliveryScheduleRepository->save($deliverySelection);

            return true;
        } catch (CouldNotSaveException $e) {
            $this->logger->error('Could not save delivery selection: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error saving delivery selection: ' . $e->getMessage());
            throw new CouldNotSaveException(__('Unable to save delivery selection: %1', $e->getMessage()));
        }
    }
}
