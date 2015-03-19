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

use DreamFactory\Rave\Exceptions\NotImplementedException;

// Handle administrative options, table add, delete, etc
abstract class BaseNoSqlDbSchemaResource extends BaseDbSchemaResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * General method for creating a pseudo-random identifier
     *
     * @param string $table Name of the table where the item will be stored
     *
     * @return string
     */
    protected static function _createRecordId( $table )
    {
        $_randomTime = abs( time() );

        if ( $_randomTime == 0 )
        {
            $_randomTime = 1;
        }

        $_random1 = rand( 1, $_randomTime );
        $_random2 = rand( 1, 2000000000 );
        $_generateId = strtolower( md5( $_random1 . $table . $_randomTime . $_random2 ) );
        $_randSmall = rand( 10, 99 );

        return $_generateId . $_randSmall;
    }

    /**
     * {@inheritdoc}
     */
    public function describeField( $table, $field, $refresh = false )
    {
        throw new NotImplementedException( 'Not currently supported for NoSQL database services.' );
    }

    /**
     * {@inheritdoc}
     */
    public function createField( $table, $field, $properties = array(), $check_exist = false, $return_schema = false )
    {
        throw new NotImplementedException( 'Not currently supported for NoSQL database services.' );
    }

    /**
     * {@inheritdoc}
     */
    public function updateField( $table, $field, $properties = array(), $allow_delete_parts = false, $return_schema = false )
    {
        throw new NotImplementedException( 'Not currently supported for NoSQL database services.' );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField( $table, $field )
    {
        throw new NotImplementedException( 'Not currently supported for NoSQL database services.' );
    }
}