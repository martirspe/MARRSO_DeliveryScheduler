<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Data;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use MARRSO\DeliveryScheduler\Api\PickupLocationRepositoryInterface;

/**
 * Backfill Click&Collect metadata on Lima demo pickup locations.
 */
class UpdateLimaPickupMetadata implements DataPatchInterface
{
    public function __construct(
        private readonly PickupLocationRepositoryInterface $pickupLocationRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    public function apply(): void
    {
        foreach ($this->getMetadataByCode() as $code => $metadata) {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('code', $code)
                ->setPageSize(1)
                ->create();

            $items = $this->pickupLocationRepository->getList($criteria)->getItems();
            if (!$items) {
                continue;
            }

            $location = reset($items);
            $location->setBrand($metadata['brand']);
            $location->setLocationReferences($metadata['location_references']);
            $location->setOpeningHours($metadata['opening_hours']);
            $location->setRetentionDays($metadata['retention_days']);
            $location->setName($metadata['name']);

            $this->pickupLocationRepository->save($location);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getMetadataByCode(): array
    {
        return [
            'lima-miraflores' => [
                'name' => 'Click&Collect Miraflores',
                'brand' => 'Mallplaza',
                'location_references' => 'Nivel 2, frente a cajas centrales',
                'opening_hours' => 'Lun-Dom 11:00-19:00',
                'retention_days' => 7,
            ],
            'lima-sanisidro' => [
                'name' => 'Click&Collect San Isidro',
                'brand' => 'Falabella',
                'location_references' => 'Nivel 1, al costado de atención al cliente',
                'opening_hours' => 'Lun-Dom 09:30-20:30',
                'retention_days' => 5,
            ],
            'lima-surco' => [
                'name' => 'Click&Collect Santiago de Surco',
                'brand' => 'Open Plaza',
                'location_references' => 'Nivel 2, estacionamiento naranja',
                'opening_hours' => 'Lun-Dom 11:00-19:00',
                'retention_days' => 5,
            ],
        ];
    }

    public static function getDependencies(): array
    {
        return [SeedLimaDemoData::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
