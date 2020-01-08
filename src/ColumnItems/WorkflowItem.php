<?php

namespace Exceedone\Exment\ColumnItems;

use Encore\Admin\Form\Field\Select;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\ConditionItems\WorkflowQuery;

class WorkflowItem extends SystemItem
{
    protected $table_name = 'workflow_values';

    protected static $addStatusSubQuery = false;

    protected static $addWorkUsersSubQuery = false;

    /**
     * whether column is enabled index.
     *
     */
    public function sortable()
    {
        return false;
    }

    /**
     * get sql query column name
     */
    protected function getSqlColumnName()
    {
        // get SystemColumn enum
        $option = SystemColumn::getOption(['name' => $this->column_name]);
        if (!isset($option)) {
            $sqlname = $this->column_name;
        } else {
            $sqlname = array_get($option, 'sqlname');
        }
        return $this->table_name.'.'.$sqlname;
    }

    public static function getItem(...$args)
    {
        list($custom_table, $column_name, $custom_value) = $args + [null, null, null];
        return new self($custom_table, $column_name, $custom_value);
    }

    protected function getTargetValue($html)
    {
        $val = parent::getTargetValue($html);

        if (boolval(array_get($this->options, 'summary'))) {
            if (isset($val)) {
                $model = WorkflowStatus::find($val);
                return array_get($model, 'status_name');
            } else {
                return $this->custom_table->workflow->start_status_name;
            }
        }

        return $val;
    }
    
    public function getFilterField($value_type = null)
    {
        $field = new Select($this->name(), [$this->label()]);

        // get workflow statuses
        $workflow = Workflow::getWorkflowByTable($this->custom_table);
        $options = $workflow->getStatusOptions() ?? [];

        $field->options($options);
        $field->default($this->value);

        return $field;
    }

    /**
     * get
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * create subquery for join
     */
    public static function getStatusSubquery($query, $custom_table)
    {
        if (static::$addStatusSubQuery) {
            return;
        }
        static::$addStatusSubQuery = true;

        $tableName = getDBTableName($custom_table);
        $subquery = \DB::table($tableName)
            ->leftJoin(SystemTableName::WORKFLOW_VALUE, function ($join) use ($tableName, $custom_table) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', "$tableName.id")
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                    ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', true);
            })->select(["$tableName.id as morph_id", 'morph_type', 'workflow_status_from_id', 'workflow_status_to_id']);
            
        $query->joinSub($subquery, 'workflow_values', function ($join) use ($tableName, $custom_table) {
            $join->on($tableName . '.id', 'workflow_values.morph_id');
        });
    }

    /**
     * create subquery for join
     */
    public static function getWorkUsersSubQuery($query, $custom_table)
    {
        if (static::$addWorkUsersSubQuery) {
            return;
        }
        static::$addWorkUsersSubQuery = true;

        // Get all workflow_authorities on workflow table.
        $targetAuthorities = Workflow::getAllAuthorities($custom_table);
        $targetValueAuthorities = Workflow::getAllValueAuthorities($custom_table);

        $tableName = getDBTableName($custom_table);
        $userTableName = getDBTableName(SystemTableName::USER);
        $orgPivotName = CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);

        $queryClasses = [
            ['class' => WorkflowQuery\NotHasWorkflowValueQuery::class, 'authorities' => $targetAuthorities],
            ['class' => WorkflowQuery\WorkflowValueQuery::class, 'authorities' => $targetAuthorities],
            ['class' => WorkflowQuery\WorkflowValueAuthorityQuery::class, 'authorities' => $targetValueAuthorities],
            ['class' => WorkflowQuery\BossUserQuery::class, 'authorities' => $targetAuthorities],
        ];

        $subqueries = [];
        foreach($queryClasses as $queryClass){
            $subqueries = array_merge($queryClass['class']::getSubQuery($query, $tableName, $custom_table, $queryClass['authorities']), $subqueries);
        }

        $subquery = $subqueries[0];
        foreach($subqueries as $index => $s){
            if($index == 0){
                continue;
            }

            $subquery->union($s);
        }

        $query->joinSub($subquery, 'workflow_values_wf', function ($join) use ($tableName) {
            $join->on($tableName . '.id', 'workflow_values_wf.morph_id');
        });
    }

    /**
     * set workflow status or work user condition
     */
    public static function scopeWorkflow($query, $view_column_target_id, $custom_table, $condition, $status)
    {
        $enum = SystemColumn::getEnum($view_column_target_id);
        if ($enum == SystemColumn::WORKFLOW_WORK_USERS) {
            //static::scopeWorkflowWorkUsers($query, $custom_table, $condition, $status);
        } else {
            static::scopeWorkflowStatus($query, $custom_table, $condition, $status);
        }
    }

    /**
     * set workflow status condition
     */
    public static function scopeWorkflowStatus($query, $custom_table, $condition, $status)
    {
        ///// Introduction: When the workflow status is "start", one of the following two conditions is required.
        ///// *No value in workflow_values ​​when registering data for the first time
        ///// *When workflow_status_id of workflow_values ​​is null. Ex.Rejection

        // if $status is start
        if ($status == Define::WORKFLOW_START_KEYNAME) {
            $func = ($condition == FilterOption::NE) ? 'whereNotNull' : 'whereNull';
            $query->{$func}('workflow_status_to_id');
        } else {
            $mark = ($condition == FilterOption::NE) ? '<>' : '=';
            $query->where('workflow_status_to_id', $mark, $status);
        }

        return $query;
    }
    
    /**
     * set workflow work users condition
     */
    protected static function scopeWorkflowWorkUsers($query, $custom_table, $condition, $value)
    {
        return $query;
    }
}
