<?php

namespace Exceedone\Exment\Form\Field;

use Encore\Admin\Form\Field\Checkbox;

class CheckboxTable extends Checkbox
{
    protected $view = 'exment::form.field.checkboxtable';
    
    protected $checkWidth = 100;
    protected $items = [];
    protected $headerHelps = [];

    public function checkWidth($checkWidth)
    {
        $this->checkWidth = $checkWidth;

        return $this;
    }

    /**
     * table items.
     * [
     *     'label' => label name,
     *     'values' => selected values,
     *     'name' => checkbox name,
     * ]
     *
     * @param [type] $items
     * @return void
     */
    public function items($items)
    {
        $this->items = $items;
        
        return $this;
    }

    /**
     * header help
     *
     * @param [type] $headerHelp
     * @return void
     */
    public function headerHelp($headerHelps)
    {
        $this->headerHelps = $headerHelps;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function render()
    {
        return parent::render()->with([
            'checkWidth' => $this->checkWidth,
            'items' => $this->items,
            'headerHelps' => collect($this->headerHelps)->toArray(),
        ]);
    }
}
