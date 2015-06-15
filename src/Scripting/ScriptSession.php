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
    protected $_store;
    /**
     * @type array|mixed
     */
    protected $_data = [];

    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * @param string $id The session ID
     * @param \Cache $store
     */
    public function __construct($id, $store)
    {
        $this->_id = $id;
        $this->_store = $store;
        $this->_data = $store->get(sha1($id), []);
    }

    /**
     * Destruction
     */
    public function __destruct()
    {
        $this->_store->add(sha1($this->_id), $this->_data, static::SCRIPT_SESSION_TTL);
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $this->_data[$key] = $value;
    }

    /**
     * @param      $key
     * @param null $defaultValue
     *
     * @return mixed
     */
    public function get($key, $defaultValue = null)
    {
        return ArrayUtils::get($this->_data, $key, $defaultValue);
    }
}
