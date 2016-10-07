<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Config;

require_once __DIR__ . '/../src/Config/Config.php';
require_once __DIR__ . '/../src/Config/EmptyParser.php';

/**
 * Class Test for cache
 *
 * @author Romain Cottard
 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test Config
     *
     * @return   void
     * @covers Config::__construct
     * @covers Config::getInstance
     * @covers Config::add
     * @covers Config::addRecursive
     * @covers Config::get
     * @covers Config::getRecursive
     */
    public function textConfig()
    {
        $dbConfig = array(
            'driver' => 'mysql', 'params' => array('user' => 'testuser', 'pass' => 'testpass', 'host' => 'localhost'),
        );

        $config = Config::getInstance();

        $config->add('database', $dbConfig);

        $this->assertEquals($config->get('database.driver'), 'mysql');
        $this->assertEquals($config->get('database.params.user'), 'testuser');
        $this->assertTrue(is_array($config->get('database')));
        $this->assertTrue(is_array($config->get('database.params')));

        $config->add('database.params.user', 'newuser');
        $config->add('database.params.main', true);

        $dbParams = $config->get('database.params');

        $this->assertEquals($dbParams['user'], 'newuser');
        $this->assertEquals($dbParams['host'], 'localhost');
        $this->assertTrue($dbParams['main']);
    }

    /**
     * Test Config File PHP
     *
     * @return void
     * @covers Config::get
     * @covers Config::getRecursive
     * @covers Config::mergeRecursive
     */
    public function testConfigFilePHPWithoutEnv()
    {
        $config = Config::getInstance();
        $config->load(__DIR__ . '/TestWithoutEnv.php', 'phpwithoutenv', new EmptyParser());

        $this->assertEquals($config->get('phpwithoutenv\driver'), 'File');
        $this->assertEquals($config->get('phpwithoutenv\params\user'), 'testuser');
        $this->assertEquals($config->get('phpwithoutenv\params\pass'), 'testpass');
        $this->assertEquals($config->get('phpwithoutenv\params\host'), 'localhost');
        $this->assertEquals($config->get('phpwithoutenv\version'), 1);
    }

    /**
     * Test Config File PHP
     *
     * @return void
     * @covers Config::get
     * @covers Config::setCache
     * @covers Config::getRecursive
     * @covers Config::mergeRecursive
     */
    public function testConfigFilePHPWithEnv()
    {
        $config = Config::getInstance();
        $config->load(__DIR__ . '/TestWithEnv.php', 'phpwithenv', new EmptyParser(), 'dev');

        //~ Load dev environment
        $this->assertEquals($config->get('phpwithenv\driver'), 'File');
        $this->assertEquals($config->get('phpwithenv\params\user'), 'testuser');
        $this->assertEquals($config->get('phpwithenv\params\pass'), 'userpass');
        $this->assertEquals($config->get('phpwithenv\params\host'), 'localhost', 'host for env "dev" must be "localhost"!');
        $this->assertTrue($config->get('phpwithenv\params\debug'), 'debug param must be true!');
        $this->assertEquals($config->get('phpwithenv\version'), 1);

        //~ Load prod env
        $config->load(__DIR__ . '/TestWithEnv.php', 'phpwithenv', new EmptyParser(), 'prod');

        $this->assertEquals($config->get('phpwithenv\driver'), 'Memcache');
        $this->assertEquals($config->get('phpwithenv\params\user'), 'testuser');
        $this->assertEquals($config->get('phpwithenv\params\pass'), 'prodpass');
        $this->assertEquals($config->get('phpwithenv\params\host'), 'localhost');
        $this->assertEquals($config->get('phpwithenv\params\debug'), null);
        $this->assertEquals($config->get('phpwithenv\version'), null);
    }
}