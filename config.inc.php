<?php
$conf = [
    'SourceFileServer' => ['host' => '127.0.0.1', 'port' => 9657],
    'FileSyncClient' => [
        ['host' => '127.0.0.1', 'port' => 9656],
    ],
    'BaseDir' => __DIR__,
    'FileMonitorDir' => dirname(__DIR__) . '/download',
    'FileAction' => ['hook' => 'unzipAndMove', 'done' => true],
    'log_file' => __DIR__.'/swoole.log',
    'cmd' => '/usr/bin/php',
];
