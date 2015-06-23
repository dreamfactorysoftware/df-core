<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that caches stuff, that can be cleared
 */
interface CachedInterface
{
    /**
     * @return boolean True on success
     */
    public function flush();
}
