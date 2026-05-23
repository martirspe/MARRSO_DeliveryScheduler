<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

use MARRSO\DeliveryScheduler\Api\Data\HolidayExtensionInterface as ExtensionAttributesInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use MARRSO\DeliveryScheduler\Api\Data\HolidayInterface;

/**
 * Holiday Model
 */
class Holiday extends AbstractModel implements HolidayInterface, IdentityInterface
{
    const CACHE_TAG = 'marrso_delivery_holiday';

    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'marrso_delivery_holiday';
    protected $_eventObject = 'holiday';

    /**
     * @var \MARRSO\DeliveryScheduler\Api\Data\HolidayExtensionInterface|null
     */
    protected $extensionAttributes;

    protected function _construct(): void
    {
        $this->_init('MARRSO\DeliveryScheduler\Model\ResourceModel\Holiday');
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

    public function getHolidayDate(): string
    {
        return (string)$this->getData(self::HOLIDAY_DATE);
    }

    public function setHolidayDate(string $holidayDate): self
    {
        return $this->setData(self::HOLIDAY_DATE, $holidayDate);
    }

    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    public function setDescription(?string $description): self
    {
        return $this->setData(self::DESCRIPTION, $description);
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
