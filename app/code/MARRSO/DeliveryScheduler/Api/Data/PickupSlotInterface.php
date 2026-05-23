<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Pickup Slot Data Interface
 *
 * @api
 */
interface PickupSlotInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const PICKUP_LOCATION_ID = 'pickup_location_id';
    const SLOT_DATE = 'slot_date';
    const START_TIME = 'start_time';
    const END_TIME = 'end_time';
    const CAPACITY = 'capacity';
    const USED_CAPACITY = 'used_capacity';
    const IS_ACTIVE = 'is_active';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

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
     * Get Pickup Location ID
     *
     * @return int
     */
    public function getPickupLocationId(): int;

    /**
     * Set Pickup Location ID
     *
     * @param int $pickupLocationId
     * @return $this
     */
    public function setPickupLocationId(int $pickupLocationId): self;

    /**
     * Get Slot Date
     *
     * @return string
     */
    public function getSlotDate(): string;

    /**
     * Set Slot Date
     *
     * @param string $slotDate
     * @return $this
     */
    public function setSlotDate(string $slotDate): self;

    /**
     * Get Start Time
     *
     * @return string
     */
    public function getStartTime(): string;

    /**
     * Set Start Time
     *
     * @param string $startTime
     * @return $this
     */
    public function setStartTime(string $startTime): self;

    /**
     * Get End Time
     *
     * @return string
     */
    public function getEndTime(): string;

    /**
     * Set End Time
     *
     * @param string $endTime
     * @return $this
     */
    public function setEndTime(string $endTime): self;

    /**
     * Get Capacity
     *
     * @return int
     */
    public function getCapacity(): int;

    /**
     * Set Capacity
     *
     * @param int $capacity
     * @return $this
     */
    public function setCapacity(int $capacity): self;

    /**
     * Get Used Capacity
     *
     * @return int
     */
    public function getUsedCapacity(): int;

    /**
     * Set Used Capacity
     *
     * @param int $usedCapacity
     * @return $this
     */
    public function setUsedCapacity(int $usedCapacity): self;

    /**
     * Get Is Active
     *
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * Set Is Active
     *
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self;

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
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupSlotExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupSlotExtensionInterface|null $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\MARRSO\DeliveryScheduler\Api\Data\PickupSlotExtensionInterface $extensionAttributes = null): self;
}
