<?php

namespace Exceedone\Exment\Tests\Unit;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Enums\SystemTableName;

class UserOrganizationTest extends UnitTestBase
{
    const ALL_USER_COUNT = 11;
    const ALL_ORGANIZATION_COUNT = 9;

    /**
     * Get user table data
     *   login user : administrator
     *   filter : no
     */
    public function testFuncGetUserAdmin1()
    {
        $array = $this->getData(1, 0, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }

    /**
     * Get user table data
     *   login user : administrator
     *   filter : yes
     */
    public function testFuncGetUserAdmin2()
    {
        $array = $this->getData(1, 1, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }
    
    /**
     * Get user table data
     *   login user : not belong organization
     *   filter : no
     */
    public function testFuncGetUserNoOrganization1()
    {
        $array = $this->getData(2, 0, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }
    
    /**
     * Get user table data
     *   login user : not belong organization
     *   filter : yes
     */
    public function testFuncGetUserNoOrganization2()
    {
        $array = $this->getData(2, 1, SystemTableName::USER);

        $this->assertTrue($array->count() == 1);
    }

    /**
     * Get user table data
     *   login user : belong one organization (solo)
     *   filter : no
     */
    public function testFuncGetUserSoloOrganization1()
    {
        $array = $this->getData(5, 0, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }
    
    /**
     * Get user table data
     *   login user : belong one organization (solo)
     *   filter : yes
     */
    public function testFuncGetUserSoloOrganization2()
    {
        $array = $this->getData(5, 1, SystemTableName::USER);

        $this->assertTrue($array->count() == 1);
    }

    /**
     * Get user table data
     *   login user : belong one organization (multiple user)
     *   filter : no
     */
    public function testFuncGetUserOrganization1()
    {
        $array = $this->getData(7, 0, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }
    
    /**
     * Get user table data
     *   login user : belong one organization (multiple user)
     *   filter : yes
     */
    public function testFuncGetUserOrganization2()
    {
        $array = $this->getData(7, 1, SystemTableName::USER);

        $this->assertTrue($array->count() == 2);
    }

    /**
     * Get user table data
     *   login user : belong multiple organization
     *   filter : no
     */
    public function testFuncGetUserMultiOrganization1()
    {
        $array = $this->getData(11, 0, SystemTableName::USER);

        $this->assertTrue($array->count() == static::ALL_USER_COUNT);
    }
    
    /**
     * Get user table data
     *   login user : belong multiple organization
     *   filter : yes
     */
    public function testFuncGetUserMultiOrganization2()
    {
        $array = $this->getData(11, 1, SystemTableName::USER);

        $this->assertTrue($array->count() == 1);
    }
    /**
     * Get organization table data
     *   login user : administrator
     *   filter : no
     */
    public function testFuncGetOrganizationAdmin1()
    {
        $array = $this->getData(1, 0, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }

    /**
     * Get organization table data
     *   login user : administrator
     *   filter : yes
     */
    public function testFuncGetOrganizationAdmin2()
    {
        $array = $this->getData(1, 1, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }
    
    /**
     * Get organization table data
     *   login user : not belong organization
     *   filter : no
     */
    public function testFuncGetOrganizationNoBelong1()
    {
        $array = $this->getData(2, 0, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }
    
    /**
     * Get organization table data
     *   login user : not belong organization
     *   filter : yes
     */
    public function testFuncGetOrganizationNoBelong2()
    {
        $array = $this->getData(2, 1, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == 0);
    }

    /**
     * Get organization table data
     *   login user : belong one organization (solo)
     *   filter : no
     */
    public function testFuncGetOrganizationSolo1()
    {
        $array = $this->getData(5, 0, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }
    
    /**
     * Get organization table data
     *   login user : belong one organization (solo)
     *   filter : yes
     */
    public function testFuncGetOrganizationSolo2()
    {
        $array = $this->getData(5, 1, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == 1);
    }

    /**
     * Get organization table data
     *   login user : belong one organization (multiple user)
     *   filter : no
     */
    public function testFuncGetOrganizationOne1()
    {
        $array = $this->getData(7, 0, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }
    
    /**
     * Get organization table data
     *   login user : belong one organization (multiple user)
     *   filter : yes
     */
    public function testFuncGetOrganizationOne2()
    {
        $array = $this->getData(7, 1, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == 1);
    }

    /**
     * Get organization table data
     *   login user : belong multiple organization
     *   filter : no
     */
    public function testFuncGetOrganizationMulti1()
    {
        $array = $this->getData(11, 0, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == static::ALL_ORGANIZATION_COUNT);
    }
    
    /**
     * Get organization table data
     *   login user : belong multiple organization
     *   filter : yes
     */
    public function testFuncGetOrganizationMulti2()
    {
        $array = $this->getData(11, 1, SystemTableName::ORGANIZATION);

        $this->assertTrue($array->count() == 2);
    }

    protected function getData($loginId, $joinedOrgFilterType, $table_name){
        $this->be(LoginUser::find($loginId));
        System::filter_joined_organization($joinedOrgFilterType);
        
        return CustomTable::getEloquent($table_name)->getValueModel()->get();
    }
}
