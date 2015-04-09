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

namespace DreamFactory\Rave\Resources;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use Illuminate\Support\Facades\Config;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Rave\Models\BaseSystemModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class BaseRestSystemResource
 *
 * @package DreamFactory\Rave\Resources
 */
class BaseRestSystemResource extends BaseRestResource
{
    /**
     *
     */
    const RECORD_WRAPPER = 'record';
    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;

    /**
     * @var \DreamFactory\Rave\Models\BaseSystemModel Model Class name.
     */
    protected $model = null;

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        $verbAliases = array(
            Verbs::PUT   => Verbs::PATCH,
            Verbs::MERGE => Verbs::PATCH
        );
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct( $settings );
    }

    /**
     * {@inheritdoc}
     */
    protected function getPayloadData( $key = null, $default = null )
    {
        $payload = parent::getPayloadData();

        if ( null !== $key && !empty( $payload[$key] ) )
        {
            return $payload[$key];
        }

        if ( !empty( $this->resource ) && !empty( $payload ) )
        {
            // single records passed in which don't use the record wrapper, so wrap it
            $payload = array( static::RECORD_WRAPPER => array( $payload ) );
        }
        elseif ( ArrayUtils::isArrayNumeric( $payload ) )
        {
            // import from csv, etc doesn't include a wrapper, so wrap it
            $payload = array( static::RECORD_WRAPPER => $payload );
        }

        if(empty($key))
        {
            $key = static::RECORD_WRAPPER;
        }
        return ArrayUtils::get( $payload, $key );
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $requestQuery = $this->getQueryData();
        $ids = ArrayUtils::get( $requestQuery, 'ids' );
        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->getModel();
        /** @var BaseSystemModel $model */
        $model = new $modelClass;

        $data = null;

        $related = ArrayUtils::get($requestQuery, 'related');
        if(!empty($related))
        {
            $related = explode(',', $related);
        }
        else
        {
            $related = [];
        }

        //	Single resource by ID
        if ( !empty( $this->resource ) )
        {
            $foundModel = $modelClass::find( $this->resource );
            if ($foundModel)
            {
                if(!empty($related))
                {
                    $foundModel->enableRelated( $related );
                }
                $data = $foundModel->toArray();
            }
        }
        else if ( !empty( $ids ) )
        {
            /** @var Collection $dataCol */
            $dataCol = $modelClass::whereIn( 'id', explode( ',', $ids ) )->get();
            if(!empty($related))
            {
                $dataCol->each(function($item) use ($related)
                {
                    $item->enableRelated($related);
                });
            }
            $data = $dataCol->all();
            $data = array( self::RECORD_WRAPPER => $data );
        }
        else if ( !empty( $records ) )
        {
            $pk = $model->getPrimaryKey();
            $ids = array();

            foreach ( $records as $record )
            {
                $ids[] = ArrayUtils::get( $record, $pk );
            }

            $dataCol = $model->whereIn( 'id', $ids )->get();
            if(!empty($related))
            {
                $dataCol->each(function($item) use ($related)
                {
                    $item->enableRelated($related);
                });
            }
            $data = $dataCol->all();
            $data = array( self::RECORD_WRAPPER => $data );
        }
        else
        {
            //	Build our criteria
            $criteria = array(
                'params' => array(),
            );

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'fields' ) ) )
            {
                $criteria['select'] = $value;
            }
            else
            {
                $criteria['select'] = "*";
            }

            if ( null !== ( $value = $this->getPayloadData( 'params' ) ) )
            {
                $criteria['params'] = $value;
            }

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'filter' ) ) )
            {
                $criteria['condition'] = $value;

                //	Add current user ID into parameter array if in condition, but not specified.
                if ( false !== stripos( $value, ':user_id' ) )
                {
                    if ( !isset( $criteria['params'][':user_id'] ) )
                    {
                        //$criteria['params'][':user_id'] = Session::getCurrentUserId();
                    }
                }
            }

            $value = intval( ArrayUtils::get( $requestQuery, 'limit' ) );
            $maxAllowed = intval( Config::get( 'rave.db_max_records_returned', self::MAX_RECORDS_RETURNED ) );
            if ( ( $value < 1 ) || ( $value > $maxAllowed ) )
            {
                // impose a limit to protect server
                $value = $maxAllowed;
            }
            $criteria['limit'] = $value;

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'offset' ) ) )
            {
                $criteria['offset'] = $value;
            }

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'order' ) ) )
            {
                $criteria['order'] = $value;
            }

            $data = $model->selectResponse( $criteria, $related );
        }

        if ( null === $data )
        {
            throw new NotFoundException( "Record not found." );
        }

        if(ArrayUtils::getBool($requestQuery, 'include_count')===true)
        {
            $data['meta']['count'] = count($data['record']);
        }

        if(ArrayUtils::getBool($requestQuery, 'include_schema')===true)
        {
            $data['meta']['schema'] = $model->getTableSchema()->toArray();
        }

        return $data;
    }

    /**
     * Handles POST action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePost()
    {
        if ( !empty( $this->resource ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        $model = $this->getModel();
        $result = $model::bulkCreate( $records, $this->getQueryData() );

        $response = ResponseFactory::create( $result, $this->outputFormat, ServiceResponseInterface::HTTP_CREATED );

        return $response;
    }

    /**
     * @throws BadRequestException
     */
    protected function handlePUT()
    {
        throw new BadRequestException( 'PUT is not supported on System Resource. Use PATCH' );
    }

    /**
     * Handles PATCH action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        $records = $this->getPayloadData( static::RECORD_WRAPPER );
        $ids = $this->getQueryData( 'ids' );
        $modelClass = $this->getModel();

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resource ) )
        {
            $result = $modelClass::updateById( $this->resource, $records[0], $this->getQueryData() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::updateByIds( $ids, $records[0], $this->getQueryData() );
        }
        else
        {
            $result = $modelClass::bulkUpdate( $records, $this->getQueryData() );
        }

        return $result;
    }

    /**
     * Handles DELETE action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handleDELETE()
    {
        $this->triggerActionEvent( $this->response );
        $ids = $this->getQueryData( 'ids' );
        $modelClass = $this->getModel();

        if ( !empty( $this->resource ) )
        {
            $result = $modelClass::deleteById( $this->resource, $this->getQueryData() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::deleteByIds( $ids, $this->getQueryData() );
        }
        else
        {
            $records = $this->getPayloadData( static::RECORD_WRAPPER );

            if ( empty( $records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }
            $result = $modelClass::bulkDelete( $records, $this->getQueryData() );
        }

        return $result;
    }

    /**
     * Returns associated model with the service/resource.
     *
     * @return \DreamFactory\Rave\Models\BaseSystemModel
     * @throws ModelNotFoundException
     */
    protected function getModel()
    {
        if ( empty( $this->model ) )
        {
            throw new ModelNotFoundException();
        }

        return $this->model;
    }
}