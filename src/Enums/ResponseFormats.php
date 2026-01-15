<?php
namespace DreamFactory\Core\Enums;


/**
 * ResponseFormats
 * Supported DreamFactory response formats
 */
class ResponseFormats extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const __default = self::RAW;

    /**
     * @var int No formatting. The default
     */
    const RAW = 0;
    /**
     * @var int DataTables formatting {@ink http://datatables.net/}
     */
    const DATATABLES = 100;
    /**
     * @var int jTable formatting {@link http://www.jtable.org/}
     */
    const JTABLE = 101;
    /**
     * @var int aciTree formatting {@link http://plugins.jquery.com/aciTree/}
     */
    const ACITREE = 102;
}