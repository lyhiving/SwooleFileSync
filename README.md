## 依赖inotify和swoole扩展
使用inotify监听目录 文件变更时自动同步到远程服务器程

```
pecl install swoole
pecl install inotify
```

## 配置说明

```
$conf = [
    'SourceFileServer' => ['host' => '192.168.8.152', 'port' => 9657],//同步文件来源的主服务器
    'FileSyncClient'   => [//需要同步到的从服务器 端口设置保持一致
        ['host' => '192.168.8.98', 'port' => 9656],
        ['host' => '192.168.8.164', 'port' => 9656],
    ],
    'FileMonitorDir'   => '/data/synctest/',//文件动态监控的目录
    'cmd'              => '/usr/local/bin/php',//执行命令
];
```

## 使用说明

- 配置文件设置完成后 将代码放置到涉及的各个服务器

- 使用前 需要先开启从服务器client

```
 php daemon.php -t c -o start
```

- 所有从服务器client 开启后 启动主服务器并开启文件监控

```
 php daemon.php -t s -o start
```

## 服务关闭

```
 php daemon.php -t c -o stop

 php daemon.php -t s -o stop
```

该目录的文件仅为示范，生产环境使用时，还是在工作目录引入。假设文件与vendor目录在同一层。

下面的命令是可以用的
```
 cp daemon.php ../../../daemon.php
 cp hooks_unzipAndMoveSample.php ../../../hooks_unzipAndMove.php
 cp config.sample.php ../../../config.inc.php
 cp config.inc.sample.php config.inc.php
```

然后对应修改：
- 当前目录下的config.inc.php 的路径指向，这种情况下，默认路径是正确的。
- 工作目录下的config.inc.php 里面的unzipAndMoveSample 到 unzipAndMove，并针对相应的文件进行调整即可。
