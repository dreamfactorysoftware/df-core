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
 *
 * Order: an implementation may declare `public function priority(): int`
 * (not part of this interface, so existing enrichers keep working). Lower runs
 * first; absent means 0; equal priorities keep their tag order. An enricher
 * that shapes tables and columns (e.g. hides columns) should run early (< 0)
 * so later enrichers only describe what is left.
 *
 * Enrichers run for GET {service}/_spec?model=true&enrich=true,
 * ?model=semantics, and in-process getDataModel(..., enrich: true); a plain
 * ?model=true is the unenriched 1.0.19 model.
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
