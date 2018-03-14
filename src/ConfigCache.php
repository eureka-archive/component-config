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
 * Configuration Cache class.
 *
 * @author Romain Cottard
 */
class ConfigCache
{
    /** @var \Eureka\Component\Config\Config config */
    protected $config;

    /** @var string $environment */
    protected $environment = '';

    /**
     * ConfigCache constructor.
     *
     * @param string $environment
     * @param string $path
     * @throws \Eureka\Component\Config\Exception\ConfigException
     * @throws \Eureka\Component\Config\Exception\InvalidConfigException
     */
    public function __construct($path, $environment = 'prod')
    {
        $this->environment = $environment;
        $this->config = new Config($environment);
        $this->config->load($path . DIRECTORY_SEPARATOR . 'app.yml', 'app.app.', new Yaml(), $environment);
        $this->config->load($path . DIRECTORY_SEPARATOR . 'cache.yml', 'app.cache.', new Yaml(), $environment);
    }

    /**
     * @return bool
     */
    public function hasCache()
    {
        return is_readable($this->getCacheFile());
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->config->get('app.cache.config.enabled');
    }

    /**
     * @param  array $config
     * @return $this
     * @throws \Eureka\Component\Config\Exception\ConfigException
     */
    public function dumpCache(array $config)
    {
        $filePathname = $this->getCacheFile();

        $path = dirname($filePathname);
        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            throw new Exception\ConfigException('Cache directory does not exist and cannot create it! (file: ' . $path . ')');
        }

        if (!file_put_contents($filePathname, "<?php\n\nreturn " . var_export($config, true) . ';')) {
            throw new Exception\ConfigException('Cannot write cache file.');
        }

        return $this;
    }

    /**
     * @return array
     * @throws \Eureka\Component\Config\Exception\FileCacheNotFoundException
     */
    public function loadFromCache()
    {
        $filePathname = $this->getCacheFile();

        if (!is_readable($filePathname)) {
            throw new Exception\FileCacheNotFoundException();
        }

        return include($filePathname);
    }

    /**
     * @return string
     */
    protected function getCacheFile()
    {
        $cachePath = $this->config->get('app.cache.config.path');
        $cacheFile = $this->config->get('app.cache.config.file');

        return $cachePath . DIRECTORY_SEPARATOR . $this->environment . '_' . $cacheFile;
    }
}
