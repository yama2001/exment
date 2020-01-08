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

abstract class WorkflowQueryBase
{
    /**
     * Check contains key in $authorities
     *
     * @param [type] $key
     * @param [type] $authorities
     * @return void
     */
    public static function checkAuthorities($key, $authorities)
    {
        return collect($authorities)->contains(function($authority) use($key){
            return array_get((array)$authority, 'related_type') == $key;
        });
    }

    abstract public static function getSubQuery($query, $tableName, $custom_table, $authorities, $options = []);
}
