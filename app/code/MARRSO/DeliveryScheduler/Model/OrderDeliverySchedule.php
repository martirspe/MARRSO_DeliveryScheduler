<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleInterface;

/**
 * OrderDeliverySchedule Model
 */
class OrderDeliverySchedule extends AbstractModel implements OrderDeliveryScheduleInterface, IdentityInterface
{
    const CACHE_TAG = 'marrso_order_delivery_schedule';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'marrso_order_delivery_schedule';
    protected $_eventObject = 'order_delivery_schedule';

    /**
     * @var \Magento\Framework\Api\ExtensionAttributesInterface|null
     */
    protected $extensionAttributes;

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\ResourceModel\OrderDeliverySchedule');
    }

    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) ? (int)$this->getData(self::ENTITY_ID) : null;
    }

    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    public function getExtensionAttributes()
    {
        return $this->extensionAttributes;
    }

    public function setExtensionAttributes(?ExtensionAttributesInterface $extensionAttributes = null): self
    {
        $this->extensionAttributes = $extensionAttributes;
        return $this;
    }

    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData(self::QUOTE_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getDeliveryType(): string
    {
        return (string)$this->getData(self::DELIVERY_TYPE);
    }

    public function setDeliveryType(string $deliveryType): self
    {
        return $this->setData(self::DELIVERY_TYPE, $deliveryType);
    }

    public function getPickupLocationId(): ?int
    {
        $value = $this->getData(self::PICKUP_LOCATION_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setPickupLocationId(?int $pickupLocationId): self
    {
        return $this->setData(self::PICKUP_LOCATION_ID, $pickupLocationId);
    }

    public function getDeliveryDate(): string
    {
        return (string)$this->getData(self::DELIVERY_DATE);
    }

    public function setDeliveryDate(string $deliveryDate): self
    {
        return $this->setData(self::DELIVERY_DATE, $deliveryDate);
    }

    public function getDeliverySlot(): ?string
    {
        return $this->getData(self::DELIVERY_SLOT);
    }

    public function setDeliverySlot(?string $deliverySlot): self
    {
        return $this->setData(self::DELIVERY_SLOT, $deliverySlot);
    }

    public function getCustomerComment(): ?string
    {
        return $this->getData(self::CUSTOMER_COMMENT);
    }

    public function setCustomerComment(?string $customerComment): self
    {
        return $this->setData(self::CUSTOMER_COMMENT, $customerComment);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function setCreatedAt(?string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * Check if this is a pickup order
     */
    public function isPickup(): bool
    {
        return $this->getDeliveryType() === self::DELIVERY_TYPE_PICKUP;
    }

    /**
     * Check if this is a delivery order
     */
    public function isDelivery(): bool
    {
        return $this->getDeliveryType() === self::DELIVERY_TYPE_DELIVERY;
    }
}
