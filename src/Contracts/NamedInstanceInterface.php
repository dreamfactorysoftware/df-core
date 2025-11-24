<?php
namespace DreamFactory\Core\Contracts;

interface NamedInstanceInterface
{
    /**
     * Return the name of the instance.
     *
     * @return string
     */
    public function getName();

    /**
     * Return the displayable label of the instance.
     *
     * @return string
     */
    public function getLabel();

    /**
     * Return the description of the instance.
     *
     * @return string
     */
    public function getDescription();
}