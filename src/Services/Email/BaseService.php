<?php

namespace DreamFactory\Core\Services\Email;

use App;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\Session;
use Illuminate\Mail\Message;
use Swift_Transport as SwiftTransport;
use Swift_Mailer as SwiftMailer;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Utility\EmailUtilities;
use DreamFactory\Core\Models\EmailTemplate;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Components\Mailer as DfMailer;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

abstract class BaseService extends BaseRestService
{
    /**
     * @var SwiftTransport
     */
    protected $transport;

    /**
     * @var \Illuminate\Mail\Mailer;
     */
    protected $mailer;

    /**
     * @var Array;
     */
    protected $parameters;

    /**
     * @param array $settings
     */
    public function __construct($settings)
    {
        parent::__construct($settings);

        $config = ArrayUtils::get($settings, 'config', []);
        $this->setParameters($config);
        $this->setTransport($config);
        $this->setMailer();
    }

    /**
     * Sets the email transport layer based on configuration.
     *
     * @param array $config
     */
    abstract protected function setTransport($config);

    protected function setMailer()
    {
        if (!$this->transport instanceof SwiftTransport) {
            throw new InternalServerErrorException('Invalid Email Transport.');
        }

        $swiftMailer = new SwiftMailer($this->transport);
        $this->mailer = new DfMailer(App::make('view'), $swiftMailer, App::make('events'));
    }

    protected function setParameters($config)
    {
        $this->parameters = ArrayUtils::clean(ArrayUtils::get($config, 'parameters', []));

        foreach ($this->parameters as $params) {
            $this->parameters[$params['name']] = ArrayUtils::get($params, 'value');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        return false;
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws NotFoundException
     */
    protected function handlePOST()
    {
        $data = $this->getPayloadData();
        $templateName = $this->request->getParameter('template', null);
        $templateId = $this->request->getParameter('template_id', null);
        $templateData = [];

        if (!empty($templateName)) {
            $templateData = static::getTemplateDataByName($templateName);
        } elseif (!empty($templateId)) {
            $templateData = static::getTemplateDataById($templateId);
        }

        if (empty($templateData) && empty($data)) {
            throw new BadRequestException('No valid data in request.');
        }

        $data = array_merge(ArrayUtils::clean(ArrayUtils::get($templateData, 'defaults', [], true)), $data);
        $data = array_merge($this->parameters, $templateData, $data);

        $text = ArrayUtils::get($data, 'body_text');
        $html = ArrayUtils::get($data, 'body_html');

        $count = $this->sendEmail($data, $text, $html);

        //Mandrill and Mailgun returns Guzzle\Message\Response object.
        if (!is_int($count)) {
            $count = 1;
        }

        return ['count' => $count];
    }

    /**
     * Sends out emails.
     *
     * @param array $data
     * @param null  $textView
     * @param null  $htmlView
     *
     * @return mixed
     */
    public function sendEmail($data, $textView = null, $htmlView = null)
    {
        Session::replaceLookups($textView);
        Session::replaceLookups($htmlView);

        $view = [
            'html' => $htmlView,
            'text' => $textView
        ];

        $count = $this->mailer->send(
            $view,
            $data,
            function (Message $m) use ($data){
                $to = ArrayUtils::get($data, 'to');
                $cc = ArrayUtils::get($data, 'cc');
                $bcc = ArrayUtils::get($data, 'bcc');
                $subject = ArrayUtils::get($data, 'subject');
                $fromName = ArrayUtils::get($data, 'from_name');
                $fromEmail = ArrayUtils::get($data, 'from_email');
                $replyName = ArrayUtils::get($data, 'reply_to_name');
                $replyEmail = ArrayUtils::get($data, 'reply_to_email');

                if (empty($fromEmail)) {
                    $fromEmail = config('mail.from.address');
                    $data['from_email'] = $fromEmail;
                    if (empty($fromName)) {
                        $fromName = config('mail.from.name');
                        $data['from_name'] = $fromName;
                    }
                }

                $to = EmailUtilities::sanitizeAndValidateEmails($to, 'swift');
                if (!empty($cc)) {
                    $cc = EmailUtilities::sanitizeAndValidateEmails($cc, 'swift');
                }
                if (!empty($bcc)) {
                    $bcc = EmailUtilities::sanitizeAndValidateEmails($bcc, 'swift');
                }

                $fromEmail = EmailUtilities::sanitizeAndValidateEmails($fromEmail, 'swift');
                if (!empty($replyEmail)) {
                    $replyEmail = EmailUtilities::sanitizeAndValidateEmails($replyEmail, 'swift');
                }

                $m->to($to);

                if (!empty($fromEmail)) {
                    $m->from($fromEmail, $fromName);
                }
                if (!empty($replyEmail)) {
                    $m->replyTo($replyEmail, $replyName);
                }

                if (!empty($subject)) {
                    Session::replaceLookups($subject);
                    $m->subject(EmailUtilities::applyDataToView($subject, $data));
                }

                if (!empty($bcc)) {
                    $m->bcc($bcc);
                }
                if (!empty($cc)) {
                    $m->cc($cc);
                }
            }
        );

        return $count;
    }

    /**
     * @param $name
     *
     * @throws NotFoundException
     *
     * @return array
     */
    public static function getTemplateDataByName($name)
    {
        // find template in system db
        $template = EmailTemplate::whereName($name)->first();
        if (empty($template)) {
            throw new NotFoundException("Email Template '$name' not found");
        }

        return $template->toArray();
    }

    /**
     * @param $id
     *
     * @throws NotFoundException
     *
     * @return array
     */
    public static function getTemplateDataById($id)
    {
        // find template in system db
        $template = EmailTemplate::whereId($id)->first();
        if (empty($template)) {
            throw new NotFoundException("Email Template id '$id' not found");
        }

        return $template->toArray();
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $base['apis'] = [
            [
                'path'        => '/' . $this->name,
                'operations'  => [
                    [
                        'method'           => 'POST',
                        'summary'          => 'sendEmail() - Send an email created from posted data and/or a template.',
                        'nickname'         => 'sendEmail',
                        'type'             => 'EmailResponse',
                        'event_name'       => 'email.sent',
                        'parameters'       => [
                            [
                                'name'          => 'template',
                                'description'   => 'Optional template name to base email on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'template_id',
                                'description'   => 'Optional template id to base email on.',
                                'allowMultiple' => false,
                                'type'          => 'integer',
                                'format'        => 'int32',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'data',
                                'description'   => 'Data containing name-value pairs used for provisioning emails.',
                                'allowMultiple' => false,
                                'type'          => 'EmailRequest',
                                'paramType'     => 'body',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses(),
                        'notes'            =>
                            'If a template is not used with all required fields, then they must be included in the request. ' .
                            'If the \'from\' address is not provisioned in the service, then it must be included in the request.',
                    ],
                ],
                'description' => 'Operations on a email service.',
            ],
        ];

        $models = [
            'EmailResponse' => [
                'id'         => 'EmailResponse',
                'properties' => [
                    'count' => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Number of emails successfully sent.',
                    ],
                ],
            ],
            'EmailRequest'  => [
                'id'         => 'EmailRequest',
                'properties' => [
                    'template'       => [
                        'type'        => 'string',
                        'description' => 'Email Template name to base email on.',
                    ],
                    'template_id'    => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Email Template id to base email on.',
                    ],
                    'to'             => [
                        'type'        => 'Array',
                        'description' => 'Required single or multiple receiver addresses.',
                        'items'       => [
                            '$ref' => 'EmailAddress',
                        ],
                    ],
                    'cc'             => [
                        'type'        => 'Array',
                        'description' => 'Optional CC receiver addresses.',
                        'items'       => [
                            '$ref' => 'EmailAddress',
                        ],
                    ],
                    'bcc'            => [
                        'type'        => 'Array',
                        'description' => 'Optional BCC receiver addresses.',
                        'items'       => [
                            '$ref' => 'EmailAddress',
                        ],
                    ],
                    'subject'        => [
                        'type'        => 'string',
                        'description' => 'Text only subject line.',
                    ],
                    'body_text'      => [
                        'type'        => 'string',
                        'description' => 'Text only version of the body.',
                    ],
                    'body_html'      => [
                        'type'        => 'string',
                        'description' => 'Escaped HTML version of the body.',
                    ],
                    'from_name'      => [
                        'type'        => 'string',
                        'description' => 'Required sender name.',
                    ],
                    'from_email'     => [
                        'type'        => 'string',
                        'description' => 'Required sender email.',
                    ],
                    'reply_to_name'  => [
                        'type'        => 'string',
                        'description' => 'Optional reply to name.',
                    ],
                    'reply_to_email' => [
                        'type'        => 'string',
                        'description' => 'Optional reply to email.',
                    ],
                ],
            ],
            'EmailAddress'  => [
                'id'         => 'EmailAddress',
                'properties' => [
                    'name'  => [
                        'type'        => 'string',
                        'description' => 'Optional name displayed along with the email address.',
                    ],
                    'email' => [
                        'type'        => 'string',
                        'description' => 'Required email address.',
                    ],
                ],
            ],
        ];

        $base['models'] = array_merge($base['models'], $models);

        return $base;
    }
}