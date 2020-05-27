<?php

define('IA_ROOT', dirname(__DIR__));
require_once __DIR__ . '/vendor/autoload.php';

$param = getopt('t:o:');
$type = $param['t'];
$option = $param['o'];

$func = new ReflectionClass('Swoole\ToolKit\FileMonitor');
$src = dirname($func->getFileName());
require __DIR__ . '/config.inc.php';

if ($type == 's') {
    if ($option == 'start') {
        $sourceFileServerPath = $src . '/SourceFileServer.php';
        $fileMonitorPath = $src . '/FileMonitor.php';
        $cmd = $conf['cmd'];
        //先启动 SourceFileServer
        $process = new swoole_process(function (swoole_process $process) use ($cmd, $sourceFileServerPath) {
            $process->exec($cmd, [$sourceFileServerPath]); // exec 系统调用
        }, true);
        $process->start();
        //文件监控
        $process = new swoole_process(function (swoole_process $process) use ($conf, $fileMonitorPath) {
            require_once $fileMonitorPath;
            $kit = new Swoole\ToolKit\FileMonitor($conf);
            $dir = $conf['FileMonitorDir'];
            $kit->watch($dir);
            $kit->run();
        }, true);
        $process->start();
        $process->daemon();
    } elseif ($option == 'stop') {
        @exec('ps -ef|grep SourceFileServer.php|grep -v grep|cut -c 9-15|xargs kill -9');
        $process = new swoole_process(function (swoole_process $process) {
            exec('ps -ef|grep daemon.php|grep -v grep|cut -c 9-15|xargs kill -9');
        }, true);
        $process->start();
        echo $process->read() . PHP_EOL;
        $process = new swoole_process(function (swoole_process $process) {
            echo "Kill node" . PHP_EOL;
            exec('ps -ef|grep SourceFileServer.php|grep -v grep|cut -c 9-15|xargs kill -9');
        }, true);
        $process->start();
        echo $process->read() . PHP_EOL;
    }
} elseif ($type == 'c') {
    if ($option == 'start') {
        $port = $conf['FileSyncClient']['0']['port'];
        new Swoole\ToolKit\FileSyncClient($port);
    } elseif ($option == 'stop') {
        exec('ps -ef|grep daemon.php|grep -v grep|cut -c 9-15|xargs kill -9');
    }
}
