<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\DataFormatter;
use Validator;

/**
 * Class Validatable
 *
 * @package DreamFactory\Core\Components
 */
trait Validatable
{
    /**
     * Rules for validating model data
     *
     * @type array
     */
    protected $rules = [];

    /**
     * Validation error messages
     *
     * @type array
     */
    protected $validationMessages = [];

    /**
     * Stores validation errors.
     *
     * @type array
     */
    protected $errors = [];

    /**
     * Validates data based on $this->rules.
     *
     * @param array     $data
     * @param bool|true $throwException
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function validate($data, $throwException = true)
    {
        if (empty($rules = $this->getRules())) {
            return true;
        } else {
            $validator = Validator::make($data, $rules, $this->validationMessages);

            if ($validator->fails()) {
                $this->errors($validator->errors()->getMessages());
                if ($throwException) {
                    $errorString = DataFormatter::validationErrorsToString($this->getErrors());
                    throw new BadRequestException('Invalid data supplied.' . $errorString, null, null, $this->getErrors());
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function rules(array $rules)
    {
        $this->rules = $rules;

        return $this;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function errors(array $errors)
    {
        $this->errors = $errors;

        return $this;
    }
}