<?php
namespace Swoole\ToolKit;

class FileWatch
{
    protected $all = array();
    protected $sleep = 1; //暂停时间X秒
    protected $count = 0; //当前次数
    protected $total = 0; //总次数
    protected $loops = 0; //循环计数
    protected $limit = 0; //限制读取多少个
    protected $loopslimit = 0; //限制循环次数
    protected $path;
    protected $include_dirs;
    protected $stopit = false;
    protected $keepalive = false; //一直读取

    public function __construct($path, $include_dirs = true)
    {
        $this->path = $path;
        $this->include_dirs = $include_dirs;
    }

    public function set($key, $value)
    {
        if (!in_array($key, ['all', 'count', 'total', 'loops'])) {
            $this->$key = $value;
        }
        return $this;
    }

    public function get($key)
    {
        return $this->$key;
    }

    public function stop()
    {
        $this->set('stopit', true);
        return $this;
    }

    /**
     * 一直循环读取
     */
    public function live($func, $loopslimit = null)
    {
        $this->set('stopit', false);
        $this->set('keepalive', true);
        if (is_numeric($loopslimit)) {
            $this->set('loopslimit', $loopslimit);
        }

        $this->watch($func, $this->path, $this->include_dirs);
    }

    /**
     *
     */
    public function run($func, $limit = null)
    {
        $this->set('stopit', false);
        if (is_numeric($limit)) {
            $this->set('limit', $limit);
        }

        $this->watch($func, $this->path, $this->include_dirs);
    }

    public function pro($func, $limit = null)
    {
        $this->set('stopit', false);
        if (is_numeric($limit)) {
            $this->set('limit', $limit);
        }

        $this->watch_pro($func, $this->path, $this->include_dirs);
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

    public function watch($func, $path, $include_dirs = true, $ispro = false)
    {
        $glob = $this->glob2foreach($path, $include_dirs);
        while (!$this->stopit) {
            if ($this->keepalive && (!$this->loopslimit || $this->loops+1 <= $this->loopslimit)) {
                if (!$glob->valid()) {
                    var_dump(\microtime(true));
                    sleep($this->sleep);
                    $this->count = 0;
                    $this->loops++;
                    $glob = $this->glob2foreach($path, $include_dirs);
                }
            }

            // 当前文件
            $filename = $glob->current();
            if ($filename) {
                if ($ispro) {
                    if (!in_array($filename, $this->all)) {
                        $this->all[] = $filename;
                    }

                }
                if ($func) {
                    $this->$func($filename, $glob);
                }
                $this->count++;
                $this->total++;
                if ($this->limit > 0 && $this->total >= $this->limit) { //限制次数
                    $this->stop();
                }
            }
            // 指向下一个，不能少
            $glob->next();
        }
    }

    public function watch_pro($func, $path, $include_dirs = true)
    {
        $this->watch($func, $path, $include_dirs, true);
    }
}
