<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

use MARRSO\DeliveryScheduler\Api\Data\PickupSlotExtensionInterface as ExtensionAttributesInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use MARRSO\DeliveryScheduler\Api\Data\PickupSlotInterface;

/**
 * PickupSlot Model
 */
class PickupSlot extends AbstractModel implements PickupSlotInterface, IdentityInterface
{
    const CACHE_TAG = 'marrso_pickup_slot';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'marrso_pickup_slot';
    protected $_eventObject = 'pickup_slot';

    /**
     * @var \MARRSO\DeliveryScheduler\Api\Data\PickupSlotExtensionInterface|null
     */
    protected $extensionAttributes;

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\ResourceModel\PickupSlot');
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

    public function getPickupLocationId(): int
    {
        return (int)$this->getData(self::PICKUP_LOCATION_ID);
    }

    public function setPickupLocationId(int $pickupLocationId): self
    {
        return $this->setData(self::PICKUP_LOCATION_ID, $pickupLocationId);
    }

    public function getSlotDate(): string
    {
        return (string)$this->getData(self::SLOT_DATE);
    }

    public function setSlotDate(string $slotDate): self
    {
        return $this->setData(self::SLOT_DATE, $slotDate);
    }

    public function getStartTime(): string
    {
        return (string)$this->getData(self::START_TIME);
    }

    public function setStartTime(string $startTime): self
    {
        return $this->setData(self::START_TIME, $startTime);
    }

    public function getEndTime(): string
    {
        return (string)$this->getData(self::END_TIME);
    }

    public function setEndTime(string $endTime): self
    {
        return $this->setData(self::END_TIME, $endTime);
    }

    public function getCapacity(): int
    {
        return (int)$this->getData(self::CAPACITY);
    }

    public function setCapacity(int $capacity): self
    {
        return $this->setData(self::CAPACITY, $capacity);
    }

    public function getUsedCapacity(): int
    {
        return (int)$this->getData(self::USED_CAPACITY);
    }

    public function setUsedCapacity(int $usedCapacity): self
    {
        return $this->setData(self::USED_CAPACITY, $usedCapacity);
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): self
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
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
     * Check if slot is available
     */
    public function isAvailable(): bool
    {
        return $this->getIsActive() && ($this->getCapacity() > $this->getUsedCapacity());
    }

    /**
     * Get available spots in slot
     */
    public function getAvailableSpots(): int
    {
        return max(0, $this->getCapacity() - $this->getUsedCapacity());
    }
}
