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

class BossUserQuery
{
    public static function getSubQuery($query, $tableName, $custom_table)
    {
        $tableName = getDBTableName($custom_table);
        $userTableName = getDBTableName(SystemTableName::USER);
        $orgPivotName = CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);

        $subqueries = [];
        $classes = [
            \Exceedone\Exment\ConditionItems\UserTableColumnItem::class,
            \Exceedone\Exment\ConditionItems\OrganizationTableColumnItem::class,
        ];

        foreach ($classes as $class) {
                
            /////// third query. has workflow value's custom value dynamic flow
            $subsubquery = \DB::table(SystemTableName::WORKFLOW_VALUE)
            ->leftJoin(SystemTableName::WORKFLOW_VALUE . ' as workflow_values_sub', function ($join) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', SystemTableName::WORKFLOW_VALUE . '_sub.morph_id')
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', SystemTableName::WORKFLOW_VALUE . '_sub.morph_type')
                    ->whereNull(SystemTableName::WORKFLOW_VALUE . '_sub.workflow_status_from_id')
                    ->where(SystemTableName::WORKFLOW_VALUE . '.id', '<', SystemTableName::WORKFLOW_VALUE . '_sub.id');
                })->whereNull(SystemTableName::WORKFLOW_VALUE . '_sub.id')
            ->whereNull(SystemTableName::WORKFLOW_VALUE . '.workflow_status_from_id')
            ->select([SystemTableName::WORKFLOW_VALUE . '.morph_id', SystemTableName::WORKFLOW_VALUE . '.morph_type', SystemTableName::WORKFLOW_VALUE . '.created_user_id AS called_created_user_id']);

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
            /// get action called action user
            ->join(SystemTableName::WORKFLOW_VALUE . ' AS workflow_values_called', function ($join) use ($tableName, $custom_table) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', $tableName .'.id')
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', $custom_table->table_name)
                    ->where(SystemTableName::WORKFLOW_VALUE . '.latest_flg', 1);
            })
            ->joinSub($subsubquery, 'called_workflow_values', function ($join) use ($tableName) {
                $join->on(SystemTableName::WORKFLOW_VALUE . '.morph_id', 'called_workflow_values.morph_id')
                    ->where(SystemTableName::WORKFLOW_VALUE . '.morph_type', \DB::raw('called_workflow_values.morph_type'));
            })
            ->join(SystemTableName::WORKFLOW_AUTHORITY, function ($join) {
                $join->on(SystemTableName::WORKFLOW_AUTHORITY . '.workflow_action_id', SystemTableName::WORKFLOW_ACTION . ".id")
                    ;
            })
            // join user
            ->join("$userTableName AS exm_user", function ($join) {
                $join->on('exm_user.id', 'called_workflow_values.called_created_user_id');
            })
            // leftjoin org
            ->leftJoin("$orgPivotName AS exm_pivot_org", function ($join) {
                $join->on('called_workflow_values.called_created_user_id', 'exm_pivot_org.child_id');
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
