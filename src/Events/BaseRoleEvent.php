<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\Role;
use Illuminate\Queue\SerializesModels;

abstract class BaseRoleEvent
{
    use SerializesModels;

    public $role;

    /**
     * Create a new event instance.
     *
     * @param Role $role
     */
    public function __construct(Role $role)
    {
        $this->role = $role;
    }
}
