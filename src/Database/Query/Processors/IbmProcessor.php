<?php

namespace DreamFactory\Core\Database\Query\Processors;

use Illuminate\Database\Query\Processors\Processor;

class IbmProcessor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumnListing($results)
    {
        $mapping = function ($r) {
            $r = (object) $r;

            return $r->column_name;
        };

        return array_map($mapping, $results);
    }
}
