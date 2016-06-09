<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Config;

use Eureka\Component\Debug\Debug;

/**
 * Configuration class.
 *
 * @author Romain Cottard
 * @version 2.1.0
 */
class Config
{
    /**
     * Config file written in Yaml
     *
     * @var string FILE_YAML
     */
    const FILE_YAML = 'yaml';

    /**
     * Config file written in PHP
     *
     * @var string FILE_PHP
     */
    const FILE_PHP = 'php';

    /**
     * Current class instance.
     *
     * @var Config $instance
     */
    protected static $instance = null;

    /**
     * Array of config infos
     *
     * @var array $config
     */
    protected $config = array();

    /**
     * Current file loaded
     *
     * @var string $currentFile
     */
    protected $currentFile = '';

    /**
     * Cache object
     *
     * @var object Cache object
     */
    protected $cache = null;

    /**
     * Class constructor.
     *
     * @param object|null $parser File parser.
     * @param object|null $cache Cache object.
     * @return Config
     */
    protected function __construct($parser = null, $cache = null)
    {
        $this->parser = $parser;
        $this->cache  = $cache;
    }

    /**
     * Singleton pattern method to get current instance.
     *
     * @return Config
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new Config();
        }

        return static::$instance;
    }

    /**
     * Set cache object
     *
     * @param  object $cache
     * @return void
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * Add config value(s).
     * Add new configuration value.
     * Example:
     * $dbConfig = array(
     *     'host' => 'localhost',
     *     'user' => 'db_user',
     *     'pass' => 'mYpAsS',
     * );
     * $config = Config::getIntance();
     * $config->add('database', $databaseConfig);
     * $config->add('database.host', 'newhost');
     * $config
     *
     * @param    string $namespace Configuration name.
     * @param    mixed  $data Configuration value.
     * @return   Config
     */
    public function add($namespace, $data = null)
    {
        //$names = explode('.', str_replace(array('/', '\\'), '.', $namespace, ));
        $names = explode('\\', $namespace);

        $namespace = array_shift($names);

        if (!isset($this->config[$namespace])) {
            $this->config[$namespace] = array();
        }

        $this->addRecursive($this->config[$namespace], $names, $data);

        return $this;
    }

    /**
     * Add config value to config array.
     *
     * @param array $config
     * @param array $names
     * @param mixed $data
     * @return void
     */
    protected function addRecursive(&$config, $names, $data)
    {
        if (empty($names)) {
            $config = $this->replace($data);

            return;
        }

        $name = array_shift($names);
        if (!isset($config[$name])) {
            $config[$name] = array();
        }

        $this->addRecursive($config[$name], $names, $data);
    }

    /**
     * Add config value(s).
     *
     * @param    mixed $config Configuration value.
     * @return   mixed
     */
    public function replace($config)
    {
        $patterns = array(
            'constants' => array(
                '`EKA_[A-Z_]+`',
            ), 'php'    => array(
                '__DIR__',
            ),
        );

        if (!is_array($config)) {

            foreach ($patterns['constants'] as $pattern) {
                if ((bool) preg_match_all($pattern, $config, $matches)) {

                    $matches   = array_unique($matches[0]);
                    $constants = array('.' => '');

                    foreach ($matches as $index => $constant) {
                        $constants[$constant] = constant($constant);
                    }

                    $config = str_replace(array_keys($constants), array_values($constants), $config);

                    if (is_numeric($config)) {
                        $config = (int) $config;
                    }
                }
            }

            $currentDir = dirname($this->currentFile);
            foreach ($patterns['php'] as $pattern) {

                switch ($pattern) {
                    case '__DIR__':
                        $replace = $currentDir;
                        break;
                    default:
                        continue 2;
                }

                $config = str_replace($pattern, $replace, $config);
            }

            if (false !== strpos($config, '..')) {
                $config = realpath($config);
            }

        } elseif (is_array($config)) {

            foreach ($config as $key => $conf) {
                $config[$key] = $this->replace($conf);
            }
        }

        return $config;
    }

    /**
     * Add config value(s).
     * Add new configuration value.
     * Example:
     * $dbConfig = array(
     *     'host' => 'localhost',
     *     'user' => 'db_user',
     *     'pass' => 'mYpAsS',
     * );
     * $config = Config::getIntance();
     * $config->add('database', $databaseConfig);
     * $config->add('database.host', 'newhost');
     * $config->get('database'); // array
     * $config->get('database.user'); // string
     *
     * @param    string $namespace Configuration name.
     * @return   mixed
     */
    public function get($namespace)
    {
        $names = explode('\\', $namespace);

        return $this->getRecursive($this->config, $names);
    }

    /**
     * Add config value to config array.
     *
     * @param array $config
     * @param array $names
     * @return mixed
     */
    protected function getRecursive(&$config, $names)
    {
        if (empty($names)) {
            return $config;
        }

        $name = array_shift($names);

        if (!isset($config[$name])) {
            return null;
        }

        return $this->getRecursive($config[$name], $names);
    }

    /**
     * Get config value for config var specified.
     *
     * @param    string $file
     * @param    string $namespace
     * @param    object $parser File parser
     * @param    string $env
     * @return   Config
     * @throws   \Exception
     */
    public function load($file, $namespace = '', $parser = null, $env = null)
    {
        $config = array();

        $this->currentFile = $file;

        //~ Check in cache
        if (is_object($this->cache)) {
            $config = $this->cache->get('Eureka.Component.Config.Test.' . $env . '.' . md5($file) . '.cache');
        }

        //~ If not in cache or cache object not defined
        if (empty($config)) {

            if (!file_exists($file)) {
                throw new \Exception('Configuration file does not exists !');
            }

            $config = $parser->load($file);

            if ($env !== null) {
                $configTmp = array();

                //~ Firstable, pick section 'all' from config if section exists.
                if (isset($config['all']) && is_array($config['all'])) {
                    $configTmp = $config['all'];
                }

                //~ Secondly, merge recursively with section corresponding with environment if section exists.
                if (isset($config[$env]) && is_array($config[$env])) {
                    $configTmp = array_replace_recursive($configTmp, $config[$env]);
                }

                //~ Set merged configurations into main array.
                $config = $configTmp;
            }

            if (is_object($this->cache)) {
                $this->cache->set('Eureka.Component.Config.Test.' . $env . '.' . md5($file) . '.cache', $config);
            }
        }

        $this->add($namespace, $config);

        $this->currentFile = '';

        return $this;
    }
}
