<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\Form\Field;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Validator;

class Image extends File
{
    /**
     * get html. show link to image
     */
    public function html()
    {
        // get image url
        $url = ExmentFile::getUrl($this->fileValue());
        if (!isset($url)) {
            return $url;
        }

        return '<a href="'.$url.'" target="_blank"><img src="'.$url.'" class="image_html" /></a>';
    }

    protected function getAdminFieldClass()
    {
        return Field\Image::class;
    }
    
    protected function setAdminOptions(&$field, $form_column_options)
    {
        parent::setAdminOptions($field, $form_column_options);

        $field->attribute(['accept' => "image/*"]);
    }
    
    protected function setValidates(&$validates)
    {
        $validates[] = new Validator\ImageRule();
    }
}
