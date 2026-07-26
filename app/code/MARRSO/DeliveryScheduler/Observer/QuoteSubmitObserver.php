<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterfaceFactory;
use MARRSO\DeliveryScheduler\Api\OrderDeliveryScheduleRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;
use MARRSO\DeliveryScheduler\Model\Service\SlotCapacityManager;

/**
 * Quote Submit Observer
 *
 * Persists delivery schedule selection from quote to order
 */
class QuoteSubmitObserver implements ObserverInterface
{
    /**
     * @var OrderDeliveryScheduleInterfaceFactory
     */
    private $orderDeliveryScheduleFactory;

    /**
     * @var OrderDeliveryScheduleRepositoryInterface
     */
    private $orderDeliveryScheduleRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderDeliveryScheduleInterfaceFactory $orderDeliveryScheduleFactory,
        OrderDeliveryScheduleRepositoryInterface $orderDeliveryScheduleRepository,
        private readonly SlotCapacityManager $slotCapacityManager,
        private readonly ConfigProvider $configProvider,
        LoggerInterface $logger
    ) {
        $this->orderDeliveryScheduleFactory = $orderDeliveryScheduleFactory;
        $this->orderDeliveryScheduleRepository = $orderDeliveryScheduleRepository;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        try {
            if (!$this->configProvider->isEnabled()) {
                return;
            }

            $quote = $observer->getEvent()->getQuote();
            $order = $observer->getEvent()->getOrder();

            if (!$quote || !$order || $quote->getIsVirtual()) {
                return;
            }

            $shippingAddress = $quote->getShippingAddress();
            if (!$shippingAddress) {
                return;
            }

            $this->slotCapacityManager->reserveFromAddress($shippingAddress);

            $schedule = $this->resolveSchedule($quote, $shippingAddress);
            if (!$schedule) {
                return;
            }

            $schedule->setOrderId((int)$order->getId());
            $schedule->setQuoteId((int)$quote->getId());

            $this->orderDeliveryScheduleRepository->save($schedule);

            if ($this->configProvider->isLoggingEnabled()) {
                $this->logger->info(sprintf('Saved delivery schedule for order %d', $order->getId()));
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in quote submit observer: ' . $e->getMessage());
            throw $e;
        }
    }

    private function resolveSchedule($quote, $shippingAddress): ?OrderDeliveryScheduleInterface
    {
        $schedule = $this->orderDeliveryScheduleFactory->create();

        try {
            $schedule = $this->orderDeliveryScheduleRepository->getByQuoteId((int)$quote->getId());
        } catch (NoSuchEntityException $e) {
            $schedule = $this->orderDeliveryScheduleFactory->create();
        }

        $deliveryType = $this->getValue($shippingAddress, 'delivery_type');
        if (!$deliveryType && $schedule->getDeliveryType()) {
            $deliveryType = $schedule->getDeliveryType();
        }

        if (!$deliveryType) {
            return null;
        }

        $schedule->setDeliveryType((string)$deliveryType);
        $schedule->setPickupLocationId($this->getIntValue($shippingAddress, 'pickup_location_id', $schedule->getPickupLocationId()));
        $schedule->setDeliveryDate((string)$this->getValue($shippingAddress, 'delivery_date', $schedule->getDeliveryDate()));
        $schedule->setDeliverySlot((string)$this->getValue($shippingAddress, 'delivery_slot', $schedule->getDeliverySlot()));
        $schedule->setServiceLevel($this->getValue($shippingAddress, 'service_level', $schedule->getServiceLevel()));
        $schedule->setCustomerComment((string)$this->getValue($shippingAddress, 'delivery_instructions', $schedule->getCustomerComment()));

        return $schedule;
    }

    private function getValue($shippingAddress, string $field, ?string $fallback = null): ?string
    {
        $value = $shippingAddress->getData('marrso_' . $field);
        if ($value === null || $value === '') {
            $value = $shippingAddress->getData($field);
        }
        if ($value === null || $value === '') {
            $value = $shippingAddress->getData('custom_' . $field);
        }

        if ($value === null || $value === '') {
            $extensionAttributes = $shippingAddress->getExtensionAttributes();
            if ($extensionAttributes) {
                $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
                if (method_exists($extensionAttributes, $method)) {
                    $value = $extensionAttributes->{$method}();
                }
            }
        }

        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string)$value;
    }

    private function getIntValue($shippingAddress, string $field, ?int $fallback = null): ?int
    {
        $value = $this->getValue($shippingAddress, $field);
        if ($value === null) {
            return $fallback;
        }

        return (int)$value;
    }
}
