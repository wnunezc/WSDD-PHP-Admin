<?php

// Reviewed bundled-core recipes shared by the explicitly supported PHP minors.
$common = [
    'bcmath' => ['packages' => [], 'configure' => []],
    'bz2' => ['packages' => ['libbz2-dev'], 'configure' => []],
    'calendar' => ['packages' => [], 'configure' => []],
    'exif' => ['packages' => [], 'configure' => []],
    'gd' => ['packages' => ['libfreetype6-dev', 'libjpeg62-turbo-dev', 'libpng-dev'], 'configure' => ['--with-freetype', '--with-jpeg']],
    'gettext' => ['packages' => [], 'configure' => []],
    'intl' => ['packages' => ['libicu-dev'], 'configure' => []],
    'mysqli' => ['packages' => [], 'configure' => []],
    'opcache' => ['packages' => [], 'configure' => []],
    'pcntl' => ['packages' => [], 'configure' => []],
    'pdo_mysql' => ['packages' => [], 'configure' => []],
    'pdo_pgsql' => ['packages' => ['libpq-dev'], 'configure' => []],
    'pgsql' => ['packages' => ['libpq-dev'], 'configure' => []],
    'shmop' => ['packages' => [], 'configure' => []],
    'soap' => ['packages' => ['libxml2-dev'], 'configure' => []],
    'sockets' => ['packages' => [], 'configure' => []],
    'sysvmsg' => ['packages' => [], 'configure' => []],
    'sysvsem' => ['packages' => [], 'configure' => []],
    'sysvshm' => ['packages' => [], 'configure' => []],
    'xsl' => ['packages' => ['libxslt1-dev'], 'configure' => []],
    'zip' => ['packages' => ['libzip-dev'], 'configure' => []],
];
return [
    'debian' => $common,
    'variants' => [
        'debian' => [
            [
                'minimum' => '5.6',
                'maximum_exclusive' => '7.4',
                'recipes' => [
                    'gd' => ['configure' => ['--with-freetype-dir=/usr', '--with-jpeg-dir=/usr']],
                    'zip' => ['packages' => ['zlib1g-dev'], 'configure' => ['--with-zlib-dir=/usr']],
                ],
            ],
        ],
    ],
];
