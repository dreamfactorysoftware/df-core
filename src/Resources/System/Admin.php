<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Invitable;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\AdminUser;
use DreamFactory\Core\Components\Registrar;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Utility\ResponseFactory;
use Mail;

class Admin extends BaseSystemResource
{
    use Invitable;
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = AdminUser::class;

    protected $resources = [
        Password::RESOURCE_NAME => [
            'name'       => Password::RESOURCE_NAME,
            'class_name' => Password::class,
            'label'      => 'Password'
        ],
        Profile::RESOURCE_NAME  => [
            'name'       => Profile::RESOURCE_NAME,
            'class_name' => Profile::class,
            'label'      => 'Profile'
        ],
        Session::RESOURCE_NAME  => [
            'name'       => Session::RESOURCE_NAME,
            'class_name' => Session::class,
            'label'      => 'Session'
        ],
    ];

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $response = parent::handlePOST();
        if ($this->request->getParameterAsBool('send_invite')) {
            $this->handleInvitation($response, true);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function handlePATCH()
    {
        $response = parent::handlePATCH();
        if ($this->request->getParameterAsBool('send_invite')) {
            if (!$response instanceof ServiceResponseInterface) {
                $response = ResponseFactory::create($response);
            }
            $this->handleInvitation($response);
        }

        return $response;
    }

    protected static function sendInvite($userId, $deleteOnError = false)
    {
        $user = AdminUser::find($userId);
        $user->confirm_code = Registrar::generateConfirmationCode(\Config::get('df.confirm_code_length', 32));
        $user->save();
        $email = $user->email;
        $code = $user->confirm_code;
        $name = $user->first_name;

        Mail::send(
            'emails.invite',
            [
                'content_header' => 'You are invited to administer DreamFactory.',
                'first_name'     => $name,
                'last_name'      => $user->last_name,
                'phone'          => $user->phone,
                'email'          => $user->email,
                'name'           => $user->name,
                'confirm_code'   => $code,
                'instance_name'  => \Config::get('df.instance_name'),
                'link'           => url(\Config::get('df.confirm_admin_invite_url')) .
                    '?code=' . $code .
                    '&email=' . $user->email .
                    '&username=' . $user->username .
                    '&admin=' . $user->is_sys_admin
            ],
            function ($m) use ($email){
                $m->to($email)->subject('[DF] New Admin Invitation');
            }
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    protected function handleResource(array $resources)
    {
        try {
            return parent::handleResource($resources);
        } catch (NotFoundException $e) {
            if (is_numeric($this->resource)) {
                //  Perform any pre-request processing
                $this->preProcess();

                $this->response = $this->processRequest();

                if (false !== $this->response) {
                    //  Perform any post-request processing
                    $this->postProcess();
                }
                //	Inherent failure?
                if (false === $this->response) {
                    $what =
                        (!empty($this->resourcePath) ? " for resource '{$this->resourcePath}'" : ' without a resource');
                    $message =
                        ucfirst($this->action) .
                        " requests $what are not currently supported by the '{$this->name}' service.";

                    throw new BadRequestException($message);
                }

                //  Perform any response processing
                return $this->respond();
            } else {
                throw $e;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelectionCriteria()
    {
        $criteria = parent::getSelectionCriteria();

        $condition = array_get($criteria, 'condition');

        if (!empty($condition)) {
            $condition = "($condition) AND is_sys_admin = '1' ";
        } else {
            $condition = " is_sys_admin = '1'";
        }

        $criteria['condition'] = $condition;

        return $criteria;
    }
}