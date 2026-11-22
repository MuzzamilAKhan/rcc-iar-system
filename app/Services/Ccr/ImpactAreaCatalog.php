<?php

namespace App\Services\Ccr;

/** Fixed starter catalog; later releases can make this company-configurable. */
final class ImpactAreaCatalog
{
    public const AREAS = [
        'design' => 'Design & Performance',
        'materials' => 'Materials & Biological Safety',
        'manufacturing' => 'Manufacturing',
        'sterilisation' => 'Sterilisation',
        'packaging' => 'Packaging',
        'shelf_life' => 'Shelf Life / Stability',
        'clinical' => 'Clinical',
        'risk' => 'Risk Management',
        'software' => 'Software',
        'labelling' => 'Labelling & Artwork',
        'master_data' => 'Databases / Master Data',
        'regulatory' => 'Regulatory',
        'supply_chain' => 'Supply Chain',
        'commercial' => 'Commercial',
        'quality' => 'Quality Assurance',
        'supplier_quality' => 'Supplier Quality / Supply Chain Quality',
    ];

    private const RELATED = [
        'design' => ['materials', 'manufacturing', 'sterilisation', 'packaging', 'shelf_life', 'clinical', 'risk', 'software', 'labelling', 'regulatory', 'supplier_quality'],
        'materials' => ['design', 'manufacturing', 'sterilisation', 'packaging', 'shelf_life', 'clinical', 'risk', 'labelling', 'regulatory', 'supplier_quality'],
        'manufacturing' => ['design', 'materials', 'sterilisation', 'packaging', 'shelf_life', 'risk', 'regulatory', 'supply_chain', 'quality', 'supplier_quality'],
        'sterilisation' => ['design', 'materials', 'manufacturing', 'packaging', 'shelf_life', 'risk', 'labelling', 'regulatory', 'supply_chain', 'quality', 'supplier_quality'],
        'packaging' => ['design', 'materials', 'manufacturing', 'sterilisation', 'shelf_life', 'risk', 'labelling', 'regulatory', 'supply_chain', 'quality', 'supplier_quality'],
        'shelf_life' => ['design', 'materials', 'sterilisation', 'packaging', 'clinical', 'risk', 'labelling', 'regulatory', 'supply_chain'],
        'clinical' => ['design', 'materials', 'risk', 'software', 'labelling', 'regulatory', 'quality'],
        'risk' => ['design', 'materials', 'manufacturing', 'sterilisation', 'packaging', 'shelf_life', 'clinical', 'software', 'labelling', 'regulatory', 'quality', 'supplier_quality'],
        'software' => ['design', 'clinical', 'risk', 'labelling', 'master_data', 'regulatory', 'quality'],
        'labelling' => ['design', 'packaging', 'shelf_life', 'clinical', 'risk', 'software', 'master_data', 'regulatory', 'supply_chain', 'commercial', 'quality'],
        'master_data' => ['design', 'manufacturing', 'labelling', 'regulatory', 'supply_chain', 'quality'],
        'regulatory' => ['design', 'clinical', 'risk', 'labelling', 'master_data', 'quality'],
        'supply_chain' => ['manufacturing', 'packaging', 'labelling', 'regulatory', 'commercial', 'quality', 'supplier_quality'],
        'commercial' => ['labelling', 'regulatory', 'supply_chain'],
        'quality' => ['manufacturing', 'sterilisation', 'risk', 'regulatory', 'supplier_quality'],
        'supplier_quality' => ['materials', 'manufacturing', 'sterilisation', 'risk', 'regulatory', 'supply_chain', 'quality'],
    ];

    /** A hint, never an AI conclusion or automatically selected impact area. */
    public function suggested(string $changeType): array
    {
        $primary = match ($changeType) {
            'design' => 'design',
            'materials' => 'materials',
            'manufacturing' => 'manufacturing',
            'software' => 'software',
            'labelling' => 'labelling',
            'supplier' => 'supplier_quality',
            default => null,
        };
        if ($primary === null) return [];
        return array_values(array_unique(array_merge([$primary], self::RELATED[$primary] ?? [])));
    }

    public function mustExplainExclusion(string $code): bool
    {
        return in_array($code, ['regulatory', 'risk', 'sterilisation'], true);
    }
}
