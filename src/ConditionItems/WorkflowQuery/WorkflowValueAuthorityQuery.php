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

class WorkflowValueAuthorityQuery extends WorkflowQueryBase
{
    public static function getSubQuery($query, $tableName, $custom_table, $authorities)
    {
        $tableName = getDBTableName($custom_table);
        $userTableName = getDBTableName(SystemTableName::USER);
        $orgPivotName = CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);

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

            /////// last query. has workflow value's custom value and workflow value authorities
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
            ->join(SystemTableName::WORKFLOW_VALUE_AUTHORITY, function ($join) {
                $join->on(SystemTableName::WORKFLOW_VALUE_AUTHORITY . '.workflow_value_id', SystemTableName::WORKFLOW_VALUE . ".id")
                    ;
            })
            ///// add authority function for user or org
            ->where(function ($query) use ($tableName, $custom_table, $class) {
                $class::setConditionQuery($query, $tableName, $custom_table, SystemTableName::WORKFLOW_VALUE_AUTHORITY);
            })
            
            ->distinct()
            ->select([$tableName .'.id as morph_id']);

            $subqueries[] = $subquery;
        }
        
        return $subqueries;
    }
}
