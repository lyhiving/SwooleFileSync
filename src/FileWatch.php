<?php
namespace Swoole\ToolKit;

class FileWatch
{
    protected $all = array();
    protected $sleep = 2;

    public function __construct($path, $include_dirs = true)
    {
        $this->watch($path);
    }

    //子类中重写这个方法
    public function fun($file)
    {

    }

    public function set_sleep($second)
    {
        $this->sleep = $second;
        return $this;
    }

    public function glob2foreach($path, $include_dirs = true)
    {
        $path = rtrim($path, '/*');
        if (is_readable($path)) {
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if (substr($file, 0, 1) == '.') {
                    continue;
                }

                $rfile = "{$path}/{$file}";
                if (is_dir($rfile)) {
                    $sub = $this->glob2foreach($rfile, $include_dirs);
                    while ($sub->valid()) {
                        yield $sub->current();
                        $sub->next();
                    }
                    if ($include_dirs) {
                        yield $rfile;
                    }

                } else {
                    yield $rfile;
                }
            }
            closedir($dh);
        }
    }

    public function watch($path, $include_dirs = true)
    {
        $glob = $this->glob2foreach($path, $include_dirs);
        while (1) {
            if(!$glob->valid()){
                sleep($this->sleep);
                $glob = $this->glob2foreach($path, $include_dirs);
            }
            // 当前文件
            $filename = $glob->current();

            $this->fun($filename);

            // 指向下一个，不能少
            $glob->next();
        }
    }
}
