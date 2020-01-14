<?php
namespace Exceedone\Exment\Validator;

use Illuminate\Contracts\Validation\Rule;

/**
 * FileRule.
 * Consider comma.
 */
class FileRule implements Rule
{
    /**
    * Check Validation
    *
    * @param  string  $attribute
    * @param  mixed  $value
    * @return bool
    */
    public function passes($attribute, $value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_array($value) && !is_vector($value)) {
            if (!array_has($value, 'name') || !array_has($value, 'base64')) {
                return false;
            }
        }

        return true;
    }

    /**
     * get validation error message
     *
     * @return string
     */
    public function message()
    {
        return trans('validation.base64_file');
    }
}
