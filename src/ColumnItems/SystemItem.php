<?php

namespace Exceedone\Exment\ColumnItems;

use Encore\Admin\Form\Field\Date;
use Encore\Admin\Form\Field\Select;
use Encore\Admin\Form\Field\Text;
use Exceedone\Exment\Form\Field;
use Exceedone\Exment\Enums\SummaryCondition;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\Traits\ColumnOptionQueryTrait;

class SystemItem implements ItemInterface
{
    use ItemTrait, ColumnOptionQueryTrait;
    
    protected $column_name;
    
    protected $custom_table;
    
    public function __construct($custom_table, $column_name, $custom_value)
    {
        // if view_pivot(like select table), custom_table is target's table
        $this->custom_table = $custom_table;
        $this->setCustomValue($custom_value);

        $params = static::getOptionParams($column_name, $custom_table);
        $this->column_name = $params['column_target'];

        // get label. check not match $this->custom_table and pivot table
        if (array_key_value_exists('view_pivot_table_id', $params) && $this->custom_table->id != $params['view_pivot_table_id']) {
            $this->label = static::getViewColumnLabel(exmtrans("common.$this->column_name"), $this->custom_table->table_view_name);
        } else {
            $this->label = exmtrans("common.$this->column_name");
        }
    }

    /**
     * get column name
     */
    public function name()
    {
        return $this->column_name;
    }

    /**
     * get column key sql name.
     */
    public function sqlname()
    {
        if (boolval(array_get($this->options, 'summary'))) {
            return $this->getSummarySqlName();
        }
        if (boolval(array_get($this->options, 'groupby'))) {
            return $this->getGroupBySqlName();
        }
        return $this->getSqlColumnName();
    }

    /**
     * get column key refer to subquery.
     */
    public function getGroupName()
    {
        if (boolval(array_get($this->options, 'summary'))) {
            $summary_condition = SummaryCondition::getSummaryCondition(array_get($this->options, 'summary_condition'));
            $alter_name = $this->sqlAsName();
            
            // wrap
            $raw = \DB::getQueryGrammar()->wrap("$summary_condition($alter_name) AS $alter_name");
            return \DB::raw($raw);
        }
        return null;
    }

    /**
     * get sqlname for summary
     */
    protected function getSummarySqlName()
    {
        $column_name = $this->getSqlColumnName();

        $summary_option = array_get($this->options, 'summary_condition');
        $summary_condition = is_null($summary_option)? null: SummaryCondition::getEnum($summary_option)->lowerKey();
        $group_condition = array_get($this->options, 'group_condition');
        $grammer = \DB::getQueryGrammar();

        if (isset($summary_condition)) {
            // wrap
            $raw = $grammer->wrap("$summary_condition($column_name) AS ".$this->sqlAsName());
        } elseif (isset($group_condition)) {
            // wrap
            $raw = $grammer->getDateFormatString($group_condition, $column_name, false) . " AS ".$this->sqlAsName();
        } else {
            // wrap
            $raw = $grammer->wrap("$column_name AS ".$this->sqlAsName());
        }

        return \DB::raw($raw);
    }

    /**
     * get sqlname for grouping
     */
    protected function getGroupBySqlName()
    {
        $column_name = $this->getSqlColumnName();

        $group_condition = array_get($this->options, 'group_condition');

        if (isset($group_condition)) {
            $raw = \DB::getQueryGrammar()->getDateFormatString($group_condition, $column_name, true);
        } else {
            $raw = $column_name;
        }

        return \DB::raw($raw);
    }

    /**
     * get sql query column name
     */
    protected function getSqlColumnName()
    {
        // get SystemColumn enum
        $option = $this->getSystemColumnOption();
        if (!isset($option)) {
            $sqlname = $this->column_name;
        } else {
            $sqlname = array_get($option, 'sqlname');
        }
        return getDBTableName($this->custom_table) .'.'. $sqlname;
    }

    public function sqlAsName()
    {
        return "column_".array_get($this->options, 'summary_index');
    }

    /**
     * get index name
     */
    public function index()
    {
        $option = $this->getSystemColumnOption();
        return array_get($option, 'sqlname', $this->name());
    }

    /**
     * get text(for display)
     */
    public function text()
    {
        return $this->getTargetValue(false);
    }

    /**
     * get html(for display)
     * *this function calls from non-escaping value method. So please escape if not necessary unescape.
     */
    public function html()
    {
        return $this->getTargetValue(true);
    }

    /**
     * get grid style
     */
    public function gridStyle()
    {
        $option = $this->getSystemColumnOption();
        return $this->getStyleString([
            'min-width' => array_get($option, 'min_width', config('exment.grid_min_width', 100)) . 'px',
            'max-width' => array_get($option, 'max_width', config('exment.grid_max_width', 300)) . 'px',
        ]);
    }

    /**
     * sortable for grid
     */
    public function sortable()
    {
        return true;
    }

    /**
     * set item label
     */
    public function setLabel($label)
    {
        return $this->label = $label;
    }

    public function setCustomValue($custom_value)
    {
        // if options has "view_pivot_column", get select_table's custom_value first
        if (isset($custom_value) && array_key_value_exists('view_pivot_column', $this->options)) {
            $view_pivot_column = $this->options['view_pivot_column'];
            if ($view_pivot_column == SystemColumn::PARENT_ID) {
                $custom_value = $this->custom_table->getValueModel($custom_value->parent_id);
            } else {
                $pivot_custom_column = CustomColumn::getEloquent($this->options['view_pivot_column']);
                $pivot_id =  array_get($custom_value, 'value.'.$pivot_custom_column->column_name);
                $custom_value = $this->custom_table->getValueModel($pivot_id);
            }
        }

        $this->custom_value = $custom_value;
        if (isset($custom_value)) {
            $this->id = array_get($custom_value, 'id');
        }

        $this->prepare();
        
        return $this;
    }

    public function getCustomTable()
    {
        return $this->custom_table;
    }

    protected function getTargetValue($html)
    {
        // if options has "summary" (for summary view)
        if (boolval(array_get($this->options, 'summary'))) {
            return array_get($this->custom_value, $this->sqlAsName());
        }

        if ($html) {
            $option = $this->getSystemColumnOption();
            if (!is_null($keyname = array_get($option, 'tagname'))) {
                return array_get($this->custom_value, $keyname);
            }
        }

        $val = array_get($this->custom_value, $this->column_name);
        return $html ? esc_html($val) : $val;
    }
    
    public function getAdminField($form_column = null, $column_name_prefix = null)
    {
        $field = new Field\Display($this->name(), [$this->label()]);
        $field->default($this->text());

        return $field;
    }
    
    public function getFilterField($value_type = null)
    {
        if (is_null($value_type)) {
            $option = $this->getSystemColumnOption();
            $value_type = array_get($option, 'type');
        }

        switch ($value_type) {
            case 'day':
            case 'datetime':
                $field = new Date($this->name(), [$this->label()]);
                $field->default($this->value);
                break;
            case 'user':
                $field = new Select($this->name(), [$this->label()]);
                $field->options(function ($value) {
                    // get DB option value
                    return CustomTable::getEloquent(SystemTableName::USER)
                        ->getSelectOptions(
                            [
                                'selected_value' => $value,
                                'display_table' => SystemTableName::USER,
                            ]
                        );
                });
                $field->default($this->value);
                break;
            default:
                $field = new Text($this->name(), [$this->label()]);
                $field->default($this->value);
                break;
        }

        return $field;
    }

    /**
     * whether column is date
     *
     */
    public function isDate()
    {
        $option = $this->getSystemColumnOption();
        $value_type = array_get($option, 'type');

        return in_array($value_type, ['day', 'datetime']);
    }

    /**
     * get view filter type
     */
    public function getViewFilterType()
    {
        switch ($this->column_name) {
            case SystemColumn::ID:
            case SystemColumn::SUUID:
            case SystemColumn::PARENT_ID:
                return FilterType::DEFAULT;
            case SystemColumn::CREATED_AT:
            case SystemColumn::UPDATED_AT:
                return FilterType::DAY;
            case SystemColumn::CREATED_USER:
            case SystemColumn::UPDATED_USER:
                return FilterType::USER;
            case SystemColumn::WORKFLOW_STATUS:
                return FilterType::WORKFLOW;
        }
        return FilterType::DEFAULT;
    }

    protected function getSystemColumnOption()
    {
        return SystemColumn::getOption(['name' => $this->column_name]);
    }


    public static function getItem(...$args)
    {
        list($custom_table, $column_name, $custom_value) = $args + [null, null, null];
        return new self($custom_table, $column_name, $custom_value);
    }
}
