<?php

namespace Exceedone\Exment\ConditionItems;

use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\Condition;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\ConditionTypeDetail;
use Exceedone\Exment\Enums\FilterKind;
use Exceedone\Exment\Enums\FilterOption;
use Exceedone\Exment\Enums\SystemTableName;

class ColumnItem extends ConditionItemBase implements ConditionItemInterface
{
    use ColumnSystemItemTrait;
    
    /**
     * check if custom_value and user(organization, role) match for conditions.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function isMatchCondition(Condition $condition, CustomValue $custom_value)
    {
        $custom_column = CustomColumn::getEloquent($condition->target_column_id);
        $value = array_get($custom_value, 'value.' . $custom_column->column_name);

        return $this->compareValue($condition, $value);
    }
    
    /**
     * get condition value text.
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function getConditionText(Condition $condition)
    {
        $custom_column = CustomColumn::getEloquent($condition->target_column_id);
        
        $column_name = $custom_column->column_name;
        $column_item = $custom_column->column_item;

        $result = $column_item->options([
            'filterKind' => FilterKind::FORM,
        ])->setCustomValue(["value.$column_name" => $condition->condition_value])->text();

        return $result . FilterOption::getConditionKeyText($condition->condition_key);
    }

    /**
     * get text.
     *
     * @param string $key
     * @param string $value
     * @param bool $showFilter
     * @return string
     */
    public function getText($key, $value, $showFilter = true)
    {
        $custom_column = CustomColumn::getEloquent($value);

        return ($custom_column->column_view_name ?? null) . ($showFilter ? FilterOption::getConditionKeyText($key) : '');
    }
    
    /**
     * Get Condition Label
     *
     * @return void
     */
    public function getConditionLabel(Condition $condition)
    {
        $custom_column = CustomColumn::getEloquent($condition->target_column_id);
        return $custom_column->column_view_name ?? null;
    }

    /**
     * Check has workflow authority
     *
     * @param CustomValue $custom_value
     * @return boolean
     */
    public function hasAuthority($workflow_authority, $custom_value, $targetUser)
    {
        $custom_column = CustomColumn::find($workflow_authority->related_id);
        if (!ColumnType::isUserOrganization($custom_column->column_type)) {
            return false;
        }
        $auth_values = $this->getAuthValues($custom_value, $custom_column);
        if (is_null($auth_values)) {
            return false;
        }
        $auth_values = (array)$auth_values;

        switch ($custom_column->column_type) {
            case ColumnType::USER:
                return in_array($targetUser->id, $auth_values);
            case ColumnType::ORGANIZATION:
                $ids = $targetUser->belong_organizations->pluck('id')->toArray();
                return collect($auth_values)->contains(function ($auth_value) use ($ids) {
                    return collect($ids)->contains($auth_value);
                });
        }
        return false;
    }

    /**
     * Get Auth values for hasAuthority
     *
     * @param CustomValue $custom_value
     * @param CustomColumn $custom_column
     * @return array|null
     */
    protected function getAuthValues($custom_value, $custom_column){
        return array_get($custom_value, 'value.' . $custom_column->column_name);
    }
    
    /**
     * Set condition query. For data list and use workflow status
     *
     * @param [type] $query
     * @param [type] $tableName
     * @param [type] $custom_table
     * @param string $authorityTableName target table name. WORKFLOW_AUTHORITY or WORKFLOW_VALUE_AUTHORITY
     * @return void
     */
    public static function setConditionQuery($query, $tableName, $custom_table, $authorityTableName = SystemTableName::WORKFLOW_AUTHORITY)
    {
        $custom_table = static::getTargetTableConditionQuery($custom_table);
        $relatedType = static::getRelatedTypeConditionQuery();

        /// get user or organization list
        $custom_columns = CustomColumn::allRecordsCache(function ($custom_column) use ($custom_table) {
            if ($custom_table->id != $custom_column->custom_table_id) {
                return false;
            }
            if (!$custom_column->index_enabled) {
                return false;
            }
            if (!ColumnType::isUserOrganization($custom_column->column_type)) {
                return false;
            }
            return true;
        });

        if(count($custom_columns) == 0){
            $query->whereRaw('1 = 0');
            return;
        }

        $org_ids = \Exment::user()->base_user->belong_organizations->pluck('id')->toArray();
        foreach ($custom_columns as $custom_column) {
            $userIndexName = static::getUserIndexNameConditionQuery($custom_column);
            $orgIndexName = static::getOrgIndexNameConditionQuery($custom_column);
            
            $query->where($authorityTableName . '.related_id', $custom_column->id)
                ->where($authorityTableName . '.related_type', $relatedType);
                
            if ($custom_column->column_type == ColumnType::USER) {
                $query->where($userIndexName, \Exment::user()->id);
            } else {
                $query->whereIn($orgIndexName, $org_ids);
            }
        }
    }

    /**
     * Set Authority Targets
     *
     * @param WorkflowAuthority $workflow_authority
     * @param CustomValue $custom_value
     * @param array $userIds
     * @param array $organizationIds
     * @param array $labels
     * @return void
     */
    public function setAuthorityTargets($workflow_authority, $custom_value, &$userIds, &$organizationIds, &$labels, $options = []){
        $getAsDefine = array_get($options, 'getAsDefine', false);
        
        $custom_column = CustomColumn::getEloquent($workflow_authority->related_id);
        if(!isset($custom_column)){
            return;
        }

        if ($getAsDefine) {
            $labels[] = $custom_column->column_view_name ?? null;
            return;
        }

        $auth_values = $this->getAuthValues($custom_value, $custom_column);
        if (is_null($auth_values)) {
            return;
        }
        $auth_values = (array)$auth_values;

        foreach ($auth_values as $auth_value) {
            if ($custom_column->column_type == ColumnType::USER) {
                $userIds[] = $auth_value;
            } else {
                $organizationIds[] = $auth_value;
            }
        }
    }
    
    protected static function getTargetTableConditionQuery($custom_table){
        return $custom_table;
    }
    
    protected static function getRelatedTypeConditionQuery(){
        return ConditionTypeDetail::COLUMN()->lowerkey();
    }
    
    protected static function getUserIndexNameConditionQuery($custom_column){
        return $custom_column->getIndexColumnName();
    }

    protected static function getOrgIndexNameConditionQuery($custom_column){
        return $custom_column->getIndexColumnName();
    }
}
