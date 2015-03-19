<?php
/**
 * This file is part of the DreamFactory Rave(tm) Common
 *
 * DreamFactory Rave(tm) Common <http://github.com/dreamfactorysoftware/rave-common>
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
namespace DreamFactory\Rave\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;
use DreamFactory\Rave\Exceptions\NotImplementedException;

/**
 * Various HTTP and server-side content types.
 *
 * This class defines formats that are passed in and out of services and
 * that are potentially able to be formatted to other types.
 */
class ContentTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::RAW;

    /**
     * @var int raw/original/unadulterated - i.e. Don't attempt to reformat!
     */
    const RAW = 0;
    /**
     * @var int JavaScript Object Notation - application/json
     */
    const JSON = 1;
    /**
     * @var int Extensible Markup Language - application/xml
     */
    const XML = 2;
    /**
     * @var int Comma-separated values - text/csv
     */
    const CSV = 3;
    /**
     * @var int Tab-separated values - application/tab-separated-values
     */
    const TSV = 4;
    /**
     * @var int Pipe-separated values -
     */
    const PSV = 5;
    /**
     * @var int Hypertext Markup Language (HTML) - text/html
     */
    const HTML = 6;
    /**
     * @var int Extensible Hypertext Markup Language (XHTML) - application/xhtml+xml
     */
    const XHTML = 7;
    /**
     * @var int Plain text - text/plain
     */
    const TEXT = 8;
    /**
     * @var int Rich Text Format - application/rtf
     */
    const RTF = 9;
    /**
     * @var int Resource Descriptive Framework - application/rdf+xml
     */
    const RDF = 10;
    /**
     * @var int YAML - text/yaml
     */
    const YAML = 11;
    /**
     * @var int ATOM - application/atom+xml
     */
    const ATOM = 12;
    /**
     * @var int Really Simple Syndication - application/rss+xml
     */
    const RSS = 13;
    /**
     * @var int application/x-www-form-urlencoded
     */
    const WWW = 14;

    // Client-side Only Content Types

    /**
     * @var int Javascript text - application/javascript
     */
    const JS = 100;
    /**
     * @var int Cascading Style Sheet - text/css
     */
    const CSS = 101;

    // Server-side Only Content Types

    /**
     * @var int Some standard PHP code
     */
    const PHP = 200;
    /**
     * @var int A standard PHP array
     */
    const PHP_ARRAY = 201;
    /**
     * @var int A standard PHP stdClass
     */
    const PHP_OBJECT = 202;
    /**
     * @var int A standard PHP SimpleXML class
     */
    const PHP_SIMPLEXML = 203;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of enum values against file extensions
     */
    protected static $_extensionMap = array(
        self::CSV           => 'csv',
        self::TSV           => 'tsv',
        self::PSV           => 'psv',
        self::HTML          => 'html',
        self::XHTML         => 'xhtml',
        self::TEXT          => 'txt',
        self::RTF           => 'rtf',
        self::JSON          => 'json',
        self::XML           => 'xml',
        self::ATOM          => 'atom',
        self::RSS           => 'rss',
        self::YAML          => 'yaml',
        self::RDF           => 'rdf',
        self::WWW           => 'www',
        self::JS            => 'js',
        self::CSS           => 'css',
        self::PHP           => 'php',
        self::PHP_ARRAY     => null,
        self::PHP_OBJECT    => null,
        self::PHP_SIMPLEXML => null,
        self::RAW           => null,
    );

    protected static $_contentTypeMap = array(
        self::CSV           => 'text/csv',
        self::TSV           => 'text/tab-separated-values',
        self::PSV           => 'application/octet-stream',
        self::HTML          => 'text/html',
        self::XHTML         => 'application/xhtml+xml',
        self::TEXT          => 'text/plain',
        self::RTF           => 'application/rtf',
        self::JSON          => 'application/json',
        self::XML           => 'application/xml',
        self::ATOM          => 'application/atom+xml',
        self::RSS           => 'application/rss+xml',
        self::YAML          => 'text/yaml',
        self::RDF           => 'application/rdf+xml',
        self::WWW           => 'application/x-www-form-urlencoded',
        self::JS            => 'application/javascript',
        self::CSS           => 'text/css',
        self::PHP           => null,
        self::PHP_ARRAY     => null,
        self::PHP_OBJECT    => null,
        self::PHP_SIMPLEXML => null,
        self::RAW           => null,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $contentType
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric( $contentType )
    {
        if ( !is_string( $contentType ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $contentType . '" is not a string.' );
        }

        return static::defines( strtoupper( $contentType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel = self::TEXT )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $numericLevel . '" is not numeric.' );
        }

        return static::nameOf( $numericLevel, true, false );
    }

    /**
     * Translates/converts class enum value to an outbound HTTP content-type's MIME type
     *
     * @param int $enum_value
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toMimeType( $enum_value )
    {
        if ( !is_numeric( $enum_value ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $enum_value . '" is not numeric.' );
        }

        if ( !array_key_exists( $enum_value, static::$_contentTypeMap ) )
        {
            throw new NotImplementedException( 'The content type "' . $enum_value . '" is not supported.' );
        }

        return static::$_contentTypeMap[$enum_value];
    }

    /**
     * Translates/converts an inbound HTTP content-type's MIME type to a class enum value
     *
     * @param string $mime_type
     *
     * @throws NotImplementedException
     * @return int
     */
    public static function fromMimeType( $mime_type )
    {
        if ( !is_string( $mime_type ) )
        {
            throw new \InvalidArgumentException( 'The MIME type "' . $mime_type . '" is not a string.' );
        }

        if ( false === $_pos = array_search( strtolower( $mime_type ), static::$_contentTypeMap ) )
        {
            throw new NotImplementedException( 'The MIME type "' . $mime_type . '" is not supported.' );
        }

        return static::$_contentTypeMap[$_pos];
    }

    /**
     * Translates/converts class enum value to an outbound file extension
     *
     * @param int $enum_value
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toFileExtension( $enum_value )
    {
        if ( !is_numeric( $enum_value ) )
        {
            throw new \InvalidArgumentException( 'The content type "' . $enum_value . '" is not numeric.' );
        }

        if ( !array_key_exists( $enum_value, static::$_extensionMap ) )
        {
            throw new NotImplementedException( 'The content type "' . $enum_value . '" is not supported.' );
        }

        return static::$_extensionMap[$enum_value];
    }

    /**
     * Translates/converts an inbound file extension to a class enum value
     *
     * @param string $extension
     *
     * @throws NotImplementedException
     * @return int
     */
    public static function fromFileExtension( $extension )
    {
        if ( !is_string( $extension ) )
        {
            throw new \InvalidArgumentException( 'The file extension "' . $extension . '" is not a string.' );
        }

        if ( false === $_pos = array_search( strtolower( $extension ), static::$_extensionMap ) )
        {
            throw new NotImplementedException( 'The file extension "' . $extension . '" is not supported.' );
        }

        return static::$_extensionMap[$_pos];
    }
}
