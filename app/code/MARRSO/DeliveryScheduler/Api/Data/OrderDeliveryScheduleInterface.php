<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Order Delivery Schedule Data Interface
 *
 * @api
 */
interface OrderDeliveryScheduleInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const ORDER_ID = 'order_id';
    const QUOTE_ID = 'quote_id';
    const DELIVERY_TYPE = 'delivery_type';
    const PICKUP_LOCATION_ID = 'pickup_location_id';
    const DELIVERY_DATE = 'delivery_date';
    const DELIVERY_SLOT = 'delivery_slot';
    const SERVICE_LEVEL = 'service_level';
    const CUSTOMER_COMMENT = 'customer_comment';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    const DELIVERY_TYPE_PICKUP = 'pickup';
    const DELIVERY_TYPE_DELIVERY = 'delivery';

    /**
     * Get Entity ID
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Set Entity ID
     *
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId): self;

    /**
     * Get Order ID
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set Order ID
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): self;

    /**
     * Get Quote ID
     *
     * @return int|null
     */
    public function getQuoteId(): ?int;

    /**
     * Set Quote ID
     *
     * @param int|null $quoteId
     * @return $this
     */
    public function setQuoteId(?int $quoteId): self;

    /**
     * Get Delivery Type
     *
     * @return string
     */
    public function getDeliveryType(): string;

    /**
     * Set Delivery Type
     *
     * @param string $deliveryType
     * @return $this
     */
    public function setDeliveryType(string $deliveryType): self;

    /**
     * Get Pickup Location ID
     *
     * @return int|null
     */
    public function getPickupLocationId(): ?int;

    /**
     * Set Pickup Location ID
     *
     * @param int|null $pickupLocationId
     * @return $this
     */
    public function setPickupLocationId(?int $pickupLocationId): self;

    /**
     * Get Delivery Date
     *
     * @return string
     */
    public function getDeliveryDate(): string;

    /**
     * Set Delivery Date
     *
     * @param string $deliveryDate
     * @return $this
     */
    public function setDeliveryDate(string $deliveryDate): self;

    /**
     * Get Delivery Slot
     *
     * @return string|null
     */
    public function getDeliverySlot(): ?string;

    /**
     * Set Delivery Slot
     *
     * @param string|null $deliverySlot
     * @return $this
     */
    public function setDeliverySlot(?string $deliverySlot): self;

    /**
     * Get Service Level
     *
     * @return string|null
     */
    public function getServiceLevel(): ?string;

    /**
     * Set Service Level
     *
     * @param string|null $serviceLevel
     * @return $this
     */
    public function setServiceLevel(?string $serviceLevel): self;

    /**
     * Get Customer Comment
     *
     * @return string|null
     */
    public function getCustomerComment(): ?string;

    /**
     * Set Customer Comment
     *
     * @param string|null $customerComment
     * @return $this
     */
    public function setCustomerComment(?string $customerComment): self;

    /**
     * Get Created At
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set Created At
     *
     * @param string|null $createdAt
     * @return $this
     */
    public function setCreatedAt(?string $createdAt): self;

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set Updated At
     *
     * @param string|null $updatedAt
     * @return $this
     */
    public function setUpdatedAt(?string $updatedAt): self;

    /**
     * Get extension attributes
     *
     * @return \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleExtensionInterface|null $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\MARRSO\DeliveryScheduler\Api\Data\OrderDeliveryScheduleExtensionInterface $extensionAttributes = null): self;
}
