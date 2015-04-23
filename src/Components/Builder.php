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

namespace DreamFactory\Rave\Components;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;

class Builder extends EloquentBuilder
{
    /**
     * Get the relation instance for the given relation name.
     *
     * @param  string $relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getRelation( $relation )
    {
        $query = Relation::noConstraints(
            function () use ( $relation )
            {
                $model = $this->getModel();
                $relationType = $model->getReferencingType( $relation );

                if ( 'has_many' === $relationType )
                {
                    return $model->getHasManyByRelationName( $relation );
                }
                elseif ( 'many_many' === $relationType )
                {
                    return $model->getBelongsToManyByRelationName( $relation );
                }
                elseif( 'belongs_to' === $relationType )
                {
                    return $model->getBelongsToByRelationName( $relation );
                }
            }
        );

        if ( !empty( $query ) )
        {
            return $query;
        }
        else
        {
            return parent::getRelation( $relation );
        }
    }
}