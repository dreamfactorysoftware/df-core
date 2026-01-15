<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface ApiDocsInterface
 *
 * Something that produces API documentation
 *
 * @package DreamFactory\Core\Contracts
 */
interface ApiDocsInterface
{
    /**
     * @param bool $refresh
     * @return array Array of all necessary parts to document an API, i.e. paths, components, etc.
     */
    public function getApiDoc($refresh = false);
}
