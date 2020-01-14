<?php
namespace Exceedone\Exment\Validator;

use Illuminate\Contracts\Validation\Rule;

/**
 * ImageRule.
 * Consider comma.
 */
class ImageRule extends FileRule
{
    use \Illuminate\Validation\Concerns\ValidatesAttributes;

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

        if (is_string($value)) {
            return $this->validateImage($attribute, $value);
        }

        return parent::passes($attribute, $value);
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
