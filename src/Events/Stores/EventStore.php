<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Rave\Events\Stores;

use DreamFactory\Rave\Events\EventDispatcher;
use DreamFactory\Rave\Events\Interfaces\EventStoreLike;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A wrapper around the app store for the event system
 */
class EventStore implements EventStoreLike
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int Event store caches for 5 minutes max!
     */
    const CACHE_TTL = 300;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var EventDispatcher
     */
    protected $_dispatcher = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Constructor.
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     */
    public function __construct( EventDispatcherInterface $dispatcher )
    {
        $this->_dispatcher = $dispatcher;
    }

    /**
     * @return bool|void
     */
    public function loadAll()
    {
        $_data = Pii::appStoreGet( 'event.config', array() );

        //  Listeners
        foreach ( ArrayUtils::get( $_data, 'listeners', array() ) as $_eventName => $_callables )
        {
            if ( empty( $_callables ) )
            {
                continue;
            }

            foreach ( $_callables as $_priority => $_listeners )
            {
                foreach ( $_listeners as $_listener )
                {
                    $this->_dispatcher->addListener( $_eventName, $_listener, $_priority, true );
                }
            }
        }

        //  Scripts
        foreach ( ( $_scripts = ArrayUtils::get( $_data, 'scripts', array() ) ) as $_eventName => $_scripts )
        {
            $this->_dispatcher->addScript( $_eventName, $_scripts, true );
        }

        //  Observers
        $this->_dispatcher->addObserver( $_observers = ArrayUtils::get( $_data, 'observers', array() ), true );

        return true;
    }

    /**
     * @return bool|void
     */
    public function saveAll()
    {
        $_data = array(
            'listeners' => $this->_dispatcher->getAllListeners(),
            'observers' => $this->_dispatcher->getObservers(),
            'scripts'   => $this->_dispatcher->getScripts(),
        );

        Pii::appStoreSet( 'event.config', $_data );
    }

    /**
     * Flush the cache
     *
     * @return bool
     */
    public function flushAll()
    {
        //  drop a null in for 1 second
        return Pii::appStoreDelete( 'event.config' );
    }

    /**
     * Retrieves cached information from the data store.
     * @return array|null An associative array with server's statistics if available, NULL otherwise.
     */
    public function getStats()
    {
        return null;
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
     */
    public function fetch( $id )
    {
        return Pii::appStoreGet( $id );

    }

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return boolean TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    public function contains( $id )
    {
        return Pii::appStoreContains( $id );
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return boolean TRUE if the cache entry was successfully deleted, FALSE otherwise.
     */
    public function delete( $id )
    {
        return Pii::appStoreDelete( $id );
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id       The cache id.
     * @param mixed  $data     The cache entry/data.
     * @param int    $lifeTime The cache lifetime.
     *                         If != 0, sets a specific lifetime for this cache entry (0 => infinite lifeTime).
     *
     * @return boolean TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public function save( $id, $data, $lifeTime = self::CACHE_TTL )
    {
        return Pii::appStoreSet( $id, $data, $lifeTime );
    }
}
