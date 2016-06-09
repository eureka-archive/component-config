<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Config;

use Eureka\Component\Cache;
use Eureka\Component\Yaml;

require_once __DIR__.'/../Config.php';
require_once __DIR__.'/../EmptyParser.php';

//~ Require Cache classes
require_once __DIR__.'/../../Cache/CacheFactory.php';
require_once __DIR__.'/../../Cache/CacheWrapperAbstract.php';
require_once __DIR__.'/../../Cache/CacheWrapperFile.php';

//~ Require Yaml class
require_once __DIR__.'/../../Yaml/Yaml.php';

/**
 * Class Test for cache
 *
 * @author Romain Cottard
 * @version 2.1.0
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
            'driver' => 'mysql',
            'params' => array('user' => 'testuser', 'pass' => 'testpass', 'host' => 'localhost'),
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
     * Test Config File Yaml
     *
     * @return void
     * @covers Config::get
     * @covers Config::getRecursive
     * @covers Config::mergeRecursive
     */
    public function testConfigFileYamlWithoutEnv()
    {
        $config = Config::getInstance();
        $config->load(__DIR__.'/TestWithoutEnv.yml', 'yamlwithoutenv', new Yaml\Yaml());

        $this->assertEquals($config->get('yamlwithoutenv.driver'), 'File');
        $this->assertEquals($config->get('yamlwithoutenv.params.user'), 'testuser');
        $this->assertEquals($config->get('yamlwithoutenv.params.pass'), 'testpass');
        $this->assertEquals($config->get('yamlwithoutenv.params.host'), 'localhost');
        $this->assertEquals($config->get('yamlwithoutenv.version'), 1);
    }

    /**
     * Test Config File Yaml
     *
     * @return void
     * @covers Config::get
     * @covers Config::getRecursive
     * @covers Config::mergeRecursive
     */
    public function testConfigFileYamlWithEnv()
    {
        $config = Config::getInstance();

        //~ Load dev environment
        $config->load(__DIR__.'/TestWithEnv.yml', 'yamlwithenv', new Yaml\Yaml(), 'dev');

        $this->assertEquals($config->get('yamlwithenv.driver'), 'File');
        $this->assertEquals($config->get('yamlwithenv.params.user'), 'testuser');
        $this->assertEquals($config->get('yamlwithenv.params.pass'), 'userpass');
        $this->assertEquals($config->get('yamlwithenv.params.host'), 'localhost');
        $this->assertTrue($config->get('yamlwithenv.params.debug'));
        $this->assertEquals($config->get('yamlwithenv.version'), 1);

        //~ Load prod env
        $config->load(__DIR__.'/TestWithEnv.yml', 'yamlwithenv', new Yaml\Yaml(), 'prod');

        $this->assertEquals($config->get('yamlwithenv.driver'), 'Memcache');
        $this->assertEquals($config->get('yamlwithenv.params.user'), 'testuser');
        $this->assertEquals($config->get('yamlwithenv.params.pass'), 'prodpass');
        $this->assertEquals($config->get('yamlwithenv.params.host'), 'localhost');
        $this->assertEquals($config->get('yamlwithenv.params.debug'), null);
        $this->assertEquals($config->get('yamlwithenv.version'), null);
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
        $config->load(__DIR__.'/TestWithoutEnv.php', 'phpwithoutenv', new EmptyParser());

        $this->assertEquals($config->get('phpwithoutenv.driver'), 'File');
        $this->assertEquals($config->get('phpwithoutenv.params.user'), 'testuser');
        $this->assertEquals($config->get('phpwithoutenv.params.pass'), 'testpass');
        $this->assertEquals($config->get('phpwithoutenv.params.host'), 'localhost');
        $this->assertEquals($config->get('phpwithoutenv.version'), 1);
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
        $config->setCache(Cache\CacheFactory::build('File'));
        $config->load(__DIR__.'/TestWithEnv.php', 'phpwithenv', new EmptyParser(), 'dev');

        //~ Load dev environment

        $this->assertEquals($config->get('phpwithenv.driver'), 'File');
        $this->assertEquals($config->get('phpwithenv.params.user'), 'testuser');
        $this->assertEquals($config->get('phpwithenv.params.pass'), 'userpass');
        $this->assertEquals($config->get('phpwithenv.params.host'), 'localhost');
        $this->assertTrue($config->get('phpwithenv.params.debug'));
        $this->assertEquals($config->get('phpwithenv.version'), 1);

        //~ Load prod env
        $config->load(__DIR__.'/TestWithEnv.php', 'phpwithenv', new EmptyParser(), 'prod');

        $this->assertEquals($config->get('phpwithenv.driver'), 'Memcache');
        $this->assertEquals($config->get('phpwithenv.params.user'), 'testuser');
        $this->assertEquals($config->get('phpwithenv.params.pass'), 'prodpass');
        $this->assertEquals($config->get('phpwithenv.params.host'), 'localhost');
        $this->assertEquals($config->get('phpwithenv.params.debug'), null);
        $this->assertEquals($config->get('phpwithenv.version'), null);
    }
}