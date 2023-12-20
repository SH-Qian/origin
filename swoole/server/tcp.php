<?php

//创建Server对象，监听 127.0.0.1:9501 端口。
$server = new Swoole\Server('127.0.0.1', 9501);

// 绑定参数
$server->set(array(
    'reactor_num'   => 2,     // 线程数
    'worker_num'    => 4,     // worker进程数
    'backlog'       => 128,   // 设置Listen队列长度
    'max_request'   => 50,    // 每个进程最大接受请求数
    'dispatch_mode' => 1,     // 数据包分发策略
));


/**
 * $fd 客户端连接唯一标识
 * $reactor_id 线程id
 * */

 //监听连接进入事件。
$server->on('Connect', function ($server, $fd ,$reactor_id) {
    echo "Client: {$fd}-{$reactor_id}-Connect.\n";
});


//监听数据接收事件。
$server->on('Receive', function ($server, $fd, $reactor_id, $data) {
    $server->send($fd, "Server: {$data}");
});

//监听连接关闭事件。
$server->on('Close', function ($server, $fd) {
    echo "Client: Close.\n";
});

//启动服务器
$server->start(); 
