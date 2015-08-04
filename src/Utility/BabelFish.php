<?php
namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Enums\DataFormats;

/**
 * Universal data translator
 */
class BabelFish
{
    /**
     * @param int   $from       The format of the information
     * @param int   $to         The format to translate to
     * @param mixed $subject    THe information to translate
     * @param mixed $translated Holds the translated subject value
     *
     * @return bool
     * @throws BadRequestException
     */
    public static function translate($from, $to, $subject, &$translated)
    {
        $result = true;
        $translated = $subject;

        if (!DataFormats::contains($from) || !DataFormats::contains($to)) {
            throw new BadRequestException('Invalid data format specified');
        }

        //  Translate!
        switch ($from) {
            //  PHP array & object
            case DataFormats::PHP_ARRAY:
            case DataFormats::PHP_OBJECT:
                switch ($to) {
                    //  JSON string
                    case DataFormats::JSON:
                        if (false === ($translated = json_encode($subject, JSON_UNESCAPED_SLASHES))) {
                            if (JSON_ERROR_NONE !== json_last_error()) {
                                $result = false;
                                $translated = $subject;
                            }
                        }
                        break;

                    default:
                        $result = false;
                        break;
                }
                break;

            //  JSON string
            case DataFormats::JSON:
                switch ($to) {
                    //  PHP array & object
                    case DataFormats::PHP_ARRAY:
                    case DataFormats::PHP_OBJECT:
                        if (false === ($translated = json_decode($subject, (DataFormats::PHP_ARRAY == $from)))) {
                            if (JSON_ERROR_NONE !== json_last_error()) {
                                $translated = $subject;
                                $result = false;
                            }
                        }
                        break;

                    default:
                        $result = false;
                        break;
                }
                break;

            default:
                $result = false;
                break;
        }

        return $result;
    }
}