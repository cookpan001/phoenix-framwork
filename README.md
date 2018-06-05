PHP Framework Based on Swoole
# 特点
- 单入口
- 无SQL
- 整合Http, Redis, WebSocket三种协议
# 需要安装以下扩展
- swoole
- redis[optional]
- mongodb[optional]

# 使用方法
1. 以samples里的composer.json为参考, 修改命名空间和所在目录
2. 执行composer create-project后，项目目录结构如下：
```
├── app
│   ├── Component
│   ├── Config
│   ├── Controller
│   ├── Data    [数据库，Redis, etc]
│   ├── Job
│   └── Queue
├── bin
├── composer.json
├── composer.lock
├── conf
│   └── application.ini
├── index.php
└── vendor
```

# 框架代码结构
```
├── README.md
├── composer.json
├── src
│   ├── framework
│   │   ├── base
│   │   │   ├── Bootstrap.php[启动类，设置各种通用常量]
│   │   │   ├── CoMysql.php [mysql的协程实现]
│   │   │   ├── Config.php
│   │   │   ├── Database.php[数据库使用的基类， 参考test/Data/*.php]
│   │   │   ├── GenSql.php  [trait, SQL生成类]
│   │   │   ├── Job.php     [简易任务系统]
│   │   │   ├── Log.php [日志处理类]
│   │   │   ├── Mysqli.php  [mysqli封装]
│   │   │   ├── Pdo.php     [Pdo封装]
│   │   │   ├── Redis.php   [Redis封装，可以使用redis扩展, predis, 及下面的redis客户端]
│   │   │   └── Watcher.php [观察者]
│   │   ├── pool
│   │   │   └── MysqlPool.php [TODO: 数据库连接池]
│   │   ├── route
│   │   │   ├── Dispatcher.php  [请求分发器，根据REQUEST_URI派发请求]
│   │   │   ├── Request.php     [封装请求]
│   │   │   └── Response.php    [封装返回]
│   │   ├── sys
│   │   │   ├── MyRedis.php [PHP实现的redis客户端]
│   │   │   └── RedisClient.php [PHP实现的redis客户端]
│   │   └── util
│   │       └── Helper.php
│   ├── samples
│   │   ├── application.ini[项目配置]
│   │   ├── composer.json[项目composer模板]
│   │   ├── index.php   [nginx和swoole入口]
│   │   ├── phoenix.sh  [启动脚本]
│   │   └── tables.json [分库分表配置]
│   ├── service
│   │   ├── SuperServer.php
│   └── web
│       ├── Debug.php   [页面调试工具入口]
│       └── debug.html
```
# 简单开始，一个简单的服务在test中
- cd test
- php index.php

# 代码调试工具, 可以在浏览器中调试代码
http://127.0.0.1:9501/_debug

# 服务器状态接口
http://127.0.0.1:9501/_stats

# 路由列表
http://127.0.0.1:9501/_routes

# HTTP调试模式, 打开调试模式后，所有的log都会输出在页面端
http://127.0.0.1:9501/?__DAVDIAN_DEBUG__=110