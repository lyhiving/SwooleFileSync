<?php

if (!isset($param)) {
    $info = "\$param is not set...";
    $_ENV['debug']->log('swoole:onHook:' . $hook.': '.$info);
    echo $info . PHP_EOL;
    return false;
}
require_once __DIR__ . '/vendor/autoload.php';
$zipfile = $param;

if (!is_file($param)) {
    $info = "\$file is not exists...";
    $_ENV['debug']->log('swoole:onHook:' . $hook.': '.$info);
    echo $info . PHP_EOL;
    return false;
}

$upload = __DIR__ . '/upload/';
$exchange = __DIR__ . '/exchange/';

if (strpos(strtolower($zipfile), '.zip') === false) {
    $info = $param . " is not a .zip file...";
    $_ENV['debug']->log('swoole:onHook:' . $hook.': '.$info);
    echo $info . PHP_EOL;
    return false;
}
$id = str_replace(array(".zip", ".ZIP"), array("", ""), basename($zipfile));

$ZIP = new \PhpZip\ZipFile();

if (!is_dir($exchange)) {
    mkdir($exchange, 0755, true);
}
if (!is_dir($upload)) {
    mkdir($upload, 0755, true);
}

try {
    // $_ENV['debug']->log('swoole:onHook:'.$hook, ['file'=>$zipfile, 'exchange'=>$exchange, 'upload'=>$upload]);
    $_ENV['debug']->log('swoole:onHook:' . $hook.':unzip', $zipfile);

    $ZIP->openFile($zipfile) // open archive from file
        ->extractTo($exchange) // extract files to the specified directory
        ->deleteFromRegex('~^\.~');
    $listFiles = $ZIP->getListFiles();
    $ext = ['使用说明', '.link'];
    foreach ($listFiles as $k => $r) {
        foreach ($ext as $_r) {
            $r = $self->transcoding($r);
            if (strpos($exchange . $r, $_r)) {
                @unlink($exchange . $r);
                $_ENV['debug']->log('swoole:onHook:' . $hook.':removed', $exchange . $r);
            }
        }
    }
    $ZIP->close();
    $ZIP = new \PhpZip\ZipFile();
    $dir = $exchange . $id . '/' . $id;
    if (!is_dir($dir)) {
        $dir = $exchange . $id;
    }
    $newzip = $upload . basename($zipfile);
    $ZIP->addDirRecursive(is_dir($dir) ? $dir : $exchange);
    $ZIP->addFromString('我来自外面.txt', 'Test file') // add a new entry from the string
        ->saveAsFile($newzip);
    $self->rrmdir($exchange);
    $_ENV['debug']->log('swoole:onHook:' . $hook.':newzipfile', $newzip);
} catch (\PhpZip\Exception\ZipException $e) {
    $_ENV['debug']->log('swoole:onHook:' . $hook, ['file' => $zipfile, 'exchange' => $exchange, 'upload' => $upload, 'error' => $e->getMessage()]);
} finally {
    $ZIP->close();
}