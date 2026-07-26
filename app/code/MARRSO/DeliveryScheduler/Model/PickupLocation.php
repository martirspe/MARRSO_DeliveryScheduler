<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

use MARRSO\DeliveryScheduler\Api\Data\PickupLocationExtensionInterface as ExtensionAttributesInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use MARRSO\DeliveryScheduler\Api\Data\PickupLocationInterface;

/**
 * PickupLocation Model
 */
class PickupLocation extends AbstractModel implements PickupLocationInterface, IdentityInterface
{
    const CACHE_TAG = 'marrso_pickup_location';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'marrso_pickup_location';
    protected $_eventObject = 'pickup_location';

    /**
     * @var \MARRSO\DeliveryScheduler\Api\Data\PickupLocationExtensionInterface|null
     */
    protected $extensionAttributes;

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\ResourceModel\PickupLocation');
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

    public function getName(): string
    {
        return (string)$this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function getCode(): string
    {
        return (string)$this->getData(self::CODE);
    }

    public function setCode(string $code): self
    {
        return $this->setData(self::CODE, $code);
    }

    public function getAddress(): string
    {
        return (string)$this->getData(self::ADDRESS);
    }

    public function setAddress(string $address): self
    {
        return $this->setData(self::ADDRESS, $address);
    }

    public function getDistrict(): ?string
    {
        return $this->getData(self::DISTRICT);
    }

    public function setDistrict(?string $district): self
    {
        return $this->setData(self::DISTRICT, $district);
    }

    public function getLatitude(): ?float
    {
        $value = $this->getData(self::LATITUDE);
        return $value !== null ? (float)$value : null;
    }

    public function setLatitude(?float $latitude): self
    {
        return $this->setData(self::LATITUDE, $latitude);
    }

    public function getLongitude(): ?float
    {
        $value = $this->getData(self::LONGITUDE);
        return $value !== null ? (float)$value : null;
    }

    public function setLongitude(?float $longitude): self
    {
        return $this->setData(self::LONGITUDE, $longitude);
    }

    public function getPriority(): int
    {
        return (int)$this->getData(self::PRIORITY);
    }

    public function setPriority(int $priority): self
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getBrand(): ?string
    {
        $value = $this->getData(self::BRAND);
        return $value !== null && $value !== '' ? (string)$value : null;
    }

    public function setBrand(?string $brand): self
    {
        return $this->setData(self::BRAND, $brand);
    }

    public function getLocationReferences(): ?string
    {
        $value = $this->getData(self::LOCATION_REFERENCES);
        return $value !== null && $value !== '' ? (string)$value : null;
    }

    public function setLocationReferences(?string $references): self
    {
        return $this->setData(self::LOCATION_REFERENCES, $references);
    }

    public function getOpeningHours(): ?string
    {
        $value = $this->getData(self::OPENING_HOURS);
        return $value !== null && $value !== '' ? (string)$value : null;
    }

    public function setOpeningHours(?string $openingHours): self
    {
        return $this->setData(self::OPENING_HOURS, $openingHours);
    }

    public function getRetentionDays(): int
    {
        return (int)($this->getData(self::RETENTION_DAYS) ?: 5);
    }

    public function setRetentionDays(int $retentionDays): self
    {
        return $this->setData(self::RETENTION_DAYS, max(1, $retentionDays));
    }

    public function getAutoGenerateSlots(): bool
    {
        $value = $this->getData(self::AUTO_GENERATE_SLOTS);
        if ($value === null) {
            return true;
        }

        return (bool)$value;
    }

    public function setAutoGenerateSlots(bool $autoGenerateSlots): self
    {
        return $this->setData(self::AUTO_GENERATE_SLOTS, $autoGenerateSlots ? 1 : 0);
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
}
