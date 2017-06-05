<?php

namespace DreamFactory\Core\Components\ResourceImport;

interface Importable
{
    public function import();

    public function revert();

    public function getResource();
}