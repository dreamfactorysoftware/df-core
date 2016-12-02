<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that behaves like a email service and can send email
 */
/**
 * Interface EmailServiceInterface
 *
 * @package DreamFactory\Core\Contracts
 */
interface EmailServiceInterface
{
    /**
     * Sends out emails.
     *
     * @param array $data
     * @param null  $textView
     * @param null  $htmlView
     *
     * @return mixed
     */
    public function sendEmail($data, $textView = null, $htmlView = null);
}
