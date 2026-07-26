<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Service;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Ensures REST/checkout callers can only modify their own active quote.
 */
class QuoteAccessValidator
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly UserContextInterface $userContext,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    /**
     * @throws AuthorizationException
     * @throws NoSuchEntityException
     */
    public function getOwnedQuote(int $quoteId): CartInterface
    {
        $quote = $this->cartRepository->get($quoteId);

        if (!(int)$quote->getIsActive()) {
            throw new AuthorizationException(__('This cart is no longer active.'));
        }

        $userType = $this->userContext->getUserType();
        $userId = (int)$this->userContext->getUserId();

        if ($userType === UserContextInterface::USER_TYPE_CUSTOMER && $userId > 0) {
            if ((int)$quote->getCustomerId() !== $userId) {
                throw new AuthorizationException(__('You are not allowed to access this cart.'));
            }

            return $quote;
        }

        if ((int)$quote->getCustomerId() > 0) {
            throw new AuthorizationException(__('You are not allowed to access this cart.'));
        }

        if (!$this->checkoutSession->isSessionExists()) {
            $this->checkoutSession->start();
        }

        $sessionQuoteId = (int)$this->checkoutSession->getQuoteId();
        if ($sessionQuoteId === 0) {
            $this->checkoutSession->setQuoteId($quoteId);
        } elseif ($sessionQuoteId !== $quoteId) {
            throw new AuthorizationException(__('You are not allowed to access this cart.'));
        }

        return $quote;
    }
}
