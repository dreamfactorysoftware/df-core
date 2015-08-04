<?php

namespace DreamFactory\Core\Utility;

use App;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;

class EmailUtilities
{
    public static function sanitizeAndValidateEmails($emails, $return_format = '')
    {
        if (is_array($emails)) {
            if (isset($emails[0])) // multiple
            {
                $out = array();
                foreach ($emails as $info) {
                    if (is_array($info)) {
                        $email = ArrayUtils::get($info, 'email');
                        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
                        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            throw new BadRequestException("Invalid email - '$email'.");
                        }
                        if (empty($email)) {
                            throw new BadRequestException('Email can not be empty.');
                        }
                        $name = ArrayUtils::get($info, 'name');
                        if (empty($name)) {
                            $out[] = $email;
                        } else {
                            switch ($return_format) {
                                case 'swift':
                                    $out[$email] = $name;
                                    break;
                                case 'wrapped': // rfc2822
                                    $out[] = $name . '<' . $email . '>';
                                    break;
                                default:
                                    $out[] = $info;
                            }
                        }
                    } else // simple email addresses
                    {
                        $info = filter_var($info, FILTER_SANITIZE_EMAIL);
                        if (false === filter_var($info, FILTER_VALIDATE_EMAIL)) {
                            throw new BadRequestException("Invalid email - '$info'.");
                        }
                        $out[] = $info;
                    }
                }
            } else // single pair
            {
                $email = ArrayUtils::get($emails, 'email');
                $email = filter_var($email, FILTER_SANITIZE_EMAIL);
                if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new BadRequestException("Invalid email - '$email'.");
                }
                if (empty($email)) {
                    throw new BadRequestException('Email can not be empty.');
                }
                $name = ArrayUtils::get($emails, 'name');
                if (empty($name)) {
                    $out = $email;
                } else {
                    switch ($return_format) {
                        case 'swift':
                            $out = array($email => $name);
                            break;
                        case 'wrapped': // rfc2822
                            $out = $name . '<' . $email . '>';
                            break;
                        default:
                            $out = $emails;
                    }
                }
            }
        } else {
            // simple single email
            $emails = filter_var($emails, FILTER_SANITIZE_EMAIL);
            if (false === filter_var($emails, FILTER_VALIDATE_EMAIL)) {
                throw new BadRequestException("Invalid email - '$emails'.");
            }
            $out = $emails;
        }

        return $out;
    }

    /**
     * Applies data to email view
     *
     * @param string $view
     * @param array  $data
     *
     * @return string
     */
    public static function applyDataToView($view, $data)
    {
        // do placeholder replacement, currently {xxx}
        if (!empty($data)) {
            foreach ($data as $name => $value) {
                if (is_string($value)) {
                    // replace {xxx} in subject
                    $view = str_replace('{' . $name . '}', $value, $view);
                }
            }
        }

        return $view;
    }
}