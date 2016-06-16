<?php

use Idiorm\ORM;
use Paris\Model;

class ModelPrefixingTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        // Set up the dummy database connection
        ORM::setDb(new MockPDO('sqlite::memory:'));

        // Enable logging
        ORM::configure('logging', true);
        
        Model::$auto_prefix_models = null;
    }

    public function tearDown() {
        ORM::configure('logging', false);
        ORM::setDb(null);

        Model::$auto_prefix_models = null;
    }

    public function testStaticPropertyExists() {
        $this->assertClassHasStaticAttribute('auto_prefix_models', 'Paris\Model');
        $this->assertInternalType('null', Model::$auto_prefix_models);
    }

    public function testSettingAndUnsettingStaticPropertyValue() {
        $model_prefix = 'My_Model_Prefix_';
        $this->assertInternalType('null', Model::$auto_prefix_models);
        Model::$auto_prefix_models = $model_prefix;
        $this->assertInternalType('string', Model::$auto_prefix_models);
        $this->assertEquals($model_prefix, Model::$auto_prefix_models);
        Model::$auto_prefix_models = null;
        $this->assertInternalType('null', Model::$auto_prefix_models);
    }

    public function testNoPrefixOnAutoTableName() {
        Model::$auto_prefix_models = null;
        Model::factory('Simple')->findMany();
        $expected = 'SELECT * FROM `simple`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testPrefixOnAutoTableName() {
        Model::$auto_prefix_models = 'MockPrefix_';
        Model::factory('Simple')->findMany();
        $expected = 'SELECT * FROM `mock_prefix_simple`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }

    public function testPrefixOnAutoTableNameWithTableSpecified() {
        Model::$auto_prefix_models = 'MockPrefix_';
        Model::factory('TableSpecified')->findMany();
        $expected = 'SELECT * FROM `simple`';
        $this->assertEquals($expected, ORM::getLastQuery());
    }
    
}