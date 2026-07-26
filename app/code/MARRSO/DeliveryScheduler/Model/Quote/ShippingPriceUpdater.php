<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;

/**
 * Applies delivery slot shipping price to the active quote.
 */
class ShippingPriceUpdater
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function applyFromSelection(int $quoteId, OrderDeliveryScheduleInterface $selection): void
    {
        $quote = $this->cartRepository->get($quoteId);
        $shippingAddress = $quote->getShippingAddress();

        if (!$shippingAddress) {
            throw new LocalizedException(__('Quote shipping address is not available.'));
        }

        if ($selection->getDeliveryType() === OrderDeliveryScheduleInterface::DELIVERY_TYPE_PICKUP) {
            $shippingAddress->setShippingAmount(0);
            $shippingAddress->setBaseShippingAmount(0);
            $quote->setShippingAddress($shippingAddress);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            return;
        }

        $price = (float)$selection->getData('delivery_price');
        $carrierCode = trim((string)$selection->getData('carrier_code')) ?: 'flatrate';
        $methodCode = $carrierCode . '_' . $carrierCode;

        $shippingAddress->setShippingMethod($methodCode);
        $shippingAddress->setShippingDescription(__('Delivery slot'));
        $shippingAddress->setShippingAmount($price);
        $shippingAddress->setBaseShippingAmount($price);

        $quote->setShippingAddress($shippingAddress);
        $quote->collectTotals();
        $this->cartRepository->save($quote);
    }
}
