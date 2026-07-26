<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Configuration Provider
 */
class ConfigProvider
{
    const XML_PATH_GENERAL_ENABLE = 'marrso_delivery_scheduler/general/enable';
    const XML_PATH_GENERAL_ENABLE_PICKUP = 'marrso_delivery_scheduler/general/enable_pickup';
    const XML_PATH_GENERAL_ENABLE_DELIVERY = 'marrso_delivery_scheduler/general/enable_delivery';
    const XML_PATH_GENERAL_TIMEZONE = 'marrso_delivery_scheduler/general/default_timezone';
    const XML_PATH_GENERAL_ENABLE_EXPRESS_CHECKOUT = 'marrso_delivery_scheduler/general/enable_express_checkout';

    const XML_PATH_PICKUP_CUTOFF_HOUR = 'marrso_delivery_scheduler/pickup/cutoff_hour';
    const XML_PATH_PICKUP_PREPARATION_DAYS = 'marrso_delivery_scheduler/pickup/preparation_days';
    const XML_PATH_PICKUP_DEFAULT_CAPACITY = 'marrso_delivery_scheduler/pickup/default_capacity';
    const XML_PATH_PICKUP_DISABLE_WEEKENDS = 'marrso_delivery_scheduler/pickup/disable_weekends';
    const XML_PATH_PICKUP_ENABLE_MAP = 'marrso_delivery_scheduler/pickup/enable_map';
    const XML_PATH_PICKUP_MAP_DEFAULT_LAT = 'marrso_delivery_scheduler/pickup/map_default_lat';
    const XML_PATH_PICKUP_MAP_DEFAULT_LNG = 'marrso_delivery_scheduler/pickup/map_default_lng';
    const XML_PATH_PICKUP_MAP_DEFAULT_ZOOM = 'marrso_delivery_scheduler/pickup/map_default_zoom';

    const XML_PATH_DELIVERY_CUTOFF_HOUR = 'marrso_delivery_scheduler/delivery/cutoff_hour';
    const XML_PATH_DELIVERY_ENABLE_SAME_DAY = 'marrso_delivery_scheduler/delivery/enable_same_day';
    const XML_PATH_DELIVERY_ENABLE_EXPRESS_180 = 'marrso_delivery_scheduler/delivery/enable_express_180';
    const XML_PATH_DELIVERY_ENABLE_EXPRESS_24 = 'marrso_delivery_scheduler/delivery/enable_express_24';
    const XML_PATH_DELIVERY_SAME_DAY_CUTOFF = 'marrso_delivery_scheduler/delivery/same_day_cutoff';
    const XML_PATH_DELIVERY_DEFAULT_PRICE = 'marrso_delivery_scheduler/delivery/default_delivery_price';
    const XML_PATH_DELIVERY_EXPRESS_180_PRICE = 'marrso_delivery_scheduler/delivery/express_180_price';
    const XML_PATH_DELIVERY_EXPRESS_24_PRICE = 'marrso_delivery_scheduler/delivery/express_24_price';
    const XML_PATH_DELIVERY_DEFAULT_CAPACITY = 'marrso_delivery_scheduler/delivery/default_capacity';
    const XML_PATH_DELIVERY_DISABLE_WEEKENDS = 'marrso_delivery_scheduler/delivery/disable_weekends';

    const XML_PATH_SLOT_GENERATION_DAYS = 'marrso_delivery_scheduler/slot_generation/days_ahead';
    const XML_PATH_SLOT_GENERATION_INTERVAL = 'marrso_delivery_scheduler/slot_generation/slot_interval_minutes';
    const XML_PATH_SLOT_GENERATION_START_TIME = 'marrso_delivery_scheduler/slot_generation/start_time';
    const XML_PATH_SLOT_GENERATION_END_TIME = 'marrso_delivery_scheduler/slot_generation/end_time';

    const XML_PATH_ADVANCED_ENABLE_LOGGING = 'marrso_delivery_scheduler/advanced/enable_logging';
    const XML_PATH_ADVANCED_CACHE_LIFETIME = 'marrso_delivery_scheduler/advanced/cache_lifetime';
    const XML_PATH_ADVANCED_MAX_DISTANCE = 'marrso_delivery_scheduler/advanced/max_distance_km';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Get configuration value
     */
    public function getValue(string $path, ?int $storeId = null): mixed
    {
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Is module enabled
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_GENERAL_ENABLE, $storeId);
    }

    /**
     * Is pickup enabled
     */
    public function isPickupEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_GENERAL_ENABLE_PICKUP, $storeId);
    }

    /**
     * Is delivery enabled
     */
    public function isDeliveryEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_GENERAL_ENABLE_DELIVERY, $storeId);
    }

    public function isExpressCheckoutEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_GENERAL_ENABLE_EXPRESS_CHECKOUT, $storeId);
    }

    /**
     * Get default timezone
     */
    public function getDefaultTimezone(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_GENERAL_TIMEZONE, $storeId) ?: 'UTC';
    }

    /**
     * Get pickup cutoff hour
     */
    public function getPickupCutoffHour(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_PICKUP_CUTOFF_HOUR, $storeId) ?: 17;
    }

    /**
     * Get pickup preparation days
     */
    public function getPickupPreparationDays(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_PICKUP_PREPARATION_DAYS, $storeId) ?: 1;
    }

    /**
     * Get pickup default capacity
     */
    public function getPickupDefaultCapacity(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_PICKUP_DEFAULT_CAPACITY, $storeId) ?: 10;
    }

    /**
     * Is pickup disabled on weekends
     */
    public function isPickupDisabledOnWeekends(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_PICKUP_DISABLE_WEEKENDS, $storeId);
    }

    public function isPickupMapEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_PICKUP_ENABLE_MAP, $storeId);
    }

    public function getPickupMapDefaultLat(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_PICKUP_MAP_DEFAULT_LAT, $storeId) ?: -12.0464;
    }

    public function getPickupMapDefaultLng(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_PICKUP_MAP_DEFAULT_LNG, $storeId) ?: -77.0428;
    }

    public function getPickupMapDefaultZoom(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_PICKUP_MAP_DEFAULT_ZOOM, $storeId) ?: 12;
    }

    /**
     * Get delivery cutoff hour
     */
    public function getDeliveryCutoffHour(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_DELIVERY_CUTOFF_HOUR, $storeId) ?: 17;
    }

    /**
     * Is same-day delivery enabled
     */
    public function isSameDayDeliveryEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_DELIVERY_ENABLE_SAME_DAY, $storeId);
    }

    public function isExpress180Enabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_DELIVERY_ENABLE_EXPRESS_180, $storeId);
    }

    public function isExpress24Enabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_DELIVERY_ENABLE_EXPRESS_24, $storeId);
    }

    /**
     * Get same-day delivery cutoff hour
     */
    public function getSameDayDeliveryCutoffHour(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_DELIVERY_SAME_DAY_CUTOFF, $storeId) ?: 14;
    }

    /**
     * Get default delivery price
     */
    public function getDefaultDeliveryPrice(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_DELIVERY_DEFAULT_PRICE, $storeId) ?: 0;
    }

    public function getExpress180Price(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_DELIVERY_EXPRESS_180_PRICE, $storeId) ?: 19.90;
    }

    public function getExpress24Price(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_DELIVERY_EXPRESS_24_PRICE, $storeId) ?: 14.90;
    }

    /**
     * Get delivery default capacity
     */
    public function getDeliveryDefaultCapacity(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_DELIVERY_DEFAULT_CAPACITY, $storeId) ?: 20;
    }

    /**
     * Is delivery disabled on weekends
     */
    public function isDeliveryDisabledOnWeekends(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_DELIVERY_DISABLE_WEEKENDS, $storeId);
    }

    /**
     * Get slot generation days ahead
     */
    public function getSlotGenerationDaysAhead(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_SLOT_GENERATION_DAYS, $storeId) ?: 30;
    }

    /**
     * Get slot interval in minutes
     */
    public function getSlotIntervalMinutes(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_SLOT_GENERATION_INTERVAL, $storeId) ?: 120;
    }

    /**
     * Get daily start time
     */
    public function getSlotGenerationStartTime(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SLOT_GENERATION_START_TIME, $storeId) ?: '09:00';
    }

    /**
     * Get daily end time
     */
    public function getSlotGenerationEndTime(?int $storeId = null): string
    {
        return (string)$this->getValue(self::XML_PATH_SLOT_GENERATION_END_TIME, $storeId) ?: '18:00';
    }

    /**
     * Is debug logging enabled
     */
    public function isLoggingEnabled(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::XML_PATH_ADVANCED_ENABLE_LOGGING, $storeId);
    }

    /**
     * Get cache lifetime in seconds
     */
    public function getCacheLifetime(?int $storeId = null): int
    {
        return (int)$this->getValue(self::XML_PATH_ADVANCED_CACHE_LIFETIME, $storeId) ?: 900;
    }

    /**
     * Get max distance in km
     */
    public function getMaxDistanceKm(?int $storeId = null): float
    {
        return (float)$this->getValue(self::XML_PATH_ADVANCED_MAX_DISTANCE, $storeId) ?: 0;
    }
}
