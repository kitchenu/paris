<?php

use Idiorm\ORM;
use Paris\Model;

class MultipleConnectionsTest extends PHPUnit_Framework_TestCase {

    const ALTERNATE = 'alternate';

    public function setUp() {

        // Set up the dummy database connection
        ORM::setDb(new MockPDO('sqlite::memory:'));
        ORM::setDb(new MockDifferentPDO('sqlite::memory:'), self::ALTERNATE);

        // Enable logging
        ORM::configure('logging', true);
        ORM::configure('logging', true, self::ALTERNATE);
    }

    public function tearDown() {
        ORM::configure('logging', false);
        ORM::configure('logging', false, self::ALTERNATE);

        ORM::setDb(null);
        ORM::setDb(null, self::ALTERNATE);
    }

    public function testMultipleConnections() {
        $simple = Model::factory('Simple')->findOne(1);
        $statement = ORM::getLastStatement();
        $this->assertInstanceOf('MockPDOStatement', $statement);

        $simple = Model::factory('Simple', self::ALTERNATE); // Change the object's default connection
        $simple->findOne(1);
        $statement = ORM::getLastStatement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);

        $temp = Model::factory('Simple', self::ALTERNATE)->findOne(1);
        $statement = ORM::getLastStatement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);
    }

    public function testCustomConnectionName() {
        $person3 = Model::factory('ModelWithCustomConnection')->findOne(1);
        $statement = ORM::getLastStatement();
        $this->assertInstanceOf('MockDifferentPDOStatement', $statement);
    }

}