<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

class AddOptionsToFilters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('workflow_condition_headers') && !Schema::hasColumn('workflow_condition_headers', 'options')){
            Schema::table('workflow_condition_headers', function (Blueprint $table) {
                $table->json('options')->nullable()->after('enabled_flg');
            });
        }

        if(Schema::hasTable('custom_form_priorities') && !Schema::hasColumn('custom_form_priorities', 'options')){
            Schema::table('custom_form_priorities', function (Blueprint $table) {
                $table->json('options')->nullable()->after('order');
            });
        }

        if(Schema::hasTable('revisions')){
            Schema::table('revisions', function (Blueprint $table) {
                if(!Schema::hasColumn('revisions', 'deleted_at')){
                    $table->timestamp('deleted_at', 0)->nullable()->after('updated_at');
                }
                if(!Schema::hasColumn('revisions', 'delete_user_id')){
                    $table->unsignedInteger('delete_user_id', 0)->nullable()->after('create_user_id');
                }
            });
        }

        \Artisan::call('exment:patchdata', ['action' => 'parent_org_type']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workflow_condition_headers', function($table) {
            $table->dropColumn('options');
        });
        Schema::table('custom_form_priorities', function($table) {
            $table->dropColumn('options');
        });
        Schema::table('revisions', function($table) {
            $table->dropColumn('deleted_at');
            $table->dropColumn('delete_user_id');
        });
    }
}
