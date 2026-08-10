<?php

namespace PDPhilip\OmniCron\Run;

use PDPhilip\Elasticsearch\Eloquent\Model;
use PDPhilip\Elasticsearch\Schema\Blueprint;

/**
 * The run log on Elasticsearch. Requires pdphilip/elasticsearch.
 *
 *   // config/omnicron.php
 *   'model' => PDPhilip\OmniCron\Run\EsRun::class,
 *
 * MAP THE INDEX BEFORE THE FIRST RUN LANDS - Elasticsearch types a field on
 * first write and cannot change it in place afterwards. In a migration:
 *
 *   Schema::create('omnicron_runs', [EsRun::class, 'mappingDefinition']);
 */
class EsRun extends Model implements RunRow
{
    use RunsLifecycle;

    public static function mappingDefinition(Blueprint $index): void
    {
        $index->keyword('task');
        $index->keyword('state');
        $index->long('started_at');
        $index->long('finished_at');
        $index->integer('duration_ms');
        $index->keyword('host');
        $index->keyword('trigger');
        $index->boolean('manual');
        $index->text('error');
    }

    /**
     * A second save on the same instance can be silently dropped - the first
     * save does not refresh the document's sequence metadata. The close goes
     * through a targeted update instead, which cannot lose the write.
     */
    protected function persistClose(): void
    {
        static::query()->where('id', $this->getKey())->update([
            'state' => $this->state->value,
            'finished_at' => $this->finished_at,
            'duration_ms' => $this->duration_ms,
            'output' => $this->output,
            'error' => $this->error,
        ]);
    }
}
