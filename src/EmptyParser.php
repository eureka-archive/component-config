<?php

/*
 * Copyright (c) Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eureka\Component\Config;

/**
 * Empty Parser file to read php file.
 *
 * @author Romain Cottard
 */
class EmptyParser
{
    /**
     * Load data from php file.
     *
     * @param  string $file
     * @return mixed File content.
     * @throws \Exception
     */
    public function load($file)
    {
        if (!file_exists($file)) {
            throw new Exception\InvalidConfigException('File to parse does not exists !');
        }

        $content = (include $file);

        return $content;
    }

    /**
     * Dump data into php file.
     *
     * @param  string $file
     * @param  mixed  $content
     * @throws \Exception
     */
    public function dump($file, $content)
    {
        $fileWritten = file_put_contents($file, '<?php return ' . var_export($content, true) . ';');

        if ($fileWritten === false) {
            throw new Exception\ConfigException('Unable to dump data into php file !');
        }
    }
}
