<?php
if (!isset($param)) {
    echo "\$parami is unset..." . PHP_EOL;
    return false;
}
require_once __DIR__ . '/vendor/autoload.php';
$zipfile = $param;

if (!is_file($param)) {
    echo $param . "file is NO EXIST..." . PHP_EOL;
    return false;
}
$upload = dirname(__DIR__) . '/upload/';
$exchange = dirname(__DIR__) . '/exchange/';
if(strpos(strtolower($zipfile),'.zip')){
    echo $param . "No .zip file found..." . PHP_EOL;
    return false;
}
$id = str_replace(array(".zip", ".ZIP"), array("", ""), basename($zipfile));
$target = $exchange;

$zipFile = new \PhpZip\ZipFile();

if (!is_dir($target)) {
    mkdir($target, 0755, true);
}
if (!is_dir($upload)) {
    mkdir($upload, 0755, true);
}
echo "inHook..." . $hook . '@' . $zipfile . PHP_EOL;

// echo "UPLOAD TO ".$upload . basename($zipfile).PHP_EOL;
try {
    $zipFile
        ->openFile($param) // open archive from file
        ->extractTo($target) // extract files to the specified directory
        ->deleteFromRegex('~^\.~');
    $listFiles = $zipFile->getListFiles();
    $ext = ['使用说明',  '.link'];
    foreach ($listFiles as $k => $r) {
        foreach ($ext as $_r) {
            $r = $self->transcoding($r);
            if (strpos($target.$r, $_r)) {
                echo "=========".$target . $r.PHP_EOL; 
                @unlink($target . $r);
            }
        }
    }
    $zipFile->close();
    $zipFile = new \PhpZip\ZipFile(); 
    $dir = $target . $id. '/' . $id;
    $zipFile->addDirRecursive(is_dir($dir) ? $dir : $target);
    $zipFile->addFromString('我来自外面.txt', 'Test file') // add a new entry from the string
        ->saveAsFile($upload . basename($zipfile));
    $self->rrmdir($target);
} catch (\PhpZip\Exception\ZipException $e) {
    // handle exception
    var_dump($e->getMessage());
} finally {
    $zipFile->close();
}
