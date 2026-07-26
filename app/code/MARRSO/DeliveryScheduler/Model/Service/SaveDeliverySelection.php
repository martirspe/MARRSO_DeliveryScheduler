<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\OrderDeliveryScheduleRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\SaveDeliverySelectionInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\Quote\Address\DeliveryAttributesPersistor;
use MARRSO\DeliveryScheduler\Model\Quote\ShippingPriceUpdater;

class SaveDeliverySelection implements SaveDeliverySelectionInterface
{
    public function __construct(
        private readonly OrderDeliveryScheduleRepositoryInterface $orderDeliveryScheduleRepository,
        private readonly DeliveryAttributesPersistor $deliveryAttributesPersistor,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly SelectionValidator $selectionValidator,
        private readonly QuoteAccessValidator $quoteAccessValidator,
        private readonly ConfigProvider $configProvider,
        private readonly ShippingPriceUpdater $shippingPriceUpdater,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(OrderDeliveryScheduleInterface $deliverySelection): bool
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                throw new LocalizedException(__('Delivery scheduler is disabled.'));
            }

            if (!$deliverySelection->getQuoteId()) {
                throw new LocalizedException(__('quote_id is required.'));
            }

            if ($deliverySelection->getOrderId()) {
                throw new AuthorizationException(__('Order-linked delivery selections cannot be modified from checkout.'));
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

            $quoteId = (int)$deliverySelection->getQuoteId();
            $this->quoteAccessValidator->getOwnedQuote($quoteId);

            $district = $this->resolveDistrict($deliverySelection);
            $resolved = $this->selectionValidator->validateAndResolve($deliverySelection, $district);

            $deliverySelection->setData('delivery_price', $resolved['price']);
            $deliverySelection->setData('carrier_code', $resolved['carrier_code']);

            try {
                $existing = $this->orderDeliveryScheduleRepository->getByQuoteId($quoteId);
                $existing->setDeliveryType($deliverySelection->getDeliveryType());
                $existing->setPickupLocationId($deliverySelection->getPickupLocationId());
                $existing->setDeliveryDate($deliverySelection->getDeliveryDate());
                $existing->setDeliverySlot($deliverySelection->getDeliverySlot());
                $existing->setServiceLevel($deliverySelection->getServiceLevel());
                $existing->setCustomerComment($deliverySelection->getCustomerComment());
                $existing->setData('delivery_price', $resolved['price']);
                $existing->setData('carrier_code', $resolved['carrier_code']);
                $deliverySelection = $existing;
            } catch (NoSuchEntityException $e) {
                // New quote-based selection.
            }

            $this->deliveryAttributesPersistor->save($quoteId, $deliverySelection);
            $this->shippingPriceUpdater->applyFromSelection($quoteId, $deliverySelection);
            $this->orderDeliveryScheduleRepository->save($deliverySelection);

            return true;
        } catch (AuthorizationException | CouldNotSaveException | LocalizedException $e) {
            $this->logger->error('Could not save delivery selection: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error saving delivery selection: ' . $e->getMessage());
            throw new CouldNotSaveException(__('Unable to save delivery selection: %1', $e->getMessage()));
        }
    }

    private function resolveDistrict(OrderDeliveryScheduleInterface $deliverySelection): ?string
    {
        try {
            $quote = $this->cartRepository->get((int)$deliverySelection->getQuoteId());
            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress) {
                return null;
            }

            $city = trim((string)$shippingAddress->getCity());
            return $city !== '' ? $city : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
