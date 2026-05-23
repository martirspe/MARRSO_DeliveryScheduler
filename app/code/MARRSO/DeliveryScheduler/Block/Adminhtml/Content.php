<?php

declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Framework\Api\SearchCriteriaBuilder;
use MARRSO\DeliveryScheduler\Api\DeliverySlotRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\HolidayRepositoryInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;

class Content extends Template
{
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    private PickupLocationRepositoryInterface $pickupLocationRepository;

    private DeliverySlotRepositoryInterface $deliverySlotRepository;

    private HolidayRepositoryInterface $holidayRepository;

    public function __construct(
        Template\Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        PickupLocationRepositoryInterface $pickupLocationRepository,
        DeliverySlotRepositoryInterface $deliverySlotRepository,
        HolidayRepositoryInterface $holidayRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->pickupLocationRepository = $pickupLocationRepository;
        $this->deliverySlotRepository = $deliverySlotRepository;
        $this->holidayRepository = $holidayRepository;
    }

    public function getPageType(): string
    {
        return (string)($this->getData('page_type') ?: '');
    }

    public function getTitle(): string
    {
        return (string)($this->getData('title') ?: __('Admin'));
    }

    public function getDescription(): string
    {
        return (string)($this->getData('description') ?: '');
    }

    public function getEmptyMessage(): string
    {
        return (string)($this->getData('empty_message') ?: __('No records found.'));
    }

    public function hasRows(): bool
    {
        return count($this->getRows()) > 0;
    }

    public function getHeaders(): array
    {
        return match ($this->getPageType()) {
            'pickup_location' => ['name', 'address', 'district', 'status', 'priority'],
            'delivery_slot' => ['date', 'time', 'district', 'capacity', 'status'],
            'holiday' => ['date', 'description'],
            default => ['value'],
        };
    }

    public function getRows(): array
    {
        return match ($this->getPageType()) {
            'pickup_location' => $this->getPickupLocationRows(),
            'delivery_slot' => $this->getDeliverySlotRows(),
            'holiday' => $this->getHolidayRows(),
            default => [],
        };
    }

    private function getPickupLocationRows(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setPageSize(100)
            ->setCurrentPage(1)
            ->create();

        $results = $this->pickupLocationRepository->getList($searchCriteria);
        $rows = [];

        foreach ($results->getItems() as $item) {
            $rows[] = [
                'name' => $item->getName(),
                'address' => $item->getAddress(),
                'district' => $item->getDistrict() ?: '-',
                'status' => $item->getIsActive() ? __('Active') : __('Inactive'),
                'priority' => (string)$item->getPriority(),
            ];
        }

        return $rows;
    }

    private function getDeliverySlotRows(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setPageSize(100)
            ->setCurrentPage(1)
            ->create();

        $results = $this->deliverySlotRepository->getList($searchCriteria);
        $rows = [];

        foreach ($results->getItems() as $item) {
            $rows[] = [
                'date' => $item->getSlotDate(),
                'time' => sprintf('%s - %s', $item->getStartTime(), $item->getEndTime()),
                'district' => $item->getDistrict(),
                'capacity' => sprintf('%d/%d', $item->getUsedCapacity(), $item->getCapacity()),
                'status' => $item->getIsActive() ? __('Active') : __('Inactive'),
            ];
        }

        return $rows;
    }

    private function getHolidayRows(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setPageSize(100)
            ->setCurrentPage(1)
            ->create();

        $results = $this->holidayRepository->getList($searchCriteria);
        $rows = [];

        foreach ($results->getItems() as $item) {
            $rows[] = [
                'date' => $item->getHolidayDate(),
                'description' => $item->getDescription() ?: __('No description'),
            ];
        }

        return $rows;
    }
}
