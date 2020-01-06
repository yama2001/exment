<?php

namespace Exceedone\Exment\ConditionItems\WorkflowQuery;

use Encore\Admin\Form\Field\Select;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterType;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Model\Workflow;
use Exceedone\Exment\Model\WorkflowStatus;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomRelation;

class NotHasWorkflowValueQuery extends WorkflowQueryBase
{
    public static function getSubQuery($query, $tableName, $custom_table, $authorities)
    {
        $subqueries = [];
        $classes = [
            ConditionTypeDetail::USER()->lowerKey() => \Exceedone\Exment\ConditionItems\UserItem::class,
            ConditionTypeDetail::ORGANIZATION()->lowerKey() => \Exceedone\Exment\ConditionItems\OrganizationItem::class,
            ConditionTypeDetail::COLUMN()->lowerKey() => \Exceedone\Exment\ConditionItems\ColumnItem::class,
            ConditionTypeDetail::SYSTEM()->lowerKey() => \Exceedone\Exment\ConditionItems\SystemItem::class,
        ];

        foreach ($classes as $key => $class) {
            if(!static::checkAuthorities($key, $authorities)){
                continue;
            }

            /////// first query. not has workflow value's custom value
            $subquery = \DB::table($tableName)
            ->join(SystemTableName::WORKFLOW_TABLE, function ($join) use ($tableName, $custom_table) {
                $join->where(SystemTableName::WORKFLOW_TABLE . '.custom_table_id', $custom_table->id)
                    ->where(SystemTableName::WORKFLOW_TABLE . '.active_flg', 1)
                    ;
            })
            ->join(SystemTableName::WORKFLOW, function ($join) {
                $join->on(SystemTableName::WORKFLOW_TABLE . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                    ;
            })
            ->join(SystemTableName::WORKFLOW_ACTION, function ($join) {
                $join->on(SystemTableName::WORKFLOW_ACTION . '.workflow_id', SystemTableName::WORKFLOW . ".id")
                    ->where(SystemTableName::WORKFLOW_ACTION . '.status_from', Define::WORKFLOW_START_KEYNAME)
                    ;
            })
            ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
                $join->on(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . ".id")
                    ;
            })->whereNotExists(function ($query) use ($tableName, $custom_table) {
                $escapeTableName = \esc_sqlTable($tableName);
                $query->select(\DB::raw(1))
                        ->from(SystemTableName::WORKFLOW_VALUE)
                        ->whereRaw(SystemTableName::WORKFLOW_VALUE . '.morph_id = ' . $escapeTableName .'.id')
                        ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                        ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', 1)
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
