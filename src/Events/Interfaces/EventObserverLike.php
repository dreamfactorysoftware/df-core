<?php
/**
 * EventObserver.php
 *
 * @copyright Copyright (c) 2014 DreamFactory Software, Inc.
 * @link      DreamFactory Software, Inc. <http://www.dreamfactory.com>
 * @package   web-csp
 * @filesource
 */
namespace DreamFactory\Rave\Events\Interfaces;

use DreamFactory\Rave\Events\EventDispatcher;
use DreamFactory\Rave\Events\PlatformEvent;

/**
 * EventObserverLike
 * Something that likes to listen in on events
 */
interface EventObserverLike
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Enables the observer in the eyes of a dispatcher
     *
     * @return void
     */
    public function enable();

    /**
     * Disables the observer in the eyes of a dispatcher
     *
     * @return void
     */
    public function disable();

    /**
     * @return bool True if this observer is enabled for event handling
     */
    public function isEnabled();

    /**
     * Process
     *
     * @param string          $eventName  The name of the event
     * @param PlatformEvent   $event      The event that occurred
     * @param EventDispatcher $dispatcher The source dispatcher
     *
     * @return mixed
     */
    public function handleEvent( $eventName, &$event = null, $dispatcher = null );
}
