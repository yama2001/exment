<?php

namespace Exceedone\Exment\ConditionItems;

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

class WorkflowItem extends SystemItem implements ConditionItemInterface
{
    public function getFilterOption()
    {
        $target = explode('?', $this->target)[0];
        return array_get($this->filterKind == FilterKind::VIEW ? FilterOption::FILTER_OPTIONS() : FilterOption::FILTER_CONDITION_OPTIONS(), $target == SystemColumn::WORKFLOW_STATUS ? FilterType::WORKFLOW : FilterType::WORKFLOW_WORK_USER);
    }
}
