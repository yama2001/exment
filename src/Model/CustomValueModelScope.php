<?php

namespace Exceedone\Exment\Model;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\Model\CustomRelation;

class CustomValueModelScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        $table_name = $model->custom_table->table_name;
        $db_table_name = getDBTableName($table_name);
        // get user info
        $user = \Exment::user();
        // if not have, check as login
        if (!isset($user)) {
            // no access role
            //throw new \Exception;
            
            // set no filter. Because when this function called, almost after login or pass oauth authonize.
            // if throw exception, Cannot execute batch.
            return;
        }

        // if system administrator user, return
        if ($user->hasPermission(Permission::SYSTEM)) {
            return;
        // if user can access list, return
        } if ($table_name == SystemTableName::USER) {
            if (System::filter_joined_organization()) {
                // get only login user's organization user
                $builder
                    ->whereExists(function ($builder) use ($user, $db_table_name) {
                        $db_table_name_pivot = CustomRelation::getRelationNameByTables(SystemTableName::ORGANIZATION, SystemTableName::USER);
                        $builder->select(\DB::raw(1))
                            ->from($db_table_name_pivot)
                            ->whereIn("$db_table_name_pivot.parent_id", $user->getOrganizationIds(JoinedOrgFilterType::ONLY_JOIN))
                            ->whereRaw("$db_table_name_pivot.child_id = $db_table_name.id");
                    })
                    ->orWhere('id', $user->base_user_id);
            } else {
                return;
            }
        } elseif ($table_name == SystemTableName::ORGANIZATION) {
            if (System::filter_joined_organization()) {
                // get only login user's organization
                $builder
                    ->where(function ($builder) use ($user) {
                        $builder->whereIn('id', $user->getOrganizationIds(JoinedOrgFilterType::ONLY_JOIN));
                    });
            } else {
                return;
            }
        // Add document skip logic
        } elseif ($table_name == SystemTableName::DOCUMENT) {
            //TODO
            return;
        } elseif ($model->custom_table->hasPermission(Permission::AVAILABLE_ALL_CUSTOM_VALUE)) {
            return;
        }
        // if user has edit or view table
        elseif ($model->custom_table->hasPermission(Permission::AVAILABLE_ACCESS_CUSTOM_VALUE)) {
            // get only has role
            $builder
                ->where(function ($builder) use ($user) {
                    $builder->whereHas('value_authoritable_users', function ($q) use ($user) {
                        $q->where('authoritable_target_id', $user->base_user_id);
                    })->orWhereHas('value_authoritable_organizations', function ($q) use ($user) {
                        $enum = JoinedOrgFilterType::getEnum(System::org_joined_type_custom_value(), JoinedOrgFilterType::ONLY_JOIN);
                        $q->whereIn('authoritable_target_id', $user->getOrganizationIds($enum));
                    });
                });
        }
        // if not role, set always false result.
        else {
            $builder->where('id', '<', 0);
        }
    }
}
