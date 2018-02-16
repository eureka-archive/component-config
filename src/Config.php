<?php

/*
 * Copyright (c) Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Config;

use Eureka\Component\Yaml\Yaml;

/**
 * Configuration class.
 *
 * @author Romain Cottard
 */
class Config
{
    /** @var string FILE_YAML Config file written in Yaml */
    const FILE_YAML = 'yaml';

    /** @var string FILE_PHP Config file written in PHP */
    const FILE_PHP = 'php';

    /** @var Config $instance Current class instance. */
    protected static $instance = null;

    /** @var object $parser Parser instance */
    protected $parser = null;

    /** @var array $config Array of config info */
    protected $config = [];

    /** @var string $currentFile Current file loaded */
    protected $currentFile = '';

    /** @var object Cache object Cache object */
    protected $cache = null;

    /** @var string $environment Current environment */
    protected $environment = 'dev';

    /**
     * Class constructor.
     *
     * @param string $environment
     * @param object|null $parser File parser.
     * @param object|null $cache Cache object.
     */
    public function __construct($environment = 'dev', $parser = null, $cache = null)
    {
        $this->parser      = $parser;
        $this->cache       = $cache;
        $this->environment = $environment;
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
     * Get environment name.
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
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
     * $dbConfig = [
     *     'host' => 'localhost',
     *     'user' => 'db_user',
     *     'pass' => 'mYpAsS',
     * ];
     * $config = new Config();
     * $config->add('database', $databaseConfig);
     * $config->add('database.host', 'newHost');
     * $config
     *
     * @param   string $namespace Configuration name.
     * @param   mixed $data Configuration value.
     * @param   bool $doReplace
     * @return  $this
     */
    public function add($namespace, $data = null, $doReplace = false)
    {
        $names = preg_split('`[\\\\.]+`', (string) $namespace, -1, PREG_SPLIT_NO_EMPTY);

        $namespace = array_shift($names);

        if (!isset($this->config[$namespace])) {
            $this->config[$namespace] = [];
        };

        $this->addRecursive($this->config[$namespace], $names, $data, $doReplace);

        //~ Replace references of other parameters final config file
        $this->replaceReferences($this->config);

        return $this;
    }

    /**
     * Add config value to config array.
     *
     * @param  array $config
     * @param  array $names
     * @param  mixed $data
     * @param  bool $doReplace
     * @return void
     */
    protected function addRecursive(&$config, $names, $data, $doReplace = false)
    {
        if (empty($names)) {
            $data = $this->replace($data);
            if (is_array($data)) {
                $config = array_merge($config, $data);
            } else {
                $config = $data;
            }

            return;
        }

        $name = array_shift($names);
        if ($doReplace || !isset($config[$name])) {
            $config[$name] = [];
        }

        $this->addRecursive($config[$name], $names, $data);
    }

    /**
     * Add config value(s).
     *
     * @param  mixed $config Configuration value.
     * @return mixed
     */
    public function replace($config)
    {
        $patterns = [
            'constants' => [
                '`EKA_[A-Z_]+`',
            ],
            'php'       => [
                '__DIR__',
                'DIRECTORY_SEPARATOR',
            ],
        ];

        if (!is_array($config)) {

            foreach ($patterns['constants'] as $pattern) {

                if (!is_string($config) || empty($config)) {
                    continue;
                }

                if ((bool) preg_match_all($pattern, $config, $matches)) {

                    $matches   = array_unique($matches[0]);
                    $constants = ['.' => ''];

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

                if (!is_string($config) || empty($config)) {
                    continue;
                }

                if (strpos($config, $pattern) !== false) {

                    switch ($pattern) {
                        case '__DIR__':
                            $replace = $currentDir;
                            break;
                        case 'DIRECTORY_SEPARATOR':
                            $replace = DIRECTORY_SEPARATOR;
                            break;
                        default:
                            continue 2;
                    }

                    $config = str_replace($pattern, $replace, $config);
                }
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
     * $dbConfig = [
     *     'host' => 'localhost',
     *     'user' => 'db_user',
     *     'pass' => 'mYpAsS',
     * ];
     * $config = new Config();
     * $config->add('database', $databaseConfig);
     * $config->add('database.host', 'newHost');
     * $config->get('database'); // array
     * $config->get('database.user'); // string
     *
     * @param  string $namespace Configuration name.
     * @return mixed
     */
    public function get($namespace)
    {
        $names = preg_split('`[\\\\.]+`', $namespace, -1, PREG_SPLIT_NO_EMPTY);

        return $this->getRecursive($this->config, $names);
    }

    /**
     * Add config value to config array.
     *
     * @param  array $config
     * @param  array $names
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
     * @param  string $file
     * @param  string $namespace
     * @param  object $parser File parser
     * @param  string $env
     * @return $this
     * @throws \Eureka\Component\Config\Exception\InvalidConfigException
     */
    public function load($file, $namespace = '', $parser = null, $env = null)
    {
        $config = [];

        $this->currentFile = $file;

        //~ Check in cache
        if (is_object($this->cache)) {
            $config = $this->cache->get('eureka.component.config.test.' . $env . '.' . md5($file) . '.cache');
        }

        //~ If not in cache or cache object not defined
        if (empty($config)) {

            if (!file_exists($file)) {
                throw new Exception\InvalidConfigException('Configuration file does not exists !');
            }

            $config = $parser->load($file);

            if ($env !== null) {
                $configTmp = [];

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
                $this->cache->set('eureka.component.config.test.' . $env . '.' . md5($file) . '.cache', $config);
            }
        }

        $this->add($namespace, $config);

        $this->currentFile = '';

        return $this;
    }

    /**
     * Load yaml files from given directory.
     *
     * @param  string $directory
     * @param  string $namespace
     * @param  null|string $environment
     * @param  bool $forcedEnvironment
     * @return $this
     * @throws \Eureka\Component\Config\Exception\InvalidConfigException
     */
    public function loadYamlFromDirectory($directory, $namespace = 'app.', $environment = null, $forcedEnvironment = true)
    {
        if ($forcedEnvironment && $environment === null) {
            $environment = $this->environment;
        }

        foreach (glob($directory . '/*.yml') as $filename) {
            $this->load($filename, $namespace . basename($filename, '.yml'), new Yaml(), $environment);
        }

        return $this;
    }

    /**
     * Replace references values in all configurations.
     *
     * @param  array $config
     * @return void
     */
    private function replaceReferences(array &$config)
    {
        foreach ($config as $key => &$value) {
            if (is_array($value)) {
                $this->replaceReferences($value);
                continue;
            }

            //~ Not string, skip
            if (!is_string($value)) {
                continue;
            }

            //~ Value not %my.reference.config%, skip
            if (!(bool) preg_match('`%(.*?)%`', $value, $matches)) {
                continue;
            }

            $referenceValue = $this->get($matches[1]);

            if ($referenceValue !== null) {
                $value = $referenceValue;
            }
        }
    }
}
