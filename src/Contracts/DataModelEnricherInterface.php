<?php

namespace DreamFactory\Core\Contracts;

use DreamFactory\Core\Services\BaseRestService;

/**
 * Extension point for the condensed data model (GET {service}/_spec?model=true,
 * which MCP get_data_model serves to AI agents).
 *
 * Packages tag an implementation with self::TAG in their service provider:
 *     $this->app->tag([MyEnricher::class], DataModelEnricherInterface::TAG);
 *
 * The model reaching an enricher is already scoped to the caller: it lists
 * only tables the caller can GET, and row-filtered tables carry schema only.
 * Enrichers must keep to that scope (e.g. only describe tables present in
 * $model['tables']) and must not add row data. An enricher that throws is
 * skipped; the model is served without it.
 */
interface DataModelEnricherInterface
{
    public const TAG = 'df.data_model_enrichers';

    /**
     * @param array<string, mixed> $model the model built so far
     * @return array<string, mixed> the model, with additions
     */
    public function enrich(array $model, BaseRestService $service): array;
}
