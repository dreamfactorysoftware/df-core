<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\Service;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Queue\SerializesModels;

abstract class BaseServiceEvent implements ShouldDispatchAfterCommit
{
    use SerializesModels;

    public $service;

    /**
     * The service name before this change, captured at dispatch time.
     * The listener runs after commit (ShouldDispatchAfterCommit), by which
     * point the model's original attributes have been re-synced, so
     * getOriginal() can no longer surface a rename.
     *
     * @var string|null
     */
    public $originalName;

    /**
     * Create a new event instance.
     *
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
        $this->originalName = $service->getOriginal('name');
    }
}
