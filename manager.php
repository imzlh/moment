<?php

use MonentDaemon\UnitStruct;

use function MomentAdaper\file_put_contents;
use function MomentCore\dbopen;
use function MomentCore\log;
use function MomentCore\open;
use function MomentCore\popen;

return function(\MomentCore\HttpHandle $handle){ 
if(!class_exists('\\MonentDaemon\\UnitStruct'))
    return $handle -> finish(500,'Daemon Process not found.');
$db = dbopen('manager');
if($db -> user || $db -> pw)
    if(!$handle -> auth($db -> user,$db -> pw,'MomentTaskMgrPanel'))
        return;
if(isset( $_GET['add'] )):  ?>
<!DOCTYPE html>
<html>
<head>
    <title>设置-TaskMGR</title>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <style>
        html {
            background: black;
            height: 100vh;
            color: white;
        }

        .btn {
            background-color: #c74ae3;
            border: solid .1rem transparent;
            color: white;
            padding: .4rem 1.2rem;
            text-decoration: none;
            display: inline-block;
            margin: .2rem 1rem;
            cursor: pointer;
            border-radius: .3rem;
            transition: all .2s;
        }

        body > div{
            margin: .5rem 1rem;
        }

        .btn:hover {
            border-color: #c74ae3;
            background-color: transparent;
            color: #c74ae3;
        }

        body {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 1.8rem;
            border-radius: .6rem;
            width: 24rem;
            min-height: 20rem;
            background-color: rgb(89, 91, 84);
        }

        #args>span>* {
            padding: .1rem .5rem;
            border-radius: 2rem;
            font-size: .8rem;
            background-color: rgb(39, 103, 176);
            color: white;
            display: inline-block;
            margin: .2rem;
        }

        div#args {
            margin: .5rem 0;
            background-color: white;
        }

        #args input {
            background: transparent;
            border: none;
            outline: none;
        }
        input[type=checkbox]{
            transform: scale(1.5);
        }
        div.input > span{
            display: inline-block;
            width: 6rem;
        }
        div.input > input{
            display: inline-block;
            width: 15rem;
            border: none;
            outline: none;
            padding: .25rem;
        }
    </style>
    <script>
        function submit(){
            // 合并所有的args
            var node = document.querySelectorAll('#args > span > *');
            var args = [];
            for (let i = 0; i < node.length; i++)
                args.push(node[i].innerText);

            // 构造JSON
            var json = {
                "$schema": "https://moment.imzlh.top/static/schema.json",
                "args": args,
                "name": document.getElementById('name').value,
                "shortname": document.getElementById('shortname').value,
                "watch": document.getElementById('watch').checked,
                "workdir": document.getElementById('workDir').value,
                "autoStart": document.getElementById('autoStart').checked
            };

            // 发送
            var xhr = new XMLHttpRequest();
            xhr.open('POST','?addUnit');
            xhr.setRequestHeader('Content-Type','application/json');
            xhr.send(JSON.stringify(json));
            xhr.onload = function(){
                if(xhr.status == 200) alert('添加成功');
                else alert('添加失败' + xhr.responseText);
            }
            xhr.onerror = function(){
                alert('xhr发送失败');
            }
        }
        window.onload = function () {
            var target = document.getElementById('args'),
                input = target.getElementsByTagName('input')[0],
                container = target.getElementsByTagName('span')[0];

            target.onclick = function () {
                input.focus();
            };

            input.onkeydown = function (ev) {
                if (ev.key == 'Enter') {
                    ev.preventDefault();
                    var element = document.createElement('div');
                    element.innerText = input.value;
                    element.onclick = function (ev) {
                        ev.stopPropagation();
                        element.remove();
                    }
                    input.value = '';
                    container.append(element);
                }
            }
        }
    </script>
</head>

<body>
    <h1>添加单元</h1>
    <div class="input">
        <span>短名称</span>
        <input required type="text" id="shortname" placeholder="test">
    </div>
    <div class="input">
        <span>名称</span>
        <input required type="text" id="name" placeholder="test">
    </div>
    <div class="input">
        <span>工作目录</span>
        <input type="text" id="workDir" value=".">
    </div>
    <div>
        <span>执行的命令</span>
        <div id="args">
            <span></span>
            <input type="text">
        </div>
    </div>
    <div class="check">
        <input type="checkbox" id="watch">
        <span>值守(daemon)</span>
    </div>
    <div class="check">
        <input type="checkbox" id="autoStart">
        <span>自启动</span>
    </div>
    <button class="btn" onclick="submit()">提交</button>
</body>

</html>
<?php
elseif(isset($_GET['connect'])):
    if(PHP_OS != 'Linux')
        return print('Windows端不支持Termoment');
    try{
        $ws = $handle -> ws();
        $proc = popen(['sh'],[
            'read' => true,
            'write' => true,
            'env' => [
                'TERM' => 'xterm-256color'
            ]
        ]);
        $ws -> onMessage(function(string $msg) use ($proc){
            $proc -> write($msg);
        });
        $ws -> on('close',fn() => $proc -> __destruct());
        $proc -> on('close',fn() => $ws -> close());
        $proc -> pipeTo($ws);
        log('{color_pink}T{/} New Remote Terminal');
    }catch(\Throwable $e){
        return $handle -> finish(500,$e -> getMessage());
    }
elseif(isset($_GET['terminal'])):
?>
<!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Web Termoment</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.5.0/xterm.min.css">
    </head>
    <body>
        <style>
            body{
                height: 100vh;
                width: 100vw;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: darkgray;
            }
        </style>
        <div id="terminal"></div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xterm/5.5.0/xterm.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-fit@0.10.0/lib/addon-fit.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/@xterm/addon-webgl@0.18.0/lib/addon-webgl.min.js"></script>
        <script>
            var term = new Terminal({
                rendererType: "webgl",
                convertEol: true,
                scrollback: 600,
                disableStdin: false,
                cursorBlink: true
            });
            var socket = new WebSocket(`ws://${location.host}${location.pathname}?connect`);
            var fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.loadAddon(new WebglAddon.WebglAddon());

            term.open(document.getElementById('terminal'));
            fitAddon.fit();

            socket.binaryType = 'arraybuffer';

            socket.onopen = function(){
                term.write('\x1b[0;0H\x1b[47;m\x1b[31m Connection established. \x1b[0m\n');
                setTimeout(function(){
                    term.write('\x1b[s\x1b[0;0H                                  \x1b[u');
                },3000);
            }

            socket.onmessage = function(msg){
                term.write('\x1b[92m');
                term.write(new Uint8Array(msg.data));
                term.write('\x1b[39m');
            }

            socket.onclose = function(){
                term.write('\x1b[s\x1b[0;0H\x1b[47;m\x1b[31m Connection closed. \x1b[0m\x1b[u');
                setTimeout(function(){
                    term.write('\x1b[s\x1b[0;0H                                  \x1b[u');
                },3000);
            }

            term.onData(function(data){
                if (data === '\r' || data === '\x0D') {
                    socket.send('\n');
                    term.write('\n');
                } else {
                    socket.send(data);
                }
                term.write(data);
            });

            window.addEventListener('resize', function(){
                term.fit();
            });
        </script>
    </body>
</html>
<?php elseif(isset( $_GET['addUnit'] )):
    if(
        $_SERVER['REQUEST_METHOD'] != 'POST' || 
        @$_ENV['content-type'] != 'application/json' || 
        !@$_ENV['content-length']
    )
        return $handle -> finish(400,'Not formed');

    if(!@file_put_contents(
        $_POST['source'] = __DIR__ . '/' . $_POST['shortname'] . '.task.json',
        json_encode($_POST)
    )) 
        return $handle -> finish(403,'Write Failed');

    UnitStruct::$units[] = $_POST;
    if(@$_POST['autostart'])
        UnitStruct::start((object)$_POST);
    else
        log("{color_green}I{/} Add NEW Service found: {$_POST['source']}");

    return $handle -> finish(200);

elseif(isset( $_GET['setUnit'] )): 
    $usr = $_POST['uname'];
    $pw = $_POST['pw'];
    if(!$usr || !$pw)
        return $handle -> finish(400,'uncompleted form');
    $db -> user = $usr;
    $db -> pw = $pw;
    $handle -> finish(301,'OK',[
        'location' => './'
    ]);
elseif(isset( $_GET['setting'] )):  ?>
<!DOCTYPE html>
<html>
    <head>
        <title>添加</title>
        <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
        <style>
            body{
                background-image: linear-gradient(50deg, #9df248, #a0f6a5);
                height: 100vh;
            }
            .body{
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                padding: 1.8rem;
                border-radius: .6rem;
                width: 20rem;
                min-height: 20rem;
                background-color: white;
            }
            div > *{
                display: inline-block;
                box-sizing: border-box;
            }
            div > span{
                width: 9rem;
            }
            div > input{
                width: 10rem;
                outline: none;
                border: none;
                padding: .5rem ;
                border-bottom: dotted .1rem rgb(105, 221, 126);
            }
            div > input:hover{
                border-bottom: dashed .1rem rgb(105, 221, 126);
            }
            div > input:focus{
                border-bottom: solid .1rem rgb(34, 186, 216)  ;
            }
            button {
                background-color: #1ecf23;
                border: solid .1rem transparent;
                color: white;
                padding: .4rem 1.2rem;
                text-decoration: none;
                display: inline-block;
                margin: 1rem 0;
                cursor: pointer;
                border-radius: .3rem;
                transition: all .2s;
            }
            button:hover {
                border-color: #1ecf23;
                background-color: white;
                color: #1ecf23;
            }
            
        </style>
    </head>
    <body>
        <form action="?setUnit" class="body" method="post" enctype="application/x-www-form-urlencoded">
            <h1>设置</h1>
            <div>
                <span>用户名</span>
                <input type="text" name="uname">
            </div>
            <div>
                <span>密码</span>
                <input type="password" name="pw">
            </div>
            <button type="submit">提交</button>
        </form>
    </body>
</html>
<?php else: ?>
<!DOCTYPE html>
<html>
    <head>
        <title>TaskMGR</title>
        <link rel='stylesheet' href='https://chinese-fonts-cdn.deno.dev/chinesefonts3/packages/cubic/dist/Cubic/result.css' /> 
        <style>
            body{
                display: flex;
                flex-direction: column;
                margin: 0;
                height: 100vh;
            }
            a{
                text-decoration: none;

            }
            .head{
                padding: 0 .5rem;
                flex-shrink: 0;
                background-color: rgb(207, 42, 207);
                color: white;
                display: flex;
            }
            .head a{
                display: block;
                padding: .5rem;
                border-radius: .2rem;
                color: inherit;
            }
            .head a:hover{
                background-color: rgba(147, 152, 153, 0.297);
            }
            .head svg{
                fill: currentColor;
                width: 1.5rem;
                height: 1.5rem;
            }
            .body{
                flex-grow: 1;
                display: flex;
            }
            .left{
                flex: 0 0;
                background-color: rgb(247 245 245);
                max-width: 20rem;
                min-width: 10rem;
            }
            .left > a{
                display: block;
                padding: .35rem .8rem;
            }
            .left a:hover{
                background-color: rgba(147, 152, 153, 0.525);
            }
            .left a[select]{
                border-left: solid .3rem rgb(66, 174, 236);
                background-color: #eeebeb;
                pointer-events: none;
            }
            .right{
                flex: 1 0;
                position: relative;
            }
            code{
                display: block;
                width: 100%;
                height: 100%;
                box-sizing: border-box;
                padding: .5rem;
                overflow: auto;
                font-family:'Cubic 11';
                background-color: #dfdede;
            }
            .right > div{
                position: absolute;
                bottom: 0;
                left: 1rem;
                right: 1rem;
                padding: 1rem;
                border-radius: .5rem .5rem 0 0;
                background-color: white;
                box-shadow: 0 0 .25rem lightgray;
                overflow: hidden;
                text-align: center;
            }
            .right div.info{
                position: absolute;
                top: 0;
                left: 0;
                padding: .25rem;
                text-align: center;
                background-color: rgb(76, 153, 215);
                color: white;
                width: 100%;
                animation: slideIn 3s linear forwards;
            }
            .right h1{
                display: inline-block;
                width: calc(100% - 8rem);
                overflow: hidden;
                margin: .5rem 0;
            }
            @keyframes slideIn{
                0%{
                    top: -2rem;
                }10%{
                    top: 0;
                }90%{
                    top: 0;
                }100%{
                    top: -2rem;
                }
            }
            a.btn {
                background-color: #c74ae3;
                border: solid .1rem transparent;
                color: white;
                padding: .4rem 1.2rem;
                text-decoration: none;
                display: inline-block;
                margin: .2rem 1rem;
                cursor: pointer;
                border-radius: .3rem;
                transition: all .2s;
            }
            .btn[disabled]{
                opacity: .6;
                pointer-events: none;
            }
            .btn:not([disabled]):hover {
                border-color: #c74ae3;
                background-color: white;
                color: #c74ae3;
            }
            .svg-text{
                text-align: left;
                display: inline-block;
            }
            .svg-text svg{
                display: inline-block;
                width: 1em;
                height: 1em;
                margin: 0 .5rem;
                transform: scale(1.6) translateY(10%);
                fill: currentColor;
            }
        </style>
    </head>
    <body>
        <div class="head">
            <h2 style="flex-grow: 1;margin: .35rem;">Moment+</h2>
            <a href="?add">
                <svg viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm.5-5a.5.5 0 0 0-1 0v1h-1a.5.5 0 0 0 0 1h1v1a.5.5 0 0 0 1 0v-1h1a.5.5 0 0 0 0-1h-1v-1Z"/>
                    <path fill-rule="evenodd" d="M12.096 6.223A4.92 4.92 0 0 0 13 5.698V7c0 .289-.213.654-.753 1.007a4.493 4.493 0 0 1 1.753.25V4c0-1.007-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1s-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4v9c0 1.007.875 1.755 1.904 2.223C4.978 15.71 6.427 16 8 16c.536 0 1.058-.034 1.555-.097a4.525 4.525 0 0 1-.813-.927C8.5 14.992 8.252 15 8 15c-1.464 0-2.766-.27-3.682-.687C3.356 13.875 3 13.373 3 13v-1.302c.271.202.58.378.904.525C4.978 12.71 6.427 13 8 13h.027a4.552 4.552 0 0 1 0-1H8c-1.464 0-2.766-.27-3.682-.687C3.356 10.875 3 10.373 3 10V8.698c.271.202.58.378.904.525C4.978 9.71 6.427 10 8 10c.262 0 .52-.008.774-.024a4.525 4.525 0 0 1 1.102-1.132C9.298 8.944 8.666 9 8 9c-1.464 0-2.766-.27-3.682-.687C3.356 7.875 3 7.373 3 7V5.698c.271.202.58.378.904.525C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777ZM3 4c0-.374.356-.875 1.318-1.313C5.234 2.271 6.536 2 8 2s2.766.27 3.682.687C12.644 3.125 13 3.627 13 4c0 .374-.356.875-1.318 1.313C10.766 5.729 9.464 6 8 6s-2.766-.27-3.682-.687C3.356 4.875 3 4.373 3 4Z"/>
                </svg>
            </a>
            <a href="?setting">
                <svg viewBox="0 0 16 16">
                    <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                    <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                </svg>
            </a>
        </div>
        <div class="body">
            <div class="left">
                <!-- 左边栏：所有单元 -->
                <?php
                    $id = @$_GET['id'];
                    $action = $_GET['action'];
                    /**
                     * @var UnitStruct
                     */
                    $service = is_numeric($id)
                        ? @UnitStruct::$units[(int)$id]
                        : null;
                    $running = @$service -> pipe && $service -> pipe -> status() -> alive;
                ?>
                <?php if(count(UnitStruct::$units) > 0):
                    foreach (UnitStruct::$units as $i => $unit): ?>
                        <a href="?id=<?php echo $i; ?>" 
                            <?php if(is_numeric($id) && $i == (int)$id) echo 'select'; ?>
                        ><?php echo $unit -> name ?></a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="?add">没有Unit，尝试添加一个吧</a>
                <?php endif; ?>
            </div>
            <div class="right">
                <!-- 输出日志 -->
                <code><?php
                    if($service){
                        if($service -> pipe) 
                            if(PHP_OS == 'Linux') array_walk($service -> pipe -> readSync(),fn(string $data) => 
                                    print(htmlspecialchars($data) . '<br>')
                                );
                            else echo 'Windows系统不支持异步读取进程输出，所以我们无法展示<br>不过你可以检查Monent的输出获取其内容';
                        else echo '服务尚未启动，点击"启动"这里才会有内容哦';
                    }else echo "没有找到指定的Unit，请在侧边栏选择";
                ?></code>
                <!-- 详细内容 -->
                <?php if($service): ?>
                    <div>
                        <?php if($action): ?>
                            <div class="info"><?php
                                $msg = '执行完成';
                                try{
                                    if($action == 'start'){
                                        if($running) $msg = '应用正在运行!';
                                        else UnitStruct::start($service) or $msg = '启动时出现错误';
                                    }elseif($action == 'stop')
                                        $running
                                            ? $service -> pipe -> __destruct()
                                            : $msg = '未在运行，无法停止';
                                    elseif($action == 'restart')
                                        $running
                                            ? $service -> pipe -> __destruct() and UnitStruct::start($service)
                                            : $msg = '未在运行，无法重启';
                                    else $msg = "未知指令 $action";
                                }catch(\Throwable $e){
                                    $msg = '错误: ' . $e -> getMessage();
                                    echo '<!--' . (string)$e . '-->';
                                }finally{
                                    echo $msg;
                                    echo "<script>setTimeout(() => location.replace('?id=$id'),3000);</script>";
                                }
                            ?></div>
                        <?php endif; ?>
                        <h1><?php echo "{$service -> name}"; ?></h1>
                        <?php if($running): ?>
                            <div class="svg-text" style="color: rgb(23, 195, 23);">
                                <svg viewBox="0 0 16 16">
                                    <path d="m11.596 8.697-6.363 3.692c-.54.313-1.233-.066-1.233-.697V4.308c0-.63.692-1.01 1.233-.696l6.363 3.692a.802.802 0 0 1 0 1.393z"/>
                                </svg>
                                运行中<br>(pid: <?php echo $service -> pipe -> status() -> pid ?>)
                            </div>
                        <?php else: ?>
                            <div class="svg-text" style="color: rgb(219, 72, 72);">
                                <svg viewBox="0 0 16 16">
                                    <path d="M5.5 3.5A1.5 1.5 0 0 1 7 5v6a1.5 1.5 0 0 1-3 0V5a1.5 1.5 0 0 1 1.5-1.5zm5 0A1.5 1.5 0 0 1 12 5v6a1.5 1.5 0 0 1-3 0V5a1.5 1.5 0 0 1 1.5-1.5z"/>
                                </svg>
                                未在运行
                            </div>
                        <?php endif; ?>
                        <div>
                        <a href="?action=stop&id=<?php echo $id ?>"
                            <?php if($action == 'stop' || !$running) echo 'disabled'; ?> 
                            class="btn"
                        >停止</a>
                        <a href="?action=start&id=<?php echo $id ?>"
                            <?php if($action == 'start' || $running) echo 'disabled'; ?> 
                            class="btn"
                        >启动</a>
                        <a href="?action=restart&id=<?php echo $id ?>"
                            <?php if($action == 'restart' || !$running) echo 'disabled'; ?> 
                            class="btn"
                        >重启</a>
                        <a onclick="document.location.reload()"
                            <?php if($action) echo 'disabled'; ?> 
                            class="btn">刷新</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </body>
</html>
<?php endif;  ?>
<?php } ?>