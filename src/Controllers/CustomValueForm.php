<?php

namespace Exceedone\Exment\Controllers;

use Symfony\Component\HttpFoundation\Response;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Form\Field;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Enums\SearchType;
use Exceedone\Exment\Enums\RelationType;
use Exceedone\Exment\Enums\FormBlockType;
use Exceedone\Exment\Enums\FormColumnType;
use Exceedone\Exment\Services\PartialCrudService;

trait CustomValueForm
{
    /**
     * Make a form builder.
     * @param $id if edit mode, set model id
     * @return Form
     */
    protected function form($id = null)
    {
        $request = request();
        $this->setFormViewInfo($request);
        
        $custom_form = $this->custom_table->getPriorityForm($id);
        if (isset($custom_form)) {
            $this->custom_form =$custom_form;
        }

        $classname = getModelName($this->custom_table);
        $form = new Form(new $classname);

        if (isset($id)) {
            $form->systemValues()->setWidth(12, 0);
        }

        // get select_parent
        $select_parent = null;
        if ($request->has('select_parent')) {
            $select_parent = intval($request->get('select_parent'));
        }

        //TODO: escape laravel-admin bug.
        //https://github.com/z-song/laravel-admin/issues/1998
        $form->hidden('laravel_admin_escape');
        $form->hidden('select_parent')->default($select_parent);

        // add parent select if this form is 1:n relation
        $relation = CustomRelation::getRelationByChild($this->custom_table, RelationType::ONE_TO_MANY);
        if (isset($relation)) {
            $parent_custom_table = $relation->parent_custom_table;
            $form->hidden('parent_type')->default($parent_custom_table->table_name);

            // if create data and not has $select_parent, select item
            if (!isset($id) && !isset($select_parent)) {
                $select = $form->select('parent_id', $parent_custom_table->table_view_name)
                ->options(function ($value) use ($parent_custom_table) {
                    return $parent_custom_table->getSelectOptions([
                        'selected_value' => $value,
                        'showMessage_ifDeny' => true,
                    ]);
                });
                $select->required();

                // set select options
                $select->ajax($parent_custom_table->getOptionAjaxUrl());
            }
            // if edit data or has $select_parent, only display
            else {
                if ($request->has('parent_id')) {
                    $parent_id = $request->get('parent_id');
                } else {
                    $parent_id = isset($select_parent) ? $select_parent : $classname::find($id)->parent_id;
                }
                $parent_value = $parent_custom_table->getValueModel($parent_id);

                if (isset($parent_id) && isset($parent_value) && isset($parent_custom_table)) {
                    $form->hidden('parent_id')->default($parent_id);
                    $form->display('parent_id_display', $parent_custom_table->table_view_name)->default($parent_value->label);
                }
            }
        }

        $calc_formula_array = [];
        $changedata_array = [];
        $relatedlinkage_array = [];
        $count_detail_array = [];
        $this->setCustomFormEvents($calc_formula_array, $changedata_array, $relatedlinkage_array, $count_detail_array);

        // loop for custom form blocks
        foreach ($this->custom_form->custom_form_blocks as $custom_form_block) {
            // if available is false, continue
            if (!$custom_form_block->available) {
                continue;
            }
            // when default block, set as normal form columns.
            if ($custom_form_block->form_block_type == FormBlockType::DEFAULT) {
                $form->embeds('value', exmtrans("common.input"), $this->getCustomFormColumns($form, $custom_form_block, $id))
                    ->disableHeader();
            }
            // one_to_many or manytomany
            else {
                list($relation_name, $block_label) = $this->getRelationName($custom_form_block);
                $target_table = $custom_form_block->target_table;
                // 1:n
                if ($custom_form_block->form_block_type == FormBlockType::ONE_TO_MANY) {
                    // get form columns count
                    $form_block_options = array_get($custom_form_block, 'options', []);
                    // if form_block_options.hasmany_type is 1, hasmanytable
                    if (boolval(array_get($form_block_options, 'hasmany_type'))) {
                        $hasmany = $form->hasManyTable(
                            $relation_name,
                            $block_label,
                            function ($form) use ($custom_form_block, $id) {
                                $form->nestedEmbeds('value', $this->custom_form->form_view_name, function (Form\EmbeddedForm $form) use ($custom_form_block, $id) {
                                    $this->setCustomFormColumns($form, $custom_form_block, $id);
                                });
                            }
                        )->setTableWidth(12, 0);
                    }
                    // default,hasmany
                    else {
                        $hasmany = $form->hasMany(
                            $relation_name,
                            $block_label,
                            function ($form, $model = null) use ($custom_form_block, $id) {
                                $form->nestedEmbeds('value', $this->custom_form->form_view_name, $this->getCustomFormColumns($form, $custom_form_block, $model))
                                ->disableHeader();
                            }
                        );
                    }
                    if (array_key_exists($relation_name, $count_detail_array)) {
                        $hasmany->setCountScript(array_get($count_detail_array, $relation_name));
                    }
                }
                // n:n
                else {
                    // get select classname
                    $isListbox = $target_table->isGetOptions();
                    if ($isListbox) {
                        $class = Field\Listbox::class;
                    } else {
                        $class = Field\MultipleSelect::class;
                    }

                    $field = new $class(
                        CustomRelation::getRelationNameByTables($this->custom_table, $target_table),
                        [$custom_form_block->target_table->table_view_name]
                    );
                    $custom_table = $this->custom_table;
                    $field->options(function ($select) use ($custom_table, $target_table, $isListbox) {
                        return $target_table->getSelectOptions(
                            [
                                'selected_value' => $select,
                                'display_table' => $custom_table,
                                'notAjax' => $isListbox,
                            ]
                        );
                    });
                    if (!$isListbox) {
                        $field->ajax($target_table->getOptionAjaxUrl());
                    } else {
                        $field->settings(['nonSelectedListLabel' => exmtrans('common.bootstrap_duallistbox_container.nonSelectedListLabel'), 'selectedListLabel' => exmtrans('common.bootstrap_duallistbox_container.selectedListLabel')]);
                        $field->help(exmtrans('common.bootstrap_duallistbox_container.help'));
                    }
                    $form->pushField($field);
                }
            }
        }

        PartialCrudService::setAdminFormOptions($this->custom_table, $form, $id);

        // add calc_formula_array and changedata_array info
        if (count($calc_formula_array) > 0) {
            $json = json_encode($calc_formula_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setCalcEvent(json);
EOT;
            Admin::script($script);
        }
        if (count($changedata_array) > 0) {
            $json = json_encode($changedata_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setChangedataEvent(json);
EOT;
            Admin::script($script);
        }
        if (count($relatedlinkage_array) > 0) {
            $json = json_encode($relatedlinkage_array);
            $script = <<<EOT
            var json = $json;
            Exment.CommonEvent.setRelatedLinkageEvent(json);
EOT;
            Admin::script($script);
        }

        // ignore select_parent
        $form->ignore('select_parent');

        // add form saving and saved event
        $this->manageFormSaving($form, $id);
        $this->manageFormSaved($form, $id, $select_parent);

        $form->disableReset();

        $custom_table = $this->custom_table;
        $custom_form = $this->custom_form;

        $this->manageFormToolButton($form, $id, $custom_table, $custom_form);
        return $form;
    }

    /**
     * set custom form columns
     */
    protected function setCustomFormColumns($form, $custom_form_block, $id = null)
    {
        $fields = []; // setting fields.
        foreach ($custom_form_block->custom_form_columns as $form_column) {
            // exclusion header and html
            if ($form_column->form_column_type == FormColumnType::OTHER) {
                continue;
            }

            $item = $form_column->column_item;
            if (isset($id)) {
                $item->id($id);
            }
            $form->pushField($item->getAdminField($form_column));
        }
    }

    /**
     * set custom form columns
     */
    protected function getCustomFormColumns($form, $custom_form_block, $custom_value = null)
    {
        $closures = [];
        if (is_numeric($custom_value)) {
            $custom_value = $this->custom_table->getValueModel($custom_value);
        }
        // setting fields.
        foreach ($custom_form_block->custom_form_columns as $form_column) {
            if (!isset($custom_value) && $form_column->form_column_type == FormColumnType::SYSTEM) {
                continue;
            }

            if ($form_column->form_column_type == FormColumnType::OTHER) {
                if (FormColumnType::getOption(['id' => $form_column->form_column_target_id])['view_only'] ?? false) {
                    continue;
                }
            }
            
            if (is_null($form_column->column_item)) {
                continue;
            }

            $field = $form_column->column_item->setCustomValue($custom_value)->getAdminField($form_column);

            // set $closures using $form_column->column_no
            if (isset($field)) {
                $column_no = array_get($form_column, 'column_no');
                $closures[$column_no][] = $field;
            }
        }

        $is_grid = array_key_exists(1, $closures) && array_key_exists(2, $closures);
        return collect($closures)->map(function ($closure, $key) use ($is_grid) {
            return function ($form) use ($closure, $key, $is_grid) {
                foreach ($closure as $field) {
                    if ($is_grid && in_array($key, [1, 2])) {
                        $field->setWidth(8, 3);
                    } else {
                        $field->setWidth(8, 2);
                    }
                    // push field to form
                    $form->pushField($field);
                }
            };
        })->toArray();
    }

    /**
     * set custom form columns
     */
    protected function setCustomFormEvents(&$calc_formula_array, &$changedata_array, &$relatedlinkage_array, &$count_detail_array)
    {
        foreach ($this->custom_form->custom_form_blocks as $custom_form_block) {
            foreach ($custom_form_block->custom_form_columns as $form_column) {
                if ($form_column->form_column_type != FormColumnType::COLUMN) {
                    continue;
                }
                if (!isset($form_column->custom_column)) {
                    continue;
                }
                $column = $form_column->custom_column;
                $form_column_options = $form_column->options;
                $options = $column->options;
                
                // set calc rule for javascript
                if (array_key_value_exists('calc_formula', $options)) {
                    $is_default = $custom_form_block->form_block_type == FormBlockType::DEFAULT;
                    $this->setCalcFormulaArray($column, $options, $calc_formula_array, $count_detail_array, $is_default);
                }
                // data changedata
                // if set form_column_options changedata_target_column_id, and changedata_column_id
                if (array_key_value_exists('changedata_target_column_id', $form_column_options) && array_key_value_exists('changedata_column_id', $form_column_options)) {
                    ///// set changedata info
                    $this->setChangeDataArray($column, $form_column_options, $options, $changedata_array);
                }
            }

            // set relatedlinkage_array
            $this->setRelatedLinkageArray($custom_form_block, $relatedlinkage_array);
        }
    }


    protected function manageFormSaving($form, $id = null)
    {
        // before saving
        $form->saving(function ($form) use ($id) {
            $result = PartialCrudService::saving($this->custom_table, $form, $id);
            if ($result instanceof Response) {
                return $result;
            }
        });
    }

    protected function manageFormSaved($form, $id, $select_parent = null)
    {
        // after saving
        $form->savedInTransaction(function ($form) use ($id, $select_parent) {
            PartialCrudService::saved($this->custom_table, $form, $form->model()->id);
        });
        
        $form->saved(function ($form) use ($select_parent) {
            // if $one_record_flg, redirect
            $one_record_flg = boolval(array_get($this->custom_table->options, 'one_record_flg'));
            if ($one_record_flg) {
                admin_toastr(trans('admin.save_succeeded'));
                return redirect(admin_urls('data', $this->custom_table->table_name));
            } elseif (!empty($select_parent)) {
                admin_toastr(trans('admin.save_succeeded'));
                return redirect(admin_url('data/'.$form->model()->parent_type.'/'. $form->model()->parent_id));
            } elseif (empty(request('after-save'))) {
                admin_toastr(trans('admin.save_succeeded'));
                return redirect($this->custom_table->getGridUrl(true));
            }
        });
    }

    protected function manageFormToolButton($form, $id, $custom_table, $custom_form)
    {
        $form->disableEditingCheck(false);
        $form->disableCreatingCheck(false);
        $form->disableViewCheck(false);
        
        $form->tools(function (Form\Tools $tools) use ($form, $id, $custom_table, $custom_form) {
            // create
            if (!isset($id)) {
                $isButtonCreate = true;
                $listButtons = Plugin::pluginPreparingButton($this->plugins, 'form_menubutton_create');
            }
            // edit
            else {
                $isButtonCreate = false;
                $listButtons = Plugin::pluginPreparingButton($this->plugins, 'form_menubutton_edit');
            }

            $custom_value = $custom_table->getValueModel($id);
            
            $tools->disableView(false);
            $tools->setListPath($custom_table->getGridUrl(true));

            // if one_record_flg, disable list
            if (array_get($custom_table->options, 'one_record_flg')) {
                $tools->disableListButton();
                $tools->disableDelete();
                $tools->disableView();
            }

            // if user only view, disable delete and view
            elseif (!$custom_table->hasPermissionEditData($id)) {
                $tools->disableDelete();
            }

            if (boolval(array_get($custom_value, 'disabled_delete'))) {
                $tools->disableDelete();
            }

            // add plugin button
            if ($listButtons !== null && count($listButtons) > 0) {
                foreach ($listButtons as $listButton) {
                    $tools->append(new Tools\PluginMenuButton($listButton, $this->custom_table));
                }
            }

            PartialCrudService::setAdminFormTools($custom_table, $tools, $id);
            
            $tools->add((new Tools\GridChangePageMenu('data', $custom_table, false))->render());
        });
    }
    
    /**
     * Create calc formula info.
     */
    protected function setCalcFormulaArray($column, $options, &$calc_formula_array, &$count_detail_array, $is_default = true)
    {
        if (is_null($calc_formula_array)) {
            $calc_formula_array = [];
        }
        // get format for calc formula
        $option_calc_formulas = array_get($options, "calc_formula");
        if ($option_calc_formulas == "null") {
            return;
        } //TODO:why???
        if (!is_array($option_calc_formulas) && is_json($option_calc_formulas)) {
            $option_calc_formulas = json_decode($option_calc_formulas, true);
        }

        // keys for calc trigger on display
        $keys = [];
        // loop $option_calc_formulas and get column_name
        foreach ($option_calc_formulas as &$option_calc_formula) {
            $child_table = array_get($option_calc_formula, 'table');
            if (isset($child_table)) {
                $option_calc_formula['relation_name'] = CustomRelation::getRelationNameByTables($this->custom_table, $child_table);
            }
            switch (array_get($option_calc_formula, 'type')) {
                case 'count':
                    if (array_has($option_calc_formula, 'relation_name')) {
                        $relation_name = $option_calc_formula['relation_name'];
                        if (!array_has($count_detail_array, $relation_name)) {
                            $count_detail_array[$relation_name] = [];
                        }
                        $count_detail_array[$relation_name][] =  [
                            'options' => $option_calc_formulas,
                            'to' => $column->column_name,
                            'is_default' => $is_default
                        ];
                    }
                    break;
                case 'dynamic':
                case 'summary':
                case 'select_table':
                    // set column name
                    $formula_column = CustomColumn::getEloquent(array_get($option_calc_formula, 'val'));
                    // get column name as key
                    $key = $formula_column->column_name ?? null;
                    if (!isset($key)) {
                        break;
                    }
                    $keys[] = $key;
                    // set $option_calc_formula val using key
                    $option_calc_formula['val'] = $key;

                    // if select table, set from value
                    if ($option_calc_formula['type'] == 'select_table') {
                        $column_from = CustomColumn::getEloquent(array_get($option_calc_formula, 'from'));
                        $option_calc_formula['from'] = $column_from->column_name ?? null;
                    }
                    break;
            }
        }

        $keys = array_unique($keys);
        // loop for $keys and set $calc_formula_array
        foreach ($keys as $key) {
            // if not exists $key in $calc_formula_array, set as array
            if (!array_has($calc_formula_array, $key)) {
                $calc_formula_array[$key] = [];
            }
            // set $calc_formula_array
            $calc_formula_array[$key][] = [
                'options' => $option_calc_formulas,
                'to' => $column->column_name,
                'is_default' => $is_default
            ];
        }
    }

    /**
     * set change data array.
     * "change data": When selecting a list, paste the value of that item into another form item.
     */
    protected function setChangeDataArray($column, $form_column_options, $options, &$changedata_array)
    {
        // get this table
        $column_table = $column->custom_table;

        // get getting target model name
        $changedata_target_column_id = array_get($form_column_options, 'changedata_target_column_id');
        $changedata_target_column = CustomColumn::getEloquent($changedata_target_column_id);
        if (is_nullorempty($changedata_target_column)) {
            return;
        }

        $changedata_target_table = $changedata_target_column->custom_table;
        if (is_nullorempty($changedata_target_table)) {
            return;
        }

        // get table column. It's that when get model data, copied from column
        $changedata_column_id = array_get($form_column_options, 'changedata_column_id');
        $changedata_column = CustomColumn::getEloquent($changedata_column_id);
        if (is_nullorempty($changedata_column)) {
            return;
        }

        $changedata_table = $changedata_column->custom_table;
        if (is_nullorempty($changedata_table)) {
            return;
        }

        // get select target table
        $select_target_table = $changedata_target_column->select_target_table;
        if (is_nullorempty($select_target_table)) {
            return;
        }

        // if different $column_table and changedata_target_table, get to_target block name using relation
        if ($column_table->id != $changedata_target_table->id) {
            $to_block_name = CustomRelation::getRelationNameByTables($changedata_target_table, $column_table);
        } else {
            $to_block_name = null;
        }

        // if not exists $changedata_target_column->column_name in $changedata_array
        if (!array_has($changedata_array, $changedata_target_column->column_name)) {
            $changedata_array[$changedata_target_column->column_name] = [];
        }
        if (!array_has($changedata_array[$changedata_target_column->column_name], $select_target_table->table_name)) {
            $changedata_array[$changedata_target_column->column_name][$select_target_table->table_name] = [];
        }
        // push changedata column from and to column name
        $changedata_array[$changedata_target_column->column_name][$select_target_table->table_name][] = [
            'from' => $changedata_column->column_name, // target_table's column
            'to' => $column->column_name, // set data
            'to_block' => is_null($to_block_name) ? null : '.has-many-' . $to_block_name . ',.has-many-table-' . $to_block_name,
            'to_block_form' => is_null($to_block_name) ? null : '.has-many-' . $to_block_name . '-form,.has-many-table-' . $to_block_name.'-form',
        ];
    }
    
    /**
     * set related linkage array.
     * "related linkage": When selecting a value, change the choices of other list. It's for 1:n relation.
     */
    protected function setRelatedLinkageArray($custom_form_block, &$relatedlinkage_array)
    {
        // if available is false, continue
        if (!$custom_form_block->available || !isset($custom_form_block->target_table)) {
            return;
        }

        // get relation columns
        $relationColumns = $custom_form_block->target_table->getSelectTableRelationColumns();

        foreach ($relationColumns as $relationColumn) {
            // ignore n:n
            if ($relationColumn['searchType'] == SearchType::MANY_TO_MANY) {
                continue;
            }

            $parent_column = $relationColumn['parent_column'];
            $parent_column_name = array_get($parent_column, 'column_name');
            $parent_table = $parent_column->select_target_table;

            $child_column = $relationColumn['child_column'];
            $child_table = $child_column->select_target_table;
                    
            // skip same table
            if ($parent_table->id == $child_table->id) {
                continue;
            }

            // if not exists $column_name in $relatedlinkage_array
            if (!array_has($relatedlinkage_array, $parent_column_name)) {
                $relatedlinkage_array[$parent_column_name] = [];
            }

            // add array. key is column name.
            $relatedlinkage_array[$parent_column_name][] = [
                'url' => admin_urls('webapi', 'data', $parent_table->table_name ?? null, 'relatedLinkage'),
                'expand' => [
                    'child_table_id' => $child_table->id ?? null,
                    'search_type' => array_get($relationColumn, 'searchType'),
                ],
                'to' => array_get($child_column, 'column_name'),
            ];
        }
    }
}
