<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\User;
use Illuminate\Queue\SerializesModels;

abstract class BaseUserEvent
{
    use SerializesModels;

    public $user;

    /**
     * Create a new event instance.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }
}

