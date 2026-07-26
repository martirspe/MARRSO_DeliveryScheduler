<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class Express implements HttpGetActionInterface
{
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ConfigProvider $configProvider
    ) {
    }

    public function execute(): Page|Redirect
    {
        if (!$this->configProvider->isEnabled() || !$this->configProvider->isExpressCheckoutEnabled()) {
            return $this->redirectToCart();
        }

        $quote = $this->checkoutSession->getQuote();
        if (!$quote || !$quote->getId() || !$quote->getItemsCount()) {
            return $this->redirectToCart();
        }

        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->getConfig()->getTitle()->set(__('Express Checkout'));

        return $page;
    }

    private function redirectToCart(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $redirect->setPath('checkout/cart');
    }
}
