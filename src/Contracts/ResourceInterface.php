<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that can handle resource requests
 */
interface ResourceInterface extends RequestHandlerInterface
{
    /**
     * @return RequestHandlerInterface
     */
    public function getParent();

    /**
     * @param RequestHandlerInterface $parent
     */
    public function setParent(RequestHandlerInterface $parent);
}
