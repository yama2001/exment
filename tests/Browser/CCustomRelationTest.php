<?php

namespace Exceedone\Exment\Tests\Browser;

use Exceedone\Exment\Tests\ExmentKitTestCase;
use Exceedone\Exment\Tests\ExmentKitPrepareTrait;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomRelation;

class CCustomRelationTest extends ExmentKitTestCase
{   
    use ExmentKitPrepareTrait;

    /**
     * pre-excecute process before test.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->login();
    }

    /**
     * prepare test table.
     */
    public function testPrepareTestTable() {
        $this->createCustomTable('ntq_contract', 1, 1);
        $this->createCustomTable('ntq_contract_relation', 1, 1);
    }

    /**
     * Check custom relation display.
     */
    public function testDisplayRelationSetting()
    {
        // Check custom column form
        $this->visit('/admin/relation/ntq_contract')
                ->seePageIs('/admin/relation/ntq_contract')
                ->see('関連テーブル設定')
                ->seeInElement('th', '親テーブル')
                ->seeInElement('th', '子テーブル')
                ->seeInElement('th', 'リレーション種類')
                ->seeInElement('th', '操作')
                ->visit('/admin/relation/ntq_contract/create')
                ->seeInElement('h1', '関連テーブル設定')
                ->seeInElement('h3[class=box-title]', '作成')
                ->seeInElement('label', '親テーブル')
                ->seeInElement('label', '子テーブル')
                ->seeInElement('label', 'リレーション種類')
            ;
    }

    /**
     * Create & edit custom relation --one to many--.
     */
    public function testAddRelationOneToManySuccess()
    {
        $row = CustomTable::where('table_name', 'ntq_contract_relation')->first();
        $child_id = array_get($row, 'id');

        $pre_cnt = CustomRelation::count();

        // Create custom relation
        $this->visit('/admin/relation/ntq_contract/create')
                ->select($child_id, 'child_custom_table_id')
                ->select('1', 'relation_type')
                ->press('送信')
                ->seePageIs('/admin/relation/ntq_contract')
                ->seeInElement('td', 'NTQ Contract Relation')
                ->seeInElement('td', '1対多')
                ->assertEquals($pre_cnt + 1, CustomRelation::count())
                ;

        $row = CustomRelation::orderBy('created_at', 'desc')->first();
        $id = array_get($row, 'id');

        // Edit custom relation
        $this->visit('/admin/relation/ntq_contract/'. $id . '/edit')
                ->seeIsSelected('child_custom_table_id', $child_id)
                ->seeIsSelected('relation_type', '1')
                ->select('2', 'relation_type')
                ->press('送信')
                ->seePageIs('/admin/relation/ntq_contract')
                ->seeInElement('td', 'NTQ Contract Relation')
                ->seeInElement('td', '多対多')
                ;
    }

    /**
     * Create custom relation --many to many--.
     */
    public function testAddRelationManyToManySuccess()
    {
        $row = CustomTable::where('table_name', 'user')->first();
        $child_id = array_get($row, 'id');

        $pre_cnt = CustomRelation::count();

        // Create custom relation
        $this->visit('/admin/relation/ntq_contract/create')
                ->select($child_id, 'child_custom_table_id')
                ->select('2', 'relation_type')
                ->press('送信')
                ->seePageIs('/admin/relation/ntq_contract')
                ->seeInElement('td', 'ユーザー')
                ->seeInElement('td', '多対多')
                ->assertEquals($pre_cnt + 1, CustomRelation::count())
        ;

        $row = CustomRelation::orderBy('created_at', 'desc')->first();
        $id = array_get($row, 'id');

        // Check custom relation
        $this->visit('/admin/relation/ntq_contract/'. $id . '/edit')
                ->seeIsSelected('child_custom_table_id', $child_id)
                ->seeIsSelected('relation_type', '2')
        ;
    }

    /**
     * Drop custom relation.
     */
    public function testDropOneLineTextColumn()
    {
        $table_id = CustomTable::where('table_name', 'ntq_contract')->first()->id;
        $row = CustomRelation::where('parent_custom_table_id', $table_id)->first();

        $pre_cnt = CustomRelation::count();

        if ($row) {
            // Delete custom relation
            $this->delete('/admin/relation/ntq_contract/'. $row->id)
                ->assertEquals($pre_cnt - 1, CustomRelation::count())
            ;
        }
    }

//     /**
//      * A Dusk test example.
//      *
//      * @return void
//      */
//     // precondition : login success
//     public function testLoginSuccessWithTrueUsername()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/auth/login')
//                 ->type('username', 'testuser')
//                 ->type('password', 'test123456')
//                 ->press('Login')
//                 ->waitForText('Login successful')
//                 ->assertPathIs('/admin')
//                 ->assertTitle('Dashboard')
//                 ->assertSee('Dashboard');
//         });
//     }

//     // AutoTest_Relation_01
//     public function testCreateTableParentSuccess()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/table')
//                 ->waitForText('New')
//                 ->clickLink('New')
//                 ->pause(5000)
//                 ->type('table_name', 'ntq_contract')
//                 ->type('table_view_name', 'NTQ Contract')
//                 ->type('description', 'NTQ Test relation table')
//                 ->type('color', '#ff0000')
//                 ->type('icon', 'fa-automobile')
//                 ->click('.fa.fa-automobile');
//             $browser->script('document.querySelector(".search_enabled.la_checkbox").click();');
//             $browser->script('document.querySelector(".one_record_flg.la_checkbox").click();');
//             $browser->press('Submit')
//                 ->pause(5000)
//                 ->assertMissing('.has-error')
//                 ->assertPathIs('/admin/table')
//                 ->assertSee('ntq_contract')
//                 ->assertSee('NTQ Contract');
//         });
//     }

//     // AutoTest_Relation_02
//     public function testCreateChildSuccess()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/table')
//                 ->waitForText('New')
//                 ->clickLink('New')
//                 ->pause(5000)
//                 ->type('table_name', 'ntq_contract_relation')
//                 ->type('table_view_name', 'NTQ Contract Relation')
//                 ->type('description', 'NTQ Test relation table')
//                 ->type('color', '#ff0000')
//                 ->type('icon', 'fa-automobile')
//                 ->click('.fa.fa-automobile');
//             $browser->script('document.querySelector(".search_enabled.la_checkbox").click();');
//             $browser->script('document.querySelector(".one_record_flg.la_checkbox").click();');
//             $browser->press('Submit')
//                 ->pause(5000)
//                 ->assertMissing('.has-error')
//                 ->assertPathIs('/admin/table')
//                 ->assertSee('ntq_contract_relation')
//                 ->assertSee('NTQ Contract Relation');
//         });
//     }

//     // AutoTest_Relation_03
//     public function testDisplayRelationSetting()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/table')
//                 ->assertSee('NTQ Contract');
//             $browser->script('$(".table-hover td").filter(function(){return $.trim($(this).text()) == "NTQ Contract"}).closest("tr").find("ins.iCheck-helper").click();');
//             $browser->press('Change Page')
//                 ->clickLink('Relation Setting')
//                 ->pause(5000)
//                 ->assertSee('Custom Relation Setting')
//                 ->assertSee('Define relations with table and table.')
//                 ->assertSee('Showing to of 0 entries')
//                 ->assertPathIs('/admin/relation/ntq_contract');
//         });
//     }

//     // AutoTest_Relation_04
//     public function testDisplayCreateRelationScreen()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract')
//                 ->waitForText('New')
//                 ->clickLink('New')
//                 ->pause(5000)
//                 ->assertPathIs('/admin/relation/ntq_contract/create')
//                 ->assertSeeIn('.box-title', 'Create')
//                 ->assertSee('NTQ Contract ')
//                 ->assertSee('Child Table')
//                 ->assertSee('Relation Type');
//         });
//     }

//     // AutoTest_Relation_05
//     public function testAddRelationOneToManySuccess()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract/create')
//                 ->pause(5000);
//             $browser->script('$(".child_custom_table_id").val($("option").filter(function() {
//   return $(this).text() === "NTQ Contract Relation";
// }).first().attr("value")).trigger("change.select2")');
//             $browser->select('relation_type', 'one_to_many')
//                 ->press('Submit')
//                 ->waitForText('Save succeeded !')
//                 ->assertSeeIn('.table-hover tr:last-child td:nth-child(5)', 'NTQ Contract Relation')
//                 ->assertSeeIn('.table-hover tr:last-child td:nth-child(6)', 'One to Many')
//                 ->assertPathIs('/admin/relation/ntq_contract');
//         });
//     }

//     // AutoTest_Relation_06
//     public function testVerifyRelationOneToMany()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract')
//                 ->assertSee('NTQ Contract Relation');
//             $browser->script('$(".table-hover td").filter(function(){return $.trim($(this).text()) == "NTQ Contract Relation"}).closest("tr").click();');
//             $browser->pause(5000)
//                 ->assertSee('NTQ Contract')
//                 ->assertSee('NTQ Contract Relation')
//                 ->assertSelected('relation_type', 'one_to_many');
//         });
//     }

//     // AutoTest_Relation_07
//     public function testAddRelationManyToManySuccess()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract/create')
//                 ->pause(5000);
//             $browser->script('$(".child_custom_table_id").val($("option").filter(function() {
//   return $(this).text() === "User";
// }).first().attr("value")).trigger("change.select2")');
//             $browser->select('relation_type', 'many_to_many')
//                 ->press('Submit')
//                 ->waitForText('Save succeeded !')
//                 ->assertSeeIn('.table-hover tr:last-child td:nth-child(5)', 'User')
//                 ->assertSeeIn('.table-hover tr:last-child td:nth-child(6)', 'Many to Many')
//                 ->assertPathIs('/admin/relation/ntq_contract');
//         });
//     }

//     // AutoTest_Relation_08
//     public function testVerifyRelationManyToMany()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract')
//                 ->assertSee('NTQ Contract Relation');
//             $browser->script('$(".table-hover td").filter(function(){return $.trim($(this).text()) == "User"}).closest("tr").click();');
//             $browser->pause(5000)
//                 ->assertSee('NTQ Contract')
//                 ->assertSee('User')
//                 ->assertSelected('relation_type', 'many_to_many');
//         });
//     }

//     // AutoTest_Relation_09
//     public function testEditRelationSuccess()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract')
//                 ->assertSee('NTQ Contract Relation');
//             $browser->script('$(".table-hover td").filter(function(){return $.trim($(this).text()) == "NTQ Contract Relation"}).closest("tr").click();');
//             $browser->pause(5000)
//                 ->assertSee('NTQ Contract')
//                 ->select('relation_type', 'many_to_many')
//                 ->press('Submit')
//                 ->waitForText('Save succeeded !')
//                 ->assertSeeIn('.table-hover tr:first-child td:nth-child(5)', 'NTQ Contract Relation')
//                 ->assertSeeIn('.table-hover tr:first-child td:nth-child(6)', 'Many to Many');
//         });
//     }

//     // AutoTest_Relation_10
//     public function testDropOneLineTextColumn()
//     {
//         $this->browse(function (Browser $browser) {
//             $browser->visit('/admin/relation/ntq_contract')
//                 ->assertSee('NTQ Contract Relation');
//             $browser->script('$(".table-hover td").filter(function(){return $.trim($(this).text()) == "NTQ Contract Relation"}).closest("tr").find("a.grid-row-delete").click();');
//             $browser->pause(5000)
//                 ->press('Confirm')
//                 ->waitForText('Delete succeeded !')
//                 ->press('OK')
//                 ->pause(2000)
//                 ->assertDontSee('NTQ Contract Relation')
//                 ->assertPathIs('/admin/relation/ntq_contract');
//         });
//     }
}
