<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotExtensionInterface as ExtensionAttributesInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use MARRSO\DeliveryScheduler\Api\Data\DeliverySlotInterface;

/**
 * DeliverySlot Model
 */
class DeliverySlot extends AbstractModel implements DeliverySlotInterface, IdentityInterface
{
    const CACHE_TAG = 'marrso_delivery_slot';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'marrso_delivery_slot';
    protected $_eventObject = 'delivery_slot';

    /**
     * @var \MARRSO\DeliveryScheduler\Api\Data\DeliverySlotExtensionInterface|null
     */
    protected $extensionAttributes;

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\ResourceModel\DeliverySlot');
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

    public function getDistrict(): string
    {
        return (string)$this->getData(self::DISTRICT);
    }

    public function setDistrict(string $district): self
    {
        return $this->setData(self::DISTRICT, $district);
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

    public function getPrice(): float
    {
        return (float)$this->getData(self::PRICE);
    }

    public function setPrice(float $price): self
    {
        return $this->setData(self::PRICE, $price);
    }

    public function getServiceLevel(): string
    {
        return \MARRSO\DeliveryScheduler\Model\ServiceLevel::normalize(
            $this->getData(self::SERVICE_LEVEL)
        );
    }

    public function setServiceLevel(string $serviceLevel): self
    {
        return $this->setData(
            self::SERVICE_LEVEL,
            \MARRSO\DeliveryScheduler\Model\ServiceLevel::normalize($serviceLevel)
        );
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

    public function getCarrierCode(): ?string
    {
        return $this->getData(self::CARRIER_CODE);
    }

    public function setCarrierCode(?string $carrierCode): self
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
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
