<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use MARRSO\DeliveryScheduler\Model\Checkout\DeliverySchedulerConfigProvider;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class Express extends Template
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly ConfigProvider $configProvider,
        private readonly DeliverySchedulerConfigProvider $deliverySchedulerConfigProvider,
        private readonly QuoteIdMaskFactory $quoteIdMaskFactory,
        private readonly FormatInterface $localeFormat,
        private readonly Json $jsonSerializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->configProvider->isEnabled() && $this->configProvider->isExpressCheckoutEnabled();
    }

    public function getJsLayout(): string
    {
        return $this->jsonSerializer->serialize([
            'components' => [
                'marrso-express-checkout' => [
                    'component' => 'MARRSO_DeliveryScheduler/js/view/express-checkout',
                    'config' => [
                        'expressCheckout' => $this->getExpressConfig(),
                    ],
                    'children' => [
                        'marrso-delivery-scheduler' => [
                            'component' => 'MARRSO_DeliveryScheduler/js/view/delivery-scheduler',
                            'displayArea' => 'delivery-scheduler',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function getCheckoutConfigJson(): string
    {
        $config = array_merge(
            [
                'quoteData' => $this->getQuoteData(),
                'priceFormat' => $this->localeFormat->getPriceFormat(),
            ],
            $this->deliverySchedulerConfigProvider->getConfig()
        );

        return $this->jsonSerializer->serialize($config);
    }

    /**
     * @return array<string, mixed>
     */
    private function getExpressConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $isGuest = !$this->customerSession->isLoggedIn();
        $quoteId = (int)$quote->getId();

        return [
            'isGuest' => $isGuest,
            'quoteId' => $quoteId,
            'maskedQuoteId' => $isGuest ? $this->resolveMaskedQuoteId($quoteId) : null,
            'checkoutUrl' => $this->getUrl('checkout', ['_secure' => true]),
            'cartUrl' => $this->getUrl('checkout/cart', ['_secure' => true]),
            'items' => $this->getCartItems(),
            'totals' => $this->getTotals(),
            'defaultCountry' => 'PE',
            'savedAddress' => $this->getSavedAddress(),
            'customerEmail' => $this->getCustomerEmail(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getQuoteData(): array
    {
        $quote = $this->checkoutSession->getQuote();

        return [
            'entity_id' => $quote->getId(),
            'quote_id' => $quote->getId(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSavedAddress(): ?array
    {
        $address = $this->checkoutSession->getQuote()->getShippingAddress();
        if (!$address || !$address->getCity()) {
            return null;
        }

        return [
            'firstname' => (string)$address->getFirstname(),
            'lastname' => (string)$address->getLastname(),
            'street' => implode("\n", (array)$address->getStreet()),
            'city' => (string)$address->getCity(),
            'postcode' => (string)$address->getPostcode(),
            'telephone' => (string)$address->getTelephone(),
            'country_id' => (string)$address->getCountryId() ?: 'PE',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCartItems(): array
    {
        $items = [];
        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            if (!$item instanceof CartItemInterface) {
                continue;
            }

            $items[] = [
                'name' => $item->getName(),
                'qty' => (float)$item->getQty(),
                'row_total' => (float)$item->getRowTotal(),
            ];
        }

        return $items;
    }

    /**
     * @return array<string, float>
     */
    private function getTotals(): array
    {
        $quote = $this->checkoutSession->getQuote();

        return [
            'subtotal' => (float)$quote->getSubtotal(),
            'shipping' => (float)$quote->getShippingAddress()->getShippingAmount(),
            'grand_total' => (float)$quote->getGrandTotal(),
        ];
    }

    private function resolveMaskedQuoteId(int $quoteId): ?string
    {
        $mask = $this->quoteIdMaskFactory->create()->load($quoteId, 'quote_id');

        if ($mask->getMaskedId()) {
            return (string)$mask->getMaskedId();
        }

        $mask->setQuoteId($quoteId);
        $mask->save();

        return $mask->getMaskedId() ? (string)$mask->getMaskedId() : null;
    }

    private function getCustomerEmail(): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        return (string)$this->customerSession->getCustomer()->getEmail();
    }
}
