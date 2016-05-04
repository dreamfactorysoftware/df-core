<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface ScriptEngineTypeInterface
 *
 * Something that defines a script engine type
 *
 * @package DreamFactory\Core\Contracts
 */
interface ScriptEngineTypeInterface
{
    /**
     * Script engine type name - matching registered script engine types
     *
     * @return string
     */
    public function getName();

    /**
     * Displayable script engine type label
     *
     * @return string
     */
    public function getLabel();

    /**
     * Script engine type description
     *
     * @return string
     */
    public function getDescription();

    /**
     * Does this script engine type not have access to the rest of the OS?
     *
     * @return boolean
     */
    public function isSandboxed();

    /**
     * Does this script engine type support inline execution instead of writing to file?
     *
     * @return boolean
     */
    public function supportsInlineExecution();

    /**
     * The factory interface for this script engine type
     *
     * @param array  $config
     *
     * @return \DreamFactory\Core\Contracts\ScriptingEngineInterface|null
     */
    public function make(array $config = []);

    /**
     * Return the script engine type information as an array.
     *
     * @return array
     */
    public function toArray();
}
