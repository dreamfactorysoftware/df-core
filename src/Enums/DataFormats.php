<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various supported data formats.
 *
 * This class defines formats that are passed in and out of services and
 * that are potentially able to be formatted to other types. It includes
 * conversions to MIME type for Content-Type and Accepts headers and
 * file extensions for uploading/downloading files.
 */
class DataFormats extends FactoryEnum
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
    /**
     * @var int application/soap+xml, for SOAP1.2 interfaces
     */
    const SOAP = 15;

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
    protected static $extensionMap = [
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
        self::SOAP          => 'soap',
        self::JS            => 'js',
        self::CSS           => 'css',
        self::PHP           => 'php',
        self::PHP_ARRAY     => null,
        self::PHP_OBJECT    => null,
        self::PHP_SIMPLEXML => null,
        self::RAW           => null,
    ];

    /**
     * @var array A hash of enum values against modern MIME types
     */
    protected static $contentTypeMap = [
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
        self::SOAP          => 'application/soap+xml',
        self::JS            => 'application/javascript',
        self::CSS           => 'text/css',
        self::PHP           => null,
        self::PHP_ARRAY     => null,
        self::PHP_OBJECT    => null,
        self::PHP_SIMPLEXML => null,
        self::RAW           => null,
    ];

    /**
     * @var array A hash of modern and old MIME types to enum values
     */
    protected static $mimeTypeMap = [
        'text/csv'                          => self::CSV,
        'text/tab-separated-values'         => self::TSV,
        'text/pipe-separated-values'        => self::PSV,
        'text/html'                         => self::HTML,
        'application/xhtml+xml'             => self::XHTML,
        'text/plain'                        => self::TEXT,
        'application/rtf'                   => self::RTF,
        'application/json'                  => self::JSON,
        'application/xml'                   => self::XML,
        'text/xml'                          => self::XML, // older type
        'application/atom+xml'              => self::ATOM,
        'application/rss+xml'               => self::RSS,
        'text/yaml'                         => self::YAML,
        'application/rdf+xml'               => self::RDF,
        'application/soap+xml'              => self::SOAP,
        'application/x-www-form-urlencoded' => self::WWW,
        'application/javascript'            => self::JS,
        'text/javascript'                   => self::JS, // older type
        'text/css'                          => self::CSS,
    ];

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $contentType
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric($contentType)
    {
        if (!is_string($contentType)) {
            throw new \InvalidArgumentException('The content type "' . $contentType . '" is not a string.');
        }

        return static::defines(strtoupper($contentType), true);
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString($numericLevel = self::TEXT)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The content type "' . $numericLevel . '" is not numeric.');
        }

        return static::nameOf($numericLevel, true, false);
    }

    /**
     * Translates/converts class enum value to an outbound HTTP content-type's MIME type
     *
     * @param int     $enum_value
     * @param string  $default            Default to return if enum value not found
     * @param boolean $throw_if_not_found Throw and exception if not found, otherwise return default
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toMimeType($enum_value, $default = 'application/octet-stream', $throw_if_not_found = false)
    {
        if (!is_numeric($enum_value)) {
            throw new \InvalidArgumentException('The content type "' . $enum_value . '" is not numeric.');
        }

        if (!array_key_exists($enum_value, static::$contentTypeMap)) {
            if ($throw_if_not_found) {
                throw new NotImplementedException('The content type "' . $enum_value . '" is not supported.');
            }

            return $default;
        }

        return (static::$contentTypeMap[$enum_value]) ?: $default;
    }

    /**
     * Translates/converts an inbound HTTP content-type's MIME type to a class enum value
     *
     * @param string   $mime_type
     * @param int|null $default            Default to return if mime type value not found
     * @param boolean  $throw_if_not_found Throw and exception if not found, otherwise return default
     *
     * @throws NotImplementedException
     * @return int
     */
    public static function fromMimeType($mime_type, $default = self::RAW, $throw_if_not_found = false)
    {
        if (!is_string($mime_type)) {
            throw new \InvalidArgumentException('The MIME type "' . $mime_type . '" is not a string.');
        }

        $mime_type = (false !== strpos($mime_type, ';')) ? trim(strstr($mime_type, ';', true)) : $mime_type;
        $mime_type = strtolower($mime_type);
        if (!array_key_exists($mime_type, static::$mimeTypeMap)) {
            if ($throw_if_not_found) {
                throw new NotImplementedException('The mime type "' . $mime_type . '" is not supported.');
            }

            return $default;
        }

        return (static::$mimeTypeMap[$mime_type]) ?: $default;
    }

    /**
     * Translates/converts class enum value to an outbound file extension
     *
     * @param int     $enum_value
     * @param string  $default            Default to return if enum value not found
     * @param boolean $throw_if_not_found Throw and exception if not found, otherwise return default
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toFileExtension($enum_value, $default = 'txt', $throw_if_not_found = false)
    {
        if (!is_numeric($enum_value)) {
            throw new \InvalidArgumentException('The content type "' . $enum_value . '" is not numeric.');
        }

        if (!array_key_exists($enum_value, static::$extensionMap)) {
            if ($throw_if_not_found) {
                throw new NotImplementedException('The content type "' . $enum_value . '" is not supported.');
            }

            return $default;
        }

        return static::$extensionMap[$enum_value];
    }

    /**
     * Translates/converts an inbound file extension to a class enum value
     *
     * @param string   $extension
     * @param int|null $default            Default to return if extension not found
     * @param boolean  $throw_if_not_found Throw and exception if not found, otherwise return default
     *
     * @throws NotImplementedException
     * @return int
     */
    public static function fromFileExtension($extension, $default = self::RAW, $throw_if_not_found = false)
    {
        if (!is_string($extension)) {
            throw new \InvalidArgumentException('The file extension "' . $extension . '" is not a string.');
        }

        if (false === $pos = array_search(strtolower($extension), static::$extensionMap)) {
            if ($throw_if_not_found) {
                throw new NotImplementedException('The file extension "' . $extension . '" is not supported.');
            }

            return $default;
        }

        return static::$extensionMap[$pos];
    }
}
