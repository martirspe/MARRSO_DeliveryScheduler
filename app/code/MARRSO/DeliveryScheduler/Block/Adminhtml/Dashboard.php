<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupSlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Model\Config\ConfigProvider;

class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly ConfigProvider $configProvider,
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly PickupSlotRepositoryInterface $pickupSlotRepository,
        private readonly DeliverySlotRepositoryInterface $deliverySlotRepository,
        private readonly HolidayRepositoryInterface $holidayRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isModuleEnabled(): bool
    {
        return $this->configProvider->isEnabled();
    }

    public function isPickupEnabled(): bool
    {
        return $this->configProvider->isPickupEnabled();
    }

    public function isDeliveryEnabled(): bool
    {
        return $this->configProvider->isDeliveryEnabled();
    }

    public function isExpressCheckoutEnabled(): bool
    {
        return $this->configProvider->isExpressCheckoutEnabled();
    }

    public function getConfigUrl(?string $group = null): string
    {
        $params = ['section' => 'marrso_delivery_scheduler'];
        $url = $this->getUrl('adminhtml/system_config/edit', $params);

        if ($group) {
            $url .= '#marrso_delivery_scheduler_' . $group;
        }

        return $url;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getStatCards(): array
    {
        return [
            [
                'label' => __('Pickup Locations'),
                'value' => $this->getTotalCount($this->pickupLocationRepository),
                'url' => $this->getUrl('marrso_delivery_scheduler/pickuplocation/index'),
            ],
            [
                'label' => __('Pickup Slots'),
                'value' => $this->getTotalCount($this->pickupSlotRepository),
                'url' => $this->getUrl('marrso_delivery_scheduler/pickupslot/index'),
            ],
            [
                'label' => __('Delivery Slots'),
                'value' => $this->getTotalCount($this->deliverySlotRepository),
                'url' => $this->getUrl('marrso_delivery_scheduler/deliveryslot/index'),
            ],
            [
                'label' => __('Holidays'),
                'value' => $this->getTotalCount($this->holidayRepository),
                'url' => $this->getUrl('marrso_delivery_scheduler/holiday/index'),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getManageCards(): array
    {
        return [
            [
                'title' => __('Configuration'),
                'description' => __('All module settings: general, pickup, delivery, slots and advanced options.'),
                'url' => $this->getConfigUrl(),
                'button' => __('Open settings'),
                'icon' => 'settings',
            ],
            [
                'title' => __('Pickup Locations'),
                'description' => __('Manage Click & Collect points, addresses, map data and retention rules.'),
                'url' => $this->getUrl('marrso_delivery_scheduler/pickuplocation/index'),
                'button' => __('Manage locations'),
                'icon' => 'pickup',
            ],
            [
                'title' => __('Pickup Slots'),
                'description' => __('Define pickup time windows, capacity and availability per store.'),
                'url' => $this->getUrl('marrso_delivery_scheduler/pickupslot/index'),
                'button' => __('Manage pickup slots'),
                'icon' => 'slots',
            ],
            [
                'title' => __('Delivery Slots'),
                'description' => __('Scheduled, express 180 min and 24 h delivery by district.'),
                'url' => $this->getUrl('marrso_delivery_scheduler/deliveryslot/index'),
                'button' => __('Manage delivery slots'),
                'icon' => 'delivery',
            ],
            [
                'title' => __('Holidays'),
                'description' => __('Block pickup and delivery on specific dates.'),
                'url' => $this->getUrl('marrso_delivery_scheduler/holiday/index'),
                'button' => __('Manage holidays'),
                'icon' => 'holiday',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function getConfigShortcuts(): array
    {
        return [
            [
                'label' => __('General'),
                'url' => $this->getConfigUrl('general'),
            ],
            [
                'label' => __('Pickup'),
                'url' => $this->getConfigUrl('pickup'),
            ],
            [
                'label' => __('Delivery & Express'),
                'url' => $this->getConfigUrl('delivery'),
            ],
            [
                'label' => __('Slot Generation'),
                'url' => $this->getConfigUrl('slot_generation'),
            ],
            [
                'label' => __('Advanced'),
                'url' => $this->getConfigUrl('advanced'),
            ],
        ];
    }

    private function getTotalCount(object $repository): int
    {
        $criteria = $this->createSearchCriteriaBuilder()
            ->setPageSize(1)
            ->setCurrentPage(1)
            ->create();

        return (int)$repository->getList($criteria)->getTotalCount();
    }

    private function createSearchCriteriaBuilder(): SearchCriteriaBuilder
    {
        return clone $this->searchCriteriaBuilder;
    }
}
