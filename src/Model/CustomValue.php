<?php

namespace Exceedone\Exment\Model;
use Encore\Admin\Facades\Admin;

class CustomValue extends ModelBase
{        
    use Traits\CustomValueTrait;
    use Traits\AutoSUuidTrait;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $casts = ['value' => 'json'];

    /**
     * remove_file_columns.
     * default flow, if file column is empty, set original value.
     */
    protected $remove_file_columns = [];

    // user value_authoritable. it's all authority data. only filter  morph_type
    public function value_authoritable_users(){
        return $this->morphToMany(getModelName(Define::SYSTEM_TABLE_NAME_USER), 'morph', 'value_authoritable', 'morph_id', 'related_id')
            ->withPivot('related_id', 'related_type')
            ->wherePivot('related_type', Define::SYSTEM_TABLE_NAME_USER)
            ;
    }

    // user value_authoritable. it's all authority data. only filter  morph_type
    public function value_authoritable_organizations(){
        return $this->morphToMany(getModelName(Define::SYSTEM_TABLE_NAME_ORGANIZATION), 'morph', 'value_authoritable', 'morph_id', 'related_id')
            ->withPivot('related_id', 'related_type')
            ->wherePivot('related_type', Define::SYSTEM_TABLE_NAME_ORGANIZATION)
            ;
    }

    public function getCustomTable(){
        return CustomTable::findByDBTableName($this->getTable());
    }

    public function parent_custom_value(){
        return $this->morphTo();
    }

    /**
     * get Authoritable values.
     * this function selects value_authoritable, and get all values.
     */
    public function getAuthoritable($related_type){
        if($related_type == Define::SYSTEM_TABLE_NAME_USER){
            $query = $this
            ->value_authoritable_users()
            ->where('related_id', Admin::user()->base_user_id);
        }else if($related_type == Define::SYSTEM_TABLE_NAME_ORGANIZATION){
            $query = $this
            ->value_authoritable_organizations()
            ->whereIn('related_id', Admin::user()->getOrganizationIds());
        }

        return $query->get();
    }

    public function getValue($column = null, $label = false){
        return getValue($this, $column, $label);
    }
    public function setValue($key, $val = null){
        if(!isset($key)){return;}
        // if key is array, loop key value
        if(is_array($key)){
            foreach($key as $k => $v){
                $this->setValue($k, $v);
            }
            return $this;
        }
        $value = $this->value;
        if(is_null($value)){$value = [];}
        $value[$key] = $val;
        $this->value = $value;

        return $this;
    }
    
    /**
     * get or set remove_file_columns
     */
    public function remove_file_columns($key = null){
        // get
        if(!isset($key)){
            return $this->remove_file_columns;
        }

        // set
        $this->remove_file_columns[] = $key;
        return $this;
    }

    protected static function boot() {
        parent::boot();

        static::saving(function ($model) {
            // re-get field data --------------------------------------------------
            static::regetOriginalData($model);
        });
        static::saved(function ($model) {
            // set auto format
            static::setAutoNumber($model);
        });
        
        static::deleting(function($model) {
            $parent_table = $model->getCustomTable();
            // delete custom relation is 1:n value
            $relations = CustomRelation
                ::where('parent_custom_table_id', $parent_table->id)
                ->where('relation_type', Define::RELATION_TYPE_ONE_TO_MANY)
                ->get();
            // loop relations
            foreach($relations as $relation){
                $child_table = CustomTable::find($relation->child_custom_table_id);
                // find keys
                getModelName($child_table)
                    ::where('parent_id', $model->id)
                    ->where('parent_type', $parent_table->table_name)
                    ->delete();
            }
        });
    }
}
