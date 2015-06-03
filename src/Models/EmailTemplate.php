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

namespace DreamFactory\Rave\Models;

/**
 * EmailTemplate
 *
 * @property integer $id
 * @property string  $name
 * @property string  $description
 * @property string  $to
 * @property string  $cc
 * @property string  $bcc
 * @property string  $subject
 * @property string  $body_text
 * @property string  $body_html
 * @property string  $from_name
 * @property string  $from_email
 * @property string  $reply_to_name
 * @property string  $reply_to_email
 * @property string  $defaults
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereLastModifiedDate( $value )
 */
class EmailTemplate extends BaseSystemModel
{
    protected $table = 'email_template';

    protected $fillable = ['name'];
}