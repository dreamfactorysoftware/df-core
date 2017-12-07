<?php

namespace DreamFactory\Core\Jobs;

use DreamFactory\Core\Utility\ResourcesWrapper;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Enums\HttpStatusCodes;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Components\RestHandler;
use Log;
use ServiceManager;

class DBInsert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $service =null;
    protected $table = null;

    protected $records = [];

    public $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param string $service
     * @param string $table
     * @param array $records
     */
    public function __construct($service, $table, array $records)
    {
        $this->table = $table;
        $this->records = $records;
        $this->service = $service;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        /** @var RestHandler $service */
        $service = ServiceManager::getService($this->service);
        /** @var \DreamFactory\Core\Contracts\ServiceResponseInterface $rs */
        $rs = ServiceManager::handleRequest(
            $service->getName(),
            Verbs::POST, '_table/' . $this->table,
            [],
            [],
            ResourcesWrapper::wrapResources($this->records),
            null,
            false
        );
        if (in_array($rs->getStatusCode(), [HttpStatusCodes::HTTP_OK, HttpStatusCodes::HTTP_CREATED])) {
            //$data = [];
        } else {
            $content = $rs->getContent();
            Log::error('Failed to insert data into table: ' .
                (is_array($content) ? print_r($content, true) : $content));
            throw new InternalServerErrorException('Failed to insert data into table. See log for details.');
        }
    }
}
