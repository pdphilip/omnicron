<?php

namespace PDPhilip\OmniCron\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use PDPhilip\OmniCron\Run\RunsLifecycle;

/**
 * The five-line swap: any Eloquent flavour becomes the run log by wearing
 * RunsLifecycle. A MongoDB app extends its Mongo model base the same way.
 */
class CustomRun extends Model
{
    use RunsLifecycle;
}
