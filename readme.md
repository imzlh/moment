![Moment.php][1]

MomentPHP是一个简单又不简单的PHP HTTP框架
**简单**在只有4000行代码，只有一个主文件
**不简单**在全部都是异步，支持很多实用的功能

可以轻松地搭高性能PHP网站&WebSocket服务器、WebDAV服务器...

再搭配几行简单的代码，可以做到

 - 进程管理
    - aria2
    - clash
    - natmap
    - ...
 - PHP定时任务
    - DDNS
    - 服务器连通性汇报
    - ...
 - 应急远程管理
    - TerMoment(Moment终端)
    - WebRPC
 - 轻量级服务器
    - WebDAV
    - WebList
    - WebSocket
    - ...

不仅如此，我们内置了很多实用工具，如

 - 虚拟线程（**class** `vThread`+`go`）
 - 全异步IO（`fetch`/`open`）
 - 仿JS的管道类（**class** `pipe`）
 - 管道级进程（**class** `procPipe` + `popen`）
 - 类似于JS`await`的语法（`yield`和`Promise::await`）
 - 远程RPC调用（**class** `RPC`）
 - 一套式HTTP解决方案（**class** `HttpHandle`）
 - 快速执行PHP文件并输出到客户端（`run`）
 - 异步文件协议(`fs://`)
 - 简易的持久化数据库(`dbopen`)
 - 与浏览器联动的简易方法(**class** `RPC`)
 - ...

<a href="https://hi.imzlh.top/2024/05/15.cgi">参考手册在这里</a>

# 扩展

做到了这么多实用工具，必须露一手解决一些问题啊
比如我们有那么多进程服务，管理何必登陆终端？直接打开浏览器，输入
`http://IP/@taskmgr/`

![管理][2]

# 官网

我花了3个小时赶制了一个简单的官网
[HERE][3] https://moment.imzlh.top/

# 预览

列表
![2024-05-04T02:42:56.png][4]

管理面板
![2024-05-04T02:44:43.png][5]

管理面板：添加单元
![2024-05-04T02:44:55.png][6]

管理面板：终端
![2024-05-04T02:45:36.png][7]

WebDAV
![2024-05-04T02:46:55.png][8]

WebRPC
![RPC][9]


  [1]: https://hi.imzlh.top/usr/uploads/2024/04/492119779.webp
  [2]: https://hi.imzlh.top/usr/uploads/2024/05/164980683.png
  [3]: https://moment.imzlh.top/
  [4]: https://hi.imzlh.top/usr/uploads/2024/05/1515077970.png
  [5]: https://hi.imzlh.top/usr/uploads/2024/05/2175039177.png
  [6]: https://hi.imzlh.top/usr/uploads/2024/05/808513403.png
  [7]: https://hi.imzlh.top/usr/uploads/2024/05/2928520216.png
  [8]: https://hi.imzlh.top/usr/uploads/2024/05/3621928048.png
  [9]: https://hi.imzlh.top/usr/uploads/2024/05/652660842.png