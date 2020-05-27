<?php
require_once __DIR__ . '/vendor/autoload.php';

use lyhiving\debug\debug;
$log_file = __DIR__ . '/swoole.log';
$conf = [
    'SourceFileServer' => ['host' => '127.0.0.1', 'port' => 9657],
    'FileSyncClient' => [
        ['host' => '127.0.0.1', 'port' => 9656],
    ],

    'BaseDir' => __DIR__,
    'FileMonitorDir' => __DIR__ . '/download',
    'FileAction' => ['hook' => 'unzipAndMove', 'done' => true],
    'serverConf' => ['log_file' => $log_file],
    'cmd' => '/usr/bin/php',
    ''
];

if (!$_ENV['debug']) {
    $_ENV['debug'] = new debug($log_file, false);
    $_ENV['debug']->set('log_level', 0);
}