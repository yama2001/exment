<?php

namespace Exceedone\Exment\ConditionItems;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\SystemTableName;

/**
 * Column in user Table
 */
class UserTableColumnItem extends ColumnItem
{
    /**
     * Get Auth values for hasAuthority
     *
     * @param CustomValue $custom_value
     * @param CustomColumn $custom_column
     * @return array|null
     */
    protected function getAuthValues($custom_value, $custom_column){
        $user = CustomTable::getEloquent(SystemTableName::USER)->getValueModel($this->getWorkflowCallUser($custom_value));
        if(is_nullorempty($user)){
            return [];
        }
        return array_get($user, 'value.' . $custom_column->column_name);
    }

    /**
     * get Workflow called user id
     *
     * @return void
     */
    protected function getWorkflowCallUser($custom_value){
        $workflow_values = $custom_value->workflow_values;

        foreach($workflow_values as $workflow_value){
            if(is_null($workflow_value->workflow_status_from_id)){
                return $workflow_value->created_user_id;
            }
        }

        // if not contains, return login user
        return \Exment::user()->base_user_id;
    }

    protected static function getTargetTableConditionQuery($custom_table){
        return CustomTable::getEloquent(SystemTableName::USER);
    }
    
    protected static function getRelatedTypeConditionQuery(){
        return ConditionTypeDetail::USERTABLE_COLUMN()->lowerkey();
    }

    protected static function getUserIndexNameConditionQuery($custom_column){
        return 'exm_user.' . $custom_column->getIndexColumnName();
    }

    protected static function getOrgIndexNameConditionQuery($custom_column){
        $orgPivotName = CustomRelation::getRelationNamebyTables(SystemTableName::ORGANIZATION, SystemTableName::USER);
        return "$orgPivotName.parent_id";
    }
}
