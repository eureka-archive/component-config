<?php

/**
 * Copyright (c) 2010-2016 Romain Cottard
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

# Cache Config

return array(
    'prod' => array(
        'driver' => 'Memcache',
        'params' => array(
            'pass' => 'prodpass',
        ),
    ),

    'all' => array(
        'driver' => 'File',
        'params' => array(
            'user' => 'testuser',
            'pass' => 'testpass',
            'host' => 'localhost'
        )
    ),

    'dev' => array(
        'params' => array(
            'pass' => 'userpass',
            'debug' => true,
        ),
        'version' => 1
    ),
);