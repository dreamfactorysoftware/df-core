<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
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

namespace DreamFactory\Rave\Services\Email;

use App;
use GuzzleHttp\Message\Response;
use Illuminate\Mail\Message;
use Swift_Transport as SwiftTransport;
use Swift_Mailer as SwiftMailer;
use DreamFactory\Rave\Services\BaseRestService;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Utility\EmailUtilities;
use DreamFactory\Rave\Models\EmailTemplate;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Components\Mailer as RaveMailer;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;

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
    public function __construct( $settings )
    {
        parent::__construct( $settings );

        $config = ArrayUtils::get( $settings, 'config', array() );
        $this->setParameters($config);
        $this->setTransport($config);
        $this->setMailer();
    }

    /**
     * Sets the email transport layer based on configuration.
     * @param array $config
     */
    abstract protected function setTransport($config);

    protected function setMailer()
    {
        if(!$this->transport instanceof SwiftTransport)
        {
            throw new InternalServerErrorException('Invalid Email Transport.');
        }

        $swiftMailer = new SwiftMailer($this->transport);
        $this->mailer = new RaveMailer( App::make( 'view' ), $swiftMailer, App::make( 'events' ) );
    }

    protected function setParameters($config)
    {
        $parameters = ArrayUtils::clean( ArrayUtils::get( $config, 'parameters', array() ) );

        foreach($parameters as $params)
        {
            $this->parameters[$params['name']] = ArrayUtils::get($params, 'value');
        }
    }

    public function handleGET()
    {
        return [];
    }

    /**
     * @return array
     * @throws BadRequestException
     * @throws NotFoundException
     */
    public function handlePOST()
    {
        $data = $this->getPayloadData();
        $templateName = $this->getQueryData( 'template', null );
        $templateId = $this->getQueryData( 'template_id', null );
        $templateData = [];

        if ( !empty( $templateName ) )
        {
            $templateData = static::getTemplateDataByName( $templateName );
        }
        elseif ( !empty( $templateId ) )
        {
            $templateData = static::getTemplateDataById( $templateId );
        }

        if ( empty( $templateData ) && empty( $data ) )
        {
            throw new BadRequestException( 'No valid data in request.' );
        }

        $data = array_merge( ArrayUtils::clean( ArrayUtils::get( $templateData, 'defaults', array(), true ) ), $data );
        $data = array_merge( $this->parameters, $templateData, $data );

        $text = ArrayUtils::get( $data, 'body_text' );
        $html = ArrayUtils::get( $data, 'body_html' );

        $view = [
            'html' => $html,
            'text' => $text
        ];

        $count = $this->mailer->send(
            $view,
            $data,
            function ( Message $m ) use ( $data )
            {
                $to = ArrayUtils::get( $data, 'to' );
                $cc = ArrayUtils::get( $data, 'cc' );
                $bcc = ArrayUtils::get( $data, 'bcc' );
                $subject = ArrayUtils::get( $data, 'subject' );
                $fromName = ArrayUtils::get( $data, 'from_name' );
                $fromEmail = ArrayUtils::get( $data, 'from_email' );
                $replyName = ArrayUtils::get( $data, 'reply_to_name' );
                $replyEmail = ArrayUtils::get( $data, 'reply_to_email' );

                if ( empty( $fromEmail ) )
                {
                    $fromEmail = 'no-reply@dreamfactory.com';
                    $data['from_email'] = $fromEmail;
                    if ( empty( $fromName ) )
                    {
                        $fromName = 'DreamFactory Software, Inc.';
                        $data['from_name'] = $fromName;
                    }
                }

                $to = EmailUtilities::sanitizeAndValidateEmails( $to, 'swift' );
                if ( !empty( $cc ) )
                {
                    $cc = EmailUtilities::sanitizeAndValidateEmails( $cc, 'swift' );
                }
                if ( !empty( $bcc ) )
                {
                    $bcc = EmailUtilities::sanitizeAndValidateEmails( $bcc, 'swift' );
                }

                $fromEmail = EmailUtilities::sanitizeAndValidateEmails( $fromEmail, 'swift' );
                if ( !empty( $replyEmail ) )
                {
                    $replyEmail = EmailUtilities::sanitizeAndValidateEmails( $replyEmail, 'swift' );
                }

                $m->to( $to );


                if(!empty($fromEmail))
                {
                    $m->from( $fromEmail, $fromName );
                }
                if(!empty($replyEmail))
                {
                    $m->replyTo( $replyEmail, $replyName );
                }

                if(!empty($subject))
                {
                    $m->subject( EmailUtilities::applyDataToView( $subject, $data ) );
                }

                if ( !empty( $bcc ) )
                {
                    $m->bcc( $bcc );
                }
                if ( !empty( $cc ) )
                {
                    $m->cc( $cc );
                }
            }
        );

        //Mandrill and Mailgun returns Guzzle\Message\Response object.
        if(!is_int($count))
        {
            $count = 1;
        }

        return [ 'count' => $count ];
    }

    /**
     * @param $name
     *
     * @throws NotFoundException
     *
     * @return array
     */
    public static function getTemplateDataByName( $name )
    {
        // find template in system db
        $template = EmailTemplate::whereName( $name )->first();
        if ( empty( $template ) )
        {
            throw new NotFoundException( "Email Template '$name' not found" );
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
    public static function getTemplateDataById( $id )
    {
        // find template in system db
        $template = EmailTemplate::whereId( $id )->first();
        if ( empty( $template ) )
        {
            throw new NotFoundException( "Email Template id '$id' not found" );
        }

        return $template->toArray();
    }
}