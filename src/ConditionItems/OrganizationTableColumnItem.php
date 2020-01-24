<?php

namespace Exceedone\Exment\ConditionItems;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\SystemTableName;

/**
 * Column in organization Table
 */
class OrganizationTableColumnItem extends UserTableColumnItem
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
        if(!\is_nullorempty($user)){
            return [];
        }
        
        $orgs = $user->belong_organizations;
        if(!\is_nullorempty($orgs)){
            return [];
        }

        return collect($orgs)->map(function($org) use($custom_column){
            return array_get($org, 'value.' . $custom_column->column_name);
        })->toArray();
    }
}
