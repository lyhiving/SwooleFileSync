<?php
namespace Swoole\ToolKit;

class NotFound extends \Exception
{
}
class FileMonitor
{
    /**
     * @var resource
     */
    protected $inotify;
    protected $str;
    protected $case;
    protected $isfolder = 0;
    protected $server;
    protected $conf;
    //默认监测所有文件，如果 为true 则只监控和此文件有关的变化
    protected $FileTypes = array('.php' => false);
    protected $watchFiles = array();
    protected $events;
    /**
     * 根目录
     * @var array
     */
    protected $rootDirs = array();
    public function putLog($log)
    {
        $_ENV['debug']->log('swoole:filemonitor', $log);
    }
    /**
     * @param $serverPid
     * @throws NotFound
     */
    public function __construct($conf)
    {
        $this->conf = $conf;
        $this->inotify = inotify_init();
        $this->events = IN_CLOSE_WRITE | IN_DELETE | IN_CREATE | IN_MOVE | IN_ATTRIB | IN_MOVE | IN_ISDIR | IN_ONLYDIR;
        // $this->events =  IN_ATTRIB | IN_CREATE | IN_DELETE | IN_DELETE_SELF | IN_MODIFY | IN_MOVE;
        // $this->events = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;
        // $this->events = IN_ALL_EVENTS;
        \Swoole\Event::add($this->inotify, function ($ifd) {
            $events = inotify_read($this->inotify);
            if (!$events) {
                return;
            }
            $doncases = array('created', 'delete', 'moveend');
            foreach ($events as $ev) {
                if ($ev['mask'] == IN_IGNORED) {
                    continue;
                } else if ($ev['mask'] == IN_CREATE or $ev['mask'] == IN_DELETE or $ev['mask'] == IN_CLOSE_WRITE or $ev['mask'] == IN_MOVED_TO or $ev['mask'] == IN_MOVED_FROM or $ev['mask'] == IN_ISDIR | IN_CREATE) {
                    $fileType = strrchr($ev['name'], '.');
                    //非监测类型
                    if (isset($this->FileTypes[$fileType])) {
                        //continue;
                    }
                    switch ($ev['mask']) {
                        case IN_CREATE:
                            $this->case = 'creating';
                            $this->str = '创建';
                            break;
                        case IN_CREATE | IN_ISDIR:
                            $this->case = 'creating';
                            $this->str = '创建目录';
                            break;
                        case IN_DELETE:
                            $this->case = 'delete';
                            $this->str = '删除';
                            break;
                        case IN_DELETE | IN_ISDIR:
                            $this->case = 'delete';
                            $this->str = '删除目录';
                            break;
                        case IN_CLOSE_WRITE:
                            $this->case = 'created';
                            $this->str = '修改';
                            break;
                        case IN_MOVED_FROM:
                            $this->case = 'movestart';
                            $this->str = '重命名 ' . $ev['name'];
                            break;
                        case IN_MOVED_TO:
                            $this->case = 'moveend';
                            $this->str .= ' 为';
                            break;
                    }

                    $this->isfolder = IN_ISDIR ? 1 : 0;

                    if ($ev['mask'] == IN_MOVED_FROM) {
                        continue;
                    } else {
                        $log = $this->str . " " . $ev['name'];
                    }
                    $path = array_search($ev['wd'], $this->watchFiles);

                    //发生变更的文件
                    $filename = $path . '/' . $ev['name'];
                    if (strstr($filename, '.') && !strstr($ev['mask'], 'CREATE')) {
                        $meta['file'] = $filename;
                        $meta['case'] = $this->case;
                        $meta['mask'] = $filename;
                        $this->sendToServer(json_encode($meta, JSON_UNESCAPED_UNICODE));
                    }
                    $this->putLog($log . "\t" . $path . "\t" . $path . '/' . $ev['name']);

                }
            }
        });
    }

    public function sendToServer($filename)
    {
        SCL:
        $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
        if (!$client->connect($this->conf['SourceFileServer']['host'], $this->conf['SourceFileServer']['port'], -1)) {
            $this->putLog("connect failed. Error: {$client->errCode}");
            $client->close();
            usleep(100000);
            goto SCL;
        }
        $client->send($filename);
    }

    /**
     * 添加文件类型
     * @param $type
     */
    public function addFileType($type)
    {
        $type = trim($type, '.');
        $this->FileTypes['.' . $type] = true;
    }
    /**
     * 添加事件
     * @param $inotifyEvent
     */
    public function addEvent($inotifyEvent)
    {
        $this->events |= $inotifyEvent;
    }
    /**
     * 清理所有inotify监听
     */
    public function clearWatch()
    {
        foreach ($this->watchFiles as $wd) {
            inotify_rm_watch($this->inotify, $wd);
        }
        $this->watchFiles = array();
    }
    /**
     * @param $dir
     * @param bool $root
     * @return bool
     * @throws NotFound
     */
    public function watch($dir, $root = true)
    {
        //目录不存在
        if (!is_dir($dir)) {
            throw new NotFound("[$dir] is not a directory.");
        }
        //避免重复监听
        if (isset($this->watchFiles[$dir])) {
            return false;
        }
        //根目录
        if ($root) {
            $this->rootDirs[] = $dir;
        }
        $wd = inotify_add_watch($this->inotify, $dir, $this->events);
        $this->watchFiles[$dir] = $wd;
        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f == '.' or $f == '..') {
                continue;
            }
            $path = rtrim($dir, '/') . '/' . $f;
            //递归目录
            if (is_dir($path)) {
                $this->watch($path, false);
            }
            //检测文件类型
            $fileType = strrchr($f, '.');
            if (isset($this->FileTypes[$fileType]) && $this->FileTypes[$fileType]) {
                $wd = inotify_add_watch($this->inotify, $path, $this->events);
                $this->watchFiles[$path] = $wd;
            }
        }
        return true;
    }
    public function run()
    {
        \Swoole\Event::wait();
    }
}
