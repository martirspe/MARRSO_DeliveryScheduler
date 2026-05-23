<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Holiday Data Interface
 *
 * @api
 */
interface HolidayInterface extends ExtensibleDataInterface
{
    const ENTITY_ID = 'entity_id';
    const HOLIDAY_DATE = 'holiday_date';
    const DESCRIPTION = 'description';
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
     * Get Holiday Date
     *
     * @return string
     */
    public function getHolidayDate(): string;

    /**
     * Set Holiday Date
     *
     * @param string $holidayDate
     * @return $this
     */
    public function setHolidayDate(string $holidayDate): self;

    /**
     * Get Description
     *
     * @return string|null
     */
    public function getDescription(): ?string;

    /**
     * Set Description
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self;

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
     * @return \MARRSO\DeliveryScheduler\Api\Data\HolidayExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set extension attributes
     *
     * @param \MARRSO\DeliveryScheduler\Api\Data\HolidayExtensionInterface|null $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(\MARRSO\DeliveryScheduler\Api\Data\HolidayExtensionInterface $extensionAttributes = null): self;
}
