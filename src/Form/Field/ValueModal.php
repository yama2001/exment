<?php

namespace Exceedone\Exment\Form\Field;

use Encore\Admin\Facades\Admin;
use Encore\Admin\Form\Field;
use Illuminate\Contracts\Support\Renderable;

/**
 * Open Modal and set value selecting modal body.
 * TODO:this field is now only supported custom_column options_calc_formula.
 */
class ValueModal extends Field
{
    protected $view = 'exment::form.field.valuemodal';

    protected $valueTextScript;

    /**
     * @var string
     */
    protected $text;

    /**
     * @var string
     */
    protected $nullText;

    /**
     * @var string
     */
    protected $nullValue;

    /**
     * @var string
     */
    protected $buttonlabel;

    /**
     * @var string
     */
    protected $buttonClass;

    /**
     * @var string
     */
    protected $modalContentname;

    /**
     * @var \Closure
     */
    protected $hiddenFormat;

    /**
     * @var string modal ajax
     */
    protected $ajax;

    /**
     * @var array modal ajax posting names
     */
    protected $post_names = [];

    /**
     * Set text.
     *
     * @param callable|string $text
     *
     * @return $this|mixed
     */
    public function text($text = '')
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Set nullText.
     *
     * @param string $nullText
     *
     * @return $this|mixed
     */
    public function nullText($nullText = '')
    {
        $this->nullText = $nullText;
        return $this;
    }

    /**
     * Set nullValue.
     *
     * @param string $nullValue
     *
     * @return $this|mixed
     */
    public function nullValue($nullValue = '')
    {
        $this->nullValue = $nullValue;
        return $this;
    }

    /**
     * Set modal content key name.
     *
     * @param string $modalContentname
     *
     * @return $this|mixed
     */
    public function modalContentname($modalContentname = '')
    {
        $this->modalContentname = $modalContentname;
        return $this;
    }

    /**
     * Set ajax.
     *
     * @param string $text
     *
     * @return $this|mixed
     */
    public function ajax($ajax = '')
    {
        $this->ajax = $ajax;
        return $this;
    }

    /**
     * Set button label.
     *
     * @param string $buttonlabel
     *
     * @return $this
     */
    public function buttonlabel(string $buttonlabel)
    {
        $this->buttonlabel = $buttonlabel;
        return $this;
    }

    /**
     * Set button class.
     *
     * @param string $buttonlabel
     *
     * @return $this
     */
    public function buttonClass($buttonClass)
    {
        $this->buttonClass = $buttonClass;
        return $this;
    }

    protected function script()
    {
        $classname = $this->getElementClassSelector();
        $modalContentname = $this->modalContentname;
        $post_names = collect($this->post_names)->toJson();
        $ajax = $this->ajax;
        $valueTextScript = $this->valueTextScript ?? '{value:null, text:null}';

        $script = <<<EOT

        // Set to submit button modal event
        {
            let keyname = '[data-contentname="$modalContentname"] .modal-submit';
            $(document).off('click', keyname).on('click', keyname, {}, function(ev){
                let valText = {$valueTextScript};
                
                // set value and text
                let target = getValueModalTarget();
                target.find('.value-valuemodal').val(valText.value);
                target.find('.text-valuemodal').html(valText.text);
                
                let forms = $('.modal form').get();

                if(forms.length > 0 &&!forms[0].reportValidity()){
                    return;
                }
    
                $('.modal').modal('hide');
            });

            // Set to reset event
            keyname = '[data-contentname="$modalContentname"] .modal-reset';
            $(document).off('click', keyname).on('click', keyname, {}, function(ev){
                let target = getValueModalTarget();
                let nullValue = target.find('.nullvalue-valuemodal').val();
                target.find('.value-valuemodal').val(nullValue);

                let nullText = target.find('.nulltext-valuemodal').val();
                target.find('.text-valuemodal').text(nullText);
            });

            function getValueModalTarget(){
                let valueModalUuid = $('.modal .valueModalUuid').val();
                if(hasValue(valueModalUuid)){
                    return $('[data-widgetmodal_uuid="' + valueModalUuid + '"]').closest('.block-valuemodal');
                }

                return  $('$classname').closest('.block-valuemodal');
            }
        }
EOT;
        $this->script = $script;
    }

    /**
     * Set value and text script
     *
     * @param string $script
     * @return $this|mixed
     */
    public function valueTextScript($script){
        $this->valueTextScript = $script;

        return $this;
    }
    
    /**
     * Callback hidden value
     *
     * @param string $script
     * @return $this|mixed
     */
    public function hiddenFormat($hiddenFormat){
        $this->hiddenFormat = $hiddenFormat;

        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function render()
    {
        // set text
        if ($this->text instanceof \Closure) {
            if ($this->form) {
                $this->text = $this->text->bindTo($this->form->model());
            }

            $this->text(call_user_func($this->text, $this->value, $this));
        }

        if(is_array($this->text) || $this->text instanceof \Illuminate\Support\Collection){
            $this->text = collect($this->text)->map(function($t){
                return esc_html($t);
            })->implode('<br />');
        }else{
            $this->text = esc_html($this->text);
        }

        // set hidden
        $hidden = $this->value;
        if ($this->hiddenFormat instanceof \Closure) {
            $hidden = call_user_func($this->hiddenFormat, $this->value, $this);
        }
        
        $nullValue = $this->nullValue;
        if ($this->nullValue instanceof \Closure) {
            $nullValue = call_user_func($this->nullValue, $this->value, $this);
        }

        // set button label
        if (is_null($this->buttonlabel)) {
            $this->buttonlabel = exmtrans('common.change');
        }
 
        // set button class
        if (is_null($this->buttonClass)) {
            $this->buttonClass = 'btn-default';
        }
 
        // set button label
        if (is_array($this->value)) {
            $this->value = json_encode($this->value);
        }   

        // set script
        $this->script();

        return parent::render()->with([
            'text'   => $this->text,
            'hidden' => $hidden,
            'nullText'   => $this->nullText,
            'nullValue'   => $nullValue,
            'buttonlabel'   => $this->buttonlabel,
            'buttonClass'   => $this->buttonClass,
            'ajax' => $this->ajax,
            'modalContentname' => $this->modalContentname,
        ]);
    }
}
