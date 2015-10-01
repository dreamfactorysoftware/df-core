<?php

namespace DreamFactory\Core\Models;

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
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereId($value)
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereName($value)
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|EmailTemplate whereLastModifiedDate($value)
 */
class EmailTemplate extends BaseSystemModel
{
    protected $table = 'email_template';

    protected $fillable = [
        'name',
        'description',
        'to',
        'cc',
        'bcc',
        'subject',
        'body_text',
        'body_html',
        'from_name',
        'from_email',
        'reply_to_name',
        'reply_to_email'
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    protected $rules = [
        'name' => 'required'
    ];
}