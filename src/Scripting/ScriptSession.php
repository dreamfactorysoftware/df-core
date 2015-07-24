<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Library\Utility\ArrayUtils;

/**
 * V8Js scripting session object
 */
class ScriptSession
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type int
     */
    const SCRIPT_SESSION_TTL = 15;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @type \Cache
     */
    protected $store;
    /**
     * @type array|mixed
     */
    protected $data = [];

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string $id The session ID
     * @param \Cache $store
     */
    public function __construct($id, $store)
    {
        $this->id = $id;
        $this->store = $store;
        $this->data = $store->get(sha1($id), []);
    }

    /**
     * Destruction
     */
    public function __destruct()
    {
        $this->store->add(sha1($this->id), $this->data, static::SCRIPT_SESSION_TTL);
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param      $key
     * @param null $defaultValue
     *
     * @return mixed
     */
    public function get($key, $defaultValue = null)
    {
        return ArrayUtils::get($this->data, $key, $defaultValue);
    }
}
