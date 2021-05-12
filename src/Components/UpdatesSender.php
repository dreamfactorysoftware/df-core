<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\InstanceId;
use DreamFactory\Core\Utility\Session;
use Exception;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;


/**
 * Class UpdatesSender
 *
 */

class UpdatesSender {

    /**
     * @param $service_type
     *
     */
    public static function sendServiceData(string $service_type) {
        $updates_endpoint = 'https://updates.dreamfactory.com/api/created-services';

        if(Session::isAuthenticated()){
            $client = new \GuzzleHttp\Client();
            $client->postAsync($updates_endpoint, [
                'json' => UpdatesSender::gatherServiceAnalyticData($service_type),
                'timeout' => 2,
                'connect_timeout' => 2
            ])
                ->then(
                    function (ResponseInterface $_ignore) {},
                    function (Exception $ex) {
                        \Log::debug($ex->getMessage());
                    }
                )
                ->wait();
        }
    }

    /**
     * @param $service_type
     *
     * @return array
     */
    public static function gatherServiceAnalyticData(string $service_type) {
        $instance_id = InstanceId::getInstanceIdOrGenerate();
        $email = Session::user()->getAttribute('email');

        $data = [];
        $data['service_type'] = $service_type;
        $data['instance_id'] = $instance_id;
        $data['ip_address'] = getHostByName(getHostName());
        $data['email'] = $email;

        return $data;
    }
}
