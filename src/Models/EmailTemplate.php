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
        'attachment',
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

    /**
     * @param $value
     *
     * @return string
     */
    protected static function getJSONIfArray($value)
    {
        if (is_string($value) && strpos($value, ',') !== false) {
            $value = explode(',', $value);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_string($v)) {
                    $value[$k] = trim($v);
                }
            }

            return json_encode($value);
        }

        return $value;
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    protected static function getArrayIfJSON($value)
    {
        if (is_array($value)) {
            return $value;
        } else {
            $toArray = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $toArray;
            } else {
                return $value;
            }
        }
    }

    /** @inheritdoc */
    public function setToAttribute($to)
    {
        $this->attributes['to'] = static::getJSONIfArray($to);
    }

    /** @inheritdoc */
    public function getToAttribute($to)
    {
        return static::getArrayIfJSON($to);
    }

    /** @inheritdoc */
    public function setAttachmentAttribute($attachment)
    {
        $this->attributes['attachment'] = static::getJSONIfArray($attachment);
    }

    /** @inheritdoc */
    public function getAttachmentAttribute($attachment)
    {
        return static::getArrayIfJSON($attachment);
    }

    /** @inheritdoc */
    public function setCcAttribute($cc)
    {
        $this->attributes['cc'] = static::getJSONIfArray($cc);
    }

    /** @inheritdoc */
    public function getCcAttribute($cc)
    {
        return static::getArrayIfJSON($cc);
    }

    /** @inheritdoc */
    public function setBccAttribute($bcc)
    {
        $this->attributes['bcc'] = static::getJSONIfArray($bcc);
    }

    /** @inheritdoc */
    public function getBccAttribute($bcc)
    {
        return static::getArrayIfJSON($bcc);
    }

    public function getErrors()
    {
        if (is_array($this->errors)) {
            foreach ($this->errors as $key => $value) {
                if ($key === 'name' && strpos(array_get($value, 0), 'is required') !== false) {
                    $value[0] = 'The Template Name field is required';
                    $this->errors[$key] = $value;
                }
            }
        }

        return $this->errors;
    }
}