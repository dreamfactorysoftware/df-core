<?php namespace DreamFactory\Core\Enums;

use Symfony\Component\HttpFoundation\Request;

/**
 * All the HTTP verbs in a single place!
 */
class Verbs extends FactoryEnum
{
    //******************************************************************************
    //* Constants
    //******************************************************************************

    /**
     * @type string
     */
    const GET = Request::METHOD_GET;
    /**
     * @type string
     */
    const PUT = Request::METHOD_PUT;
    /**
     * @type string
     */
    const HEAD = Request::METHOD_HEAD;
    /**
     * @type string
     */
    const POST = Request::METHOD_POST;
    /**
     * @type string
     */
    const DELETE = Request::METHOD_DELETE;
    /**
     * @type string
     */
    const OPTIONS = Request::METHOD_OPTIONS;
    /**
     * @type string
     */
    const COPY = 'COPY';
    /**
     * @type string
     */
    const PATCH = Request::METHOD_PATCH;
    /**
     * @type string
     */
    const TRACE = Request::METHOD_TRACE;
    /**
     * @type string
     */
    const CONNECT = Request::METHOD_CONNECT;
}