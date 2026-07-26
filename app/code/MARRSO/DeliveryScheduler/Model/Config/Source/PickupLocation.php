<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class PickupLocation implements OptionSourceInterface
{
    public function __construct(
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select --')]];

        $criteria = $this->searchCriteriaBuilder->create();

        $items = $this->pickupLocationRepository->getList($criteria)->getItems();
        usort($items, static function ($a, $b): int {
            $priority = ($a->getPriority() ?? 0) <=> ($b->getPriority() ?? 0);
            if ($priority !== 0) {
                return $priority;
            }

            return strcmp((string)$a->getName(), (string)$b->getName());
        });

        foreach ($items as $location) {
            $label = $location->getName();
            if ($location->getDistrict()) {
                $label .= ' (' . $location->getDistrict() . ')';
            }
            if (!$location->getIsActive()) {
                $label .= ' [' . __('Inactive') . ']';
            }

            $options[] = [
                'value' => (string)$location->getEntityId(),
                'label' => $label,
            ];
        }

        return $options;
    }
}
