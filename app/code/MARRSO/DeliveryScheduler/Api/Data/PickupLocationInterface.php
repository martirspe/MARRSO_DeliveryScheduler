<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Pickup Location Data Interface
 *
 * @api
 */
interface PickupLocationInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const NAME = 'name';
    const CODE = 'code';
    const ADDRESS = 'address';
    const DISTRICT = 'district';
    const LATITUDE = 'latitude';
    const LONGITUDE = 'longitude';
    const PRIORITY = 'priority';
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
     * Get Name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set Name
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get Code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set Code
     *
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self;

    /**
     * Get Address
     *
     * @return string
     */
    public function getAddress(): string;

    /**
     * Set Address
     *
     * @param string $address
     * @return $this
     */
    public function setAddress(string $address): self;

    /**
     * Get District
     *
     * @return string|null
     */
    public function getDistrict(): ?string;

    /**
     * Set District
     *
     * @param string|null $district
     * @return $this
     */
    public function setDistrict(?string $district): self;

    /**
     * Get Latitude
     *
     * @return float|null
     */
    public function getLatitude(): ?float;

    /**
     * Set Latitude
     *
     * @param float|null $latitude
     * @return $this
     */
    public function setLatitude(?float $latitude): self;

    /**
     * Get Longitude
     *
     * @return float|null
     */
    public function getLongitude(): ?float;

    /**
     * Set Longitude
     *
     * @param float|null $longitude
     * @return $this
     */
    public function setLongitude(?float $longitude): self;

    /**
     * Get Priority
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Set Priority
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority(int $priority): self;

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
     * @return \MARRSO\DeliveryScheduler\Api\Data\PickupLocationExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\PickupLocationExtensionInterface|null $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\MARRSO\DeliveryScheduler\Api\Data\PickupLocationExtensionInterface $extensionAttributes = null): self;
}
