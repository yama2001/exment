<?php

namespace Exceedone\Exment\ConditionItems\WorkflowQuery;

use Encore\Admin\Form\Field\Select;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomRelation;

class WorkflowValueQuery
{
    public static function getSubQuery($query, $tableName, $custom_table)
    {
        $tableName = getDBTableName($custom_table);
        $userTableName = getDBTableName(SystemTableName::USER);
        $orgPivotName = CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);

        $subqueries = [];
        $classes = [
            \Exceedone\Exment\ConditionItems\UserItem::class,
            \Exceedone\Exment\ConditionItems\OrganizationItem::class,
            \Exceedone\Exment\ConditionItems\ColumnItem::class,
            \Exceedone\Exment\ConditionItems\SystemItem::class,
        ];

        foreach ($classes as $class) {
            /////// second query. has workflow value's custom value
            $subquery = \DB::table($tableName)
            ->join(SystemTableName::WORKFLOW_VALUE, function ($join) use ($tableName, $custom_table) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', $tableName .'.id')
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                    ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', 1);
            })
            ->join(SystemTableName::WORKFLOW_TABLE, function ($join) use ($tableName, $custom_table) {
                $join->where(SystemTableName::WORKFLOW_TABLE . '.custom_table_id', $custom_table->id)
                    ->where(SystemTableName::WORKFLOW_TABLE . '.active_flg', 1)
                    ;
            })
            ->join(SystemTableName::WORKFLOW, function ($join) {
                $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ->on(SystemTableName::WORKFLOW_VALUE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                    ;
            })
            ->join(SystemTableName::WORKFLOW_ACTION, function ($join) {
                $join
                ->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                ->where('ignore_work', false)
                ->where(function ($query) {
                    $query->where(function ($query) {
                        $query->where(SystemTableName::WORKFLOW_ACTION . '.status_from', Define::WORKFLOW_START_KEYNAME)
                            ->whereNull(SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id')
                        ;
                    })->orWhere(function ($query) {
                        $query->where(SystemTableName::WORKFLOW_ACTION . '.status_from', \DB::raw(SystemTableName::WORKFLOW_VALUE . '.workflow_status_to_id'))
                        ;
                    });
                });
            })
            ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
                $join->on(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . ".id")
                    ;
            })
            ///// add authority function for user or org
            ->where(function ($query) use ($tableName, $custom_table, $class) {
                $class::setConditionQuery($query, $tableName, $custom_table);
            })
            ->distinct()
            ->select([$tableName .'.id  as morph_id']);
            
            $subqueries[] = $subquery;
        }
        
        return $subqueries;
    }
}
