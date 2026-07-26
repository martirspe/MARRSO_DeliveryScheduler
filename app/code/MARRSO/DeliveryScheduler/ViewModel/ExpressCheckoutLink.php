<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class ExpressCheckoutLink implements ArgumentInterface
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isVisible(): bool
    {
        return $this->configProvider->isEnabled()
            && $this->configProvider->isExpressCheckoutEnabled();
    }

    public function getUrl(): string
    {
        return $this->urlBuilder->getUrl('delivery/checkout/express', ['_secure' => true]);
    }
}
