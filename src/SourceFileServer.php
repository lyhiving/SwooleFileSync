<?php
namespace Swoole\ToolKit;

class SourceFileServer
{
    private $serv;
    private $conf;

    public function __construct()
    {
        date_default_timezone_set('PRC');
        if (is_file(dirname(__DIR__) . '/config.inc.php')) {
            require dirname(__DIR__) . '/config.inc.php';
        } else {
            require dirname(__DIR__) . '/config.sample.php';
        }
        $this->conf = $conf;
        $this->serv = new \swoole_server($this->conf['SourceFileServer']['host'], $this->conf['SourceFileServer']['port']);
        $serv_conf = array(
            'worker_num' => 1,
            'daemonize' => true,
            'max_request' => 10000,
            'dispatch_mode' => 1,
            'debug_mode' => 1,
            'task_worker_num' => 1,
            // 'log_file' => '/tmp/swoole.log',
            'log_level' => 1,
            'socketbuffersize' => 200 * 1024 * 1024,
            // 'open_length_check'     => true,
            'package_max_length' => 20 * 1024 * 1024,
            // 'package_length_type'   => 'N',
            // 'package_length_offset' => 0,
            // 'package_body_offset'   => 4,

        );
        if ($this->conf['serverConf']) {
            $serv_conf = array_merge($serv_conf, $this->conf['serverConf']);
        }
        $this->serv->set($serv_conf);
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));
        $this->serv->start();
    }

    public function onWorkerStart($serv, $worker_id)
    {
        $_ENV['debug']->log('swoole:worker:start', $worker_id);
    }

    public function onClose($serv, $fd, $from_id)
    {
        $_ENV['debug']->log('swoole:client:close', $fd);
    }

    public function onReceive($serv, $fd, $from_id, $meta)
    {
        $serv->task($meta); //投递task任务
        // $serv->send($fd, $meta ? true : false);
    }

    public function onTask($serv, $task_id, $from_id, $meta)
    {
        $meta = json_decode($meta, true);
        $param = $meta['file'];
        $hook = 'sendToClient';

        $_ENV['debug']->log('swoole:onTask:task', 'task_id_'.$task_id.": ".$param);
        if ($this->conf['FileAction'] && $this->conf['FileAction']['hook']) {
            $hook = $this->conf['FileAction']['hook'];
            $done = $this->conf['FileAction'] && isset($this->conf['FileAction']['done']) ? $this->conf['FileAction']['done'] : true;
            $self = $this;
            if (is_file($this->conf['BaseDir'] . '/hooks.php')) {
                require $this->conf['BaseDir'] . '/hooks.php';
            }
            if (is_file($this->conf['BaseDir'] . '/hooks_' . $hook . '.php')) {
                require $this->conf['BaseDir'] . '/hooks_' . $hook . '.php';
            }
            if (!$done && $hook != 'sendToClient') {
                $this->sendToClient($param);
            }

        } else {
            $this->sendToClient($param);
        }

        return $task_id;
    }

    public function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }

    public function transcoding($filename, $iswin = null)
    {
        $encodes = ['UTF-8', 'GBK', 'BIG5', 'CP936'];
        $encoding = mb_detect_encoding($filename, $encodes);
        if ($encoding == 'UTF-8') {
            return $filename;
        }

        if (is_null($iswin)) {
            $iswin = DIRECTORY_SEPARATOR == '/';
        }
        //linux
        $encoding = mb_detect_encoding($filename, ['UTF-8', 'GBK', 'BIG5', 'CP936']);
        if ($iswin) { //linux
            $filename = iconv($encoding, 'UTF-8', $filename);
        } else { //win
            $filename = iconv($encoding, 'GBK', $filename);
        }
        return $filename;
    }

    public function unzipAndMove($filename)
    {
        $content = file_get_contents($filename);
        $filecontent = "<-filename-{$filename}-filename->" . $content;
        $fileSyncClient = $this->conf['FileSyncClient'];
        foreach ($fileSyncClient as $clientConf) {
            $this->sendFileContent($filecontent, $clientConf);
        }
    }

    public function onFinish($serv, $task_id, $param)
    {
        $_ENV['debug']->log('swoole:task:finish', $task_id);
    }

    public function sendToClient($filename)
    {
        $content = file_get_contents($filename);
        $filecontent = "<-filename-{$filename}-filename->" . $content;
        $fileSyncClient = $this->conf['FileSyncClient'];
        foreach ($fileSyncClient as $clientConf) {
            $this->sendFileContent($filecontent, $clientConf);
        }
    }

    public function sendFileContent($filecontent, $clientConf)
    {
        SCL:
        // $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
        // $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client = new \swoole_client(SWOOLE_TCP);
        if (!$client->connect($clientConf['host'], $clientConf['port'], -1)) {
            echo "connect failed. Error: {$client->errCode}" . PHP_EOL;
            $client->close();
            usleep(100000);
            goto SCL;
        }
        $client->set(array(
            'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0, //第N个字节是包长度的值
            'package_body_offset' => 4, //第几个字节开始计算长度
            'package_max_length' => 1024 * 1024 * 20, //协议最大长度
            'socket_buffer_size' => 1024 * 1024 * 200, //2M缓存区
        ));
        $data = pack('N', strlen($filecontent)) . $filecontent;
        $client->send($data);
    }
}

new SourceFileServer();
