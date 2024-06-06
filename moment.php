#!/bin/env php
<?php declare(strict_types=1); ?>
<?php namespace MomentCore{
    /**
     * MomentPHP MainFile
     * MomentPHP是一个以MIT标准分发的自由软件
     * 使用PHP作为引擎实现动态网页，select()作为EventLoopCore实现异步
     * 
     * 目前可用的功能
     *  - FileServer
     *  - WebSocket
     *  - ASyncRequest
     * 
     * @license MIT
     * @version 1.3 修复大量错误
     * @copyright izGroup <2131601562@qq.com>
     * @link https://moment.imzlh.top
     */

    const VERSION = 1.3;

    const FONT_BOLD = "\033[1m";
    const FONT_DARK = "\033[2m";
    const FONT_UNDERLINE = "\033[4m";
    const FONT_BLINK = "\033[5m";
    const FONT_INVERT = "\033[7m";
    const FONT_HIDE = "\033[8m";

    const FONT_UNSET_BOLD = "\033[21m";
    const FONT_UNSET_DARK = "\033[22m";
    const FONT_UNSET_UNDERLINE = "\033[24m";
    const FONT_UNSET_BLINK = "\033[25m";
    const FONT_UNSET_INVERT = "\033[27m";
    const FONT_UNSET_HIDE = "\033[28m";
    const FONT_UNSET_ALL = "\033[0m";

    const COLOR_BLACK = "\033[30m";
    const COLOR_RED = "\033[31m";
    const COLOR_GREEN = "\033[32m";
    const COLOR_YELLOW = "\033[33m";
    const COLOR_BLUE = "\033[34m";
    const COLOR_PINK = "\033[35m";
    const COLOR_CRAN = "\033[36m";
    const COLOR_WHITE = "\033[97m";
    const COLOR_GRAY = "\033[90m";
    const COLOR_GRAY_LIGHT = "\033[37m";
    const COLOR_RED_LIGHT = "\033[91m";
    const COLOR_GREEN_LIGHT = "\033[92m";
    const COLOR_YELLOW_LIGHT = "\033[93m";
    const COLOR_BLUE_LIGHT = "\033[94m";
    const COLOR_PINK_LIGHT = "\033[95m";
    const COLOR_CRAN_LIGHT = "\033[96m";

    const COLOR_UNSET = "\033[39m";

    const BG_BLACK = "\033[40m";
    const BG_RED = "\033[41m";
    const BG_GREEN = "\033[42m";
    const BG_YELLOW = "\033[43m";
    const BG_BLUE = "\033[44m";
    const BG_PINK = "\033[45m";
    const BG_CRAN = "\033[46m";
    const BG_GRAY_LIGHT = "\033[47m";
    const BG_GRAY = "\033[100m";
    const BGT_RED_LIGH = "\033[101m";
    const BG_GREEN_LIGHT = "\033[102m";
    const BG_YELLOW_LIGHT = "\033[103m";
    const BG_BLUE_LIGHT = "\033[104m";
    const BG_PINK_LIGHT= "\033[105m";
    const BG_CRAN_LIGHT = "\033[106m"; 
    const BG_WHITE = "\033[107m";

    const BG_UNSET = "\033[49m";

    /**
     * 信号传递组件
     */
    class Signal extends \Error{

        /**
         * @var mixed 信号传递的内容
         */
        protected $content;

        public function __construct(mixed $data = null) {
            $this -> content = $data;
        }

        public function getData(){
            return $this -> content;
        }

        public function __toString():string{
            return 'NEW SIGNAL';
        }
    }

    class Event{
        private $events = [];

        /**
         * 监听一个事件
         * 类似于JS的`addEventListener`
         * 
         * @param string $ev 事件名称
         * @param callable 事件
         * @return callable
         */
        public function on(string $ev,callable $cb){
            if(!array_key_exists($ev,$this -> events))
                $this -> events[$ev] = [];
            $id = count($this -> events[$ev]);
            $this -> events[$ev][$id] = $cb;
            return function() use (&$id,$ev){
                if($id === null)
                    throw new \TypeError('Already aborted.');
                unset($this -> events[$ev][$id]);
                $id = null;
            };
        }

        /**
         * 同`on`，但回调执行一次
         * 
         * @param string $ev 事件名称
         * @param callable 事件
         */
        public function once(string $ev,callable $cb){
            if(!array_key_exists($ev,$this -> events))
            $this -> events[$ev] = [];
            $id = count($this -> events[$ev]);
            $this -> events[$ev][$id] = function() use ($cb,$id,$ev){
                unset($this -> events[$ev][$id]);
                $cb();
            };
        }

        /**
         * 触发事件
         * 
         * @param string $ev 事件名
         * @param mixed $args 传递的参数
         */
        public function _trig(string $ev,array $args = []){
            if(!array_key_exists($ev,$this -> events))
                return ;
            else
                foreach ($this -> events[$ev] as $cb) 
                    Promise::call($cb,...$args);
        }
    }

    /**
     * Promise 仿JS 异步方案
     * @copyright izGroup
     * @link https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Reference/Global_Objects/Promise
     */
    class Promise{

        private $resolve = [];
        private $reject = [];
        private $finally = [];

        /**
         * @var int 存储执行状态值
         */
        public $status = self::PENDING;

        /**
         * @var mixin Promise返回值，用于应对提前兑现现象
         */
        private $result;

        const PENDING = 0;
        const RESOLVE = 1;
        const REJECT = 2;

        /**
         * 安全调用函数，返回Promise
         * @param callable $cb 执行的函数
         * @param array ...$args 传递的参数
         */
        static function call(callable $cb,...$args){
            $prom = new Promise($rs,$rj,true);
            try{
                Promise::await(call_user_func_array($cb,$args))
                    -> then($rs)
                    -> catch($rj);
            }catch(\Throwable $e){
                $rj($e);
            }
            return $prom;
        }

        /**
         * 通过迭代器实现await方法
         * 如果不使用`Promise::await`，迭代器将无法继续执行
         * 只需要在顶层使用即可，调用的函数可以使用`yield`代替`await`
         * ```
         * function aaa(){ yield async_func() }
         * function b(){ yield Promise::await(aaa()) }
         * Promise::await(b()) -> then(...)
         * ```
         * **我们强烈推荐使用 vThread 代替迭代器循环！！！**
         * 
         * @param mixed $async 异步内容，允许函数、函数返回值和Promise
         * @param ?callable $onext 当获取内容前的回调（初次不会触发）
         * @return Promise
         */
        static function await(mixed $async,?callable $onext = null){
            /**
             * @var callable next
             */
            $next = null;
            
            // 函数直接调用
            if(is_callable($async))
                return self::call($async);
            
            // 返回的Promise
            $return = new Promise($rs,$rj);

            // Generator下一个
            $next = function() use ($async,&$next,$rs,$rj,$onext){
                // 返回的数据
                $await = $async -> current();
                // 回调函数
                $nexts = function($dat) use ($async,$rj,$next){
                    try{
                        $async -> send($dat);
                        $next();
                    }catch(\Throwable $e){
                        return $rj($e);
                    }
                };
                // 调用事件
                if(\is_callable($onext)) $onext($await);
                // 完成：已经return
                if(!$async -> valid()){
                    $await = $async -> getReturn();
                    // 返回Promise?
                    if($await instanceof Promise)
                        $await -> then($rs) -> catch(fn($e) => $rj($e));
                    // 传递Generator?
                    elseif($await instanceof \Generator)
                        self::await($await) -> then($rs) -> catch(fn($e) => $rj($e));
                    else
                        $rs($await);
                // 传递Promise: 等待并继续
                }elseif($await instanceof Promise)
                    $await -> then($nexts) -> catch(fn($e) => $async -> throw($e));
                // 传递Generator: 等待并继续
                elseif($await instanceof \Generator)
                    self::await($await) -> then($nexts) -> catch(fn($e) => $async -> throw($e));
                // 没有匹配：依旧直接传递
                else
                    try{
                        trigger_error('Passing non-awaitable data to "yield"',E_USER_NOTICE);
                        $async -> send($await);
                        $next();
                    }catch(\Throwable $e){
                        return $rj($e);
                    }
            };

            // 迭代器: 转Promise
            if($async instanceof \Generator)
                $next();
            // Promise: 直接传递
            elseif($async instanceof Promise)
                return $async;
            // 非异步：直接实现
            else
                $rs($async);

            return $return;
        }

        /**
         * 等待直到`Promise`全部兑现
         * @link https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Reference/Global_Objects/Promise/all
         * @param array $promises Promise对象
         * @return Promise 所有Promise兑现时才兑现的`Promise`
         */
        static function all(...$promises){
            $prom = new Promise($rs,$rj);
            $len = \count($promises) -1;
            $then = function(mixed $data,bool $status,int $n) use (&$rs,&$result,$len){
                $result[] = ['ok' => $status,'result' => $data];
                // 已经全部实现
                if($len == $n)
                    $rs($result);
            };
            $result = [];
            foreach ($promises as $i => $promise) {
                $promise -> then(fn($data) => $then($data,true,$i))
                     -> catch(fn($data) => $then($data,false,$i));
            }
            return $prom;
        }

        /**
         * 等待任何一个`Promise`兑现
         * @link https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Reference/Global_Objects/Promise/any
         * @param array $promises Promise对象
         * @return Promise 任何一个Promise兑现时才兑现的`Promise`
         */
        static function any(...$promises){
            $prom = new Promise($rs,$rj);
            foreach ($promises as $promise) {
                $promise -> then(fn($data) => $rs($data))
                     -> catch(fn($data) => $rj($data));
            }
            return $prom;
        }

        /**
         * 像Javascript一样创建新Promise
         * ```
         * yield Promise::new(fn($rs) => delay(1000) -> then($rs));
         * ```
         * @link https://developer.mozilla.org/zh-CN/docs/Web/JavaScript/Reference/Global_Objects/Promise/Promise
         * @param callable $func 自执行函数
         * @return Promise
         */
        static function new(callable $func){
            $return = new Promise($rs,$rj);
            $func($rs,$rj);
            return $return;
        }

        /**
         * 创建一个Promise，使用引用方式传递回调
         * @param ?callable $rs 解决时的回调
         * @param ?callable $rj 错误时的回调
         */
        public function __construct(mixed &$rs,mixed &$rj,bool $noerror = false) {
            // finally
            $finish = function(){
                if($this -> status != self::PENDING)
                    return false;
                foreach ($this -> finally as $finally) try{
                    \call_user_func($finally);
                }catch(\Throwable){}
            };
            // then
            $rs = function($result = null) use ($finish){
                if($this -> status != self::PENDING)
                    return false;
                $finish();
                if($this -> resolve)
                    foreach ($this -> resolve as $rs_handle)
                        \call_user_func($rs_handle,$result);
                $this -> result = $result;
                $this -> status = self::RESOLVE;
            };
            // catch
            $rj = function($result) use ($finish,$noerror){
                if($this -> status != self::PENDING)
                    return false;
                $finish();
                if(count($this -> reject) == 0 && !$noerror)
                    trigger_error('Uncaught Promise' . $result -> __toString(),E_USER_ERROR);
                foreach ($this -> reject as $rj_handle)
                    call_user_func($rj_handle,$result);
                $this -> status = self::REJECT;
            };
        }

        /**
         * Promise兑现时的回调
         * 允许提前兑现现象时依旧有输出
         * 
         * @param callable $cb 回调
         * @return Promise
         */
        public function then(callable $cb){
            if($this -> status == self::RESOLVE)
                $cb($this -> result);
            else $this -> resolve[] = $cb;
            return $this;
        }

        /**
         * Promise错误时的回调
         * 如果没有设置任何回调，将会直接变成全局错误
         * 但是我们不支持链式调用，所有的返回值将舍弃
         * 
         * @param callable $cb 回调
         * @return Promise
         */
        public function catch(callable $cb){
            @$this -> reject[] = $cb;
            return $this;
        }

        /**
         * Promise 完成时的回调，无论是否错误
         * 但是我们不支持链式调用，所有的返回值将舍弃
         * 
         * @param callable $cb 回调
         * @return Promise
         */
        public function finally(callable $cb){
            if($this -> status == self::PENDING)
                $this -> finally[] = $cb;
            else $cb();
            return $this;
        }
    }

    /**
     * 最简单却可以select后调用的Pipe
     */
    interface PipeFactory{
        function _read():void;
        function _write():void;
        function _status():bool;
    }

    /**
     * 可以正常读写的pipe
     * 只需要实现最基础的Pipe即可
     */
    interface PipeLiked{
        function read(int $length = 0):Promise;
        function write(string $data):Promise;
        function status():statStruct;
    }

    class statStruct extends \stdClass{

        /**
         * @var int 读取缓冲区缓冲的内容长度
         */
        public $read = 0;

        /**
         * @var int 写入缓冲区缓冲的内容长度
         */
        public $write = 0;
        
        /**
         * @var bool 是否可读
         */
        public $readable = false;
        
        /**
         * @var bool 是否可写
         */
        public $writable = false;
                
        /**
         * @internal
         * @var bool 
         */
        public $_read = false;

        /**
         * @internal
         * @var bool 
         */
        public $_write = false;

        /**
         * @var bool 是否已经完全不可读
         */
        public $eof = false;

        /**
         * @var bool 是否存活（可用）
         */
        public $alive = true;
    }

    /**
     * Moment仿JS Stream的类
     */
    interface Pipe extends PipeLiked,PipeFactory{}

    /**
     * 包装好的Pipe，一般可以直接二次包装使用
     */
    abstract class BaselinePipe extends Event implements Pipe{

        const RM_READLINE = 1;
        const RM_READONCE = 2;
        const RM_READLEN = 3;
        const RM_READLEN_STREAM = 4;

        const T_READABLE = 0b10;
        const T_WRITABLE = 0b01;
        const T_RW = 0b11;

        /**
         * @var resource 当前处理的Stream
         */
        protected $stream;

        /**
         * @var array 写入队列
         */
        protected $write_queue = [];

        /**
         * @var int 写入缓冲区长度
         */
        protected $write_len = 0;

        /**
         * @var array 读取缓冲区
         */
        protected $read_queue = [];

        /**
         * @var string 读取缓冲区
         */
        protected $read_temp = '';

        /**
         * @var callable 达到数据量后传递的Promise
         */
        protected $reader;

        /**
         * @var int 最后一次有活动流量的时间
         */
        // protected $time = 0;

        /**
         * @var callable 用于限制读取，返回false表示无法读取
         */
        public $read_filter;

        /**
         * @var statStruct 当前状态，用于代替`_status()`
         */
        private $status;
        
        /**
         * @var int 管道模式参数
         */
        protected $mode;

        /**
         * @var int 当前Pipe的读取缓冲区buffer大小
         */
        public $write_buffer_size;

        /**
         * @var int 当前Pipe的写入缓冲区buffer大小
         */
        public $read_buffer_size;

        /**
         * 创建Handler
         * 注意，不应该由用户自行创建。此函数由Eventloop自动调用
         * @param resource $client 可读/写/读写的stream
         * @param int $flag 管道类型，支持可写/可读/读写
         */
        public function __construct($client,int $flag = self::T_RW) {
            $this -> stream = $client;
            // $this -> time = time();
            $this -> mode = $flag;
            $this -> status = new statStruct;
            $this -> read_buffer_size = EventLoop::$READ_BUFFER_SIZE /2;
            $this -> write_buffer_size = EventLoop::$WRITE_BUFFER_SIZE /2;
        }

        /**
         * 向对象写入内容
         * 使用非阻塞IO，无需担心写入卡死
         * @param string $data 写入的数据
         * @return Promise 写入后立即兑现的Promise
         */
        public function write(string $data):Promise{
            // 管道存活
            if(!$this -> status -> alive)
                throw new \Error('Pipe has already been closed.');

            // 可写管道
            if(($this -> mode & self::T_WRITABLE) == 0)
                throw new \Error('The pipe is not writable');

            // 加入队列
            $prom = new Promise($rs,$rj);
            $len = strlen($data);
            $this -> write_queue[] = [$rs,$rj,$data,$len];
            $this -> write_len += $len;

            return $prom;
        }

        /**
         * 读取指定大小内容，将阻塞直到数据足够，但是也有例外
         *  - 如果已经关闭
         *    - 如果还有数据读取且不满足大小，将返回所有数据
         *    - 如果全部读完将抛出`Error`
         *  - 如果没有关闭
         *    - 如果`$byte`参数数字小于0，立即输出全部缓冲区
         * @param int $byte 读取长度，-1表示读取缓冲区内所有内容
         */
        public function read(int $byte = -1):Promise{
            // 管道可读
            if($this -> status -> eof || ($this -> mode & self::T_READABLE) == 0)
                throw new \Error('The pipe is not readable');

            // 异步返回
            $prom = new Promise($rs,$rj);
            $this -> read_queue[] = [
                $byte == -1 ? self::RM_READONCE : self::RM_READLEN,
                $rs,$rj,
                $byte
            ];
            return $prom;
        }

        /**
         * 用于去除换行符
         */
        static function _trim(string $str,bool $left = true){
            if($str == '') return $str;
            if($left)
                if($str[0] == "\r" || $str[0] == "\n")
                    return substr($str,1);
                else return $str;
            else
                if($str[$len = (strlen($str) -1)] == "\r" || $str[$len] == "\n")
                    return substr($str,0,$len);
                else return $str;
        }

        /**
         * 读取一行文本，同时兼容Windows与Linux的特殊换行符
         * 如果对象已经关闭，将返回全部缓存的数据
         */
        public function readline(){
            // 管道可读
            if($this -> status -> eof || ($this -> mode & self::T_READABLE) == 0)
                throw new \Error('The pipe is not readable');

            // 加入队列
            $prom = new Promise($rs,$rj);
            $this -> read_queue[] = [self::RM_READLINE,$rs,$rj,true];

            // 异步
            return $prom;
        }

        /**
         * 写入到一个Pipe中
         * 如果读完了还没有到达期望的数据量，将抛出错误
         * 
         * @link https://developer.mozilla.org/zh-CN/docs/Web/API/ReadableStream/pipeTo
         * @param BaselinePipe|resource $handle 写入的Pipe
         * @param int $expect 希望写入的大小
         */
        public function pipeTo($handle,int $expect = -1):Promise{
            // 管道可写
            if($this -> status -> eof || $this -> mode & self::T_READABLE == 0)
                throw new \Error('The pipe is not readable');

            // 任务uid
            $id = count($this -> read_queue);
            
            // 异步读取
            $promise = new Promise($rs,$rj);

            // 对方是管道
            if($handle instanceof self):
                
                // 是否可写决定当前是否可读
                $this -> read_filter = fn() =>
                    $handle -> status() -> writable;
                // 加入队列
                $this -> read_queue[$id] = [
                    self::RM_READLEN_STREAM,
                    $rs,$rj,
                    $expect,
                    // chunk回调
                    fn($chunk) => $handle -> write($chunk)
                ];
                // 同步关闭
                $cancel = $handle -> on('close',function() use ($rj,$id){
                    unset($this -> read_queue[$id]);
                    $rj();
                });
                $promise -> then(fn() => $cancel($this -> read_filter = null));
                // 同步对方pipe宽度
                $handle -> write_buffer_size = $this -> read_buffer_size;
            // 对方是原生Stream
            elseif(is_resource($handle)):
                // 不阻塞
                stream_set_blocking($handle,false);
                
                // 写入缓冲区
                $unwrited_temp = '';
                $this -> read_filter = fn() =>
                    strlen($unwrited_temp) <= $this -> write_buffer_size;

                $this -> read_queue[$id] = [
                    self::RM_READLEN_STREAM,
                    $rs,$rj,
                    $expect,
                    // chunk回调
                    function($chunk) use (&$unwrited_temp,$handle,$id,$rj){
                        // 是否存活
                        if(!is_resource($handle)) goto error;

                        // 尝试写入
                        $unwrited_temp .= $chunk;
                        $writed = @fwrite($handle,$unwrited_temp);

                        // 写入失败
                        if($writed === false)
                            goto error;

                        // 裁剪
                        $unwrited_temp = substr($unwrited_temp,$writed);
                        return;

                        error:{
                            unset($this -> read_queue[$id]);
                            $this -> read_filter = null;
                            return $rj(new \Error('write failed: Pipe error'));
                        }
                    }
                ];

                // 同步stream宽度
                stream_set_write_buffer($handle,$this -> read_buffer_size);

                // 清空拦截器
                $promise -> then(fn() => $this -> read_filter = null);
            endif;
            return $promise;
        }

        /**
         * 连续接接收数据
         */
        public function onMessage(callable $cb,int $limit = -1){
            $prom = new Promise($rs,$rj);
            $id = count($this -> read_queue);
            $this -> read_queue[$id] = [
                self::RM_READLEN_STREAM,
                $rs,$rj,
                $limit,
                $cb
            ];
            return $prom;
        }

        /**
         * 获取pipe状态(缓存后)
         * @return statStruct
         */
        public function status():statStruct{
            return $this -> status;
        }

        /**
         * 此函式应该由EventLoop调用，表示可写
         */
        public function _write():void{
            $i = 0;
            foreach ($this -> write_queue as &$writer) {
                if(!$writer) continue;
                // $writer = [{Promise:rs}, {Promise:rj}, {string:data}, {int:datalen}]
                $writed = fwrite($this -> stream,$writer[2],$writer[3]);
                // 写入失败
                if($writed === false){
                    fclose($this -> stream);
                    return;
                }
                $this -> write_len -= $writed;
                // 写入不足: 下次Loop继续写
                if($writer[3] != $writed){
                    $writer[2] = substr($writer[2],$writed);    // 减少数据
                    $writer[3] -= $writed;                      // 减小长度
                    $i ++;
                    break;
                }
                // 写入成功: 销毁
                $writer[0]();
                $writer = null;
            }
            // 一个都没有: 清空
            if($i == 0){
                $this -> write_queue = [];
                $this -> _trig('empty');
            }
            // $this -> time = time();
        }

        static function findLine(string $data){
            $pos = strpos($data, "\n");
            if (false === $pos) $pos = strpos($data, "\r");
            return $pos;
        }

        /**
         * 此函式应该由EventLoop调用，表示可读
         */
        public function _read():void{
            $i = 0;
            foreach ($this -> read_queue as $ii => &$reader) {
                // $writer = [{int:mode}, {Promise:rs}, {Promise:rj}, ...]
                if(!$reader) continue;
                $i ++;
                switch ($reader[0]) {
                    // ...void
                    case self::RM_READONCE:
                        // 读取全部
                        $reader[1](
                            $this -> read_temp .
                            fread($this -> stream,$this -> read_buffer_size)
                        );
                        $this -> read_temp = '';
                        $reader = NULL;
                    return;
                    
                    // ...{int:length}
                    case self::RM_READLEN:
                        $require = $reader[3] - strlen($this -> read_temp);
                        // 缓冲区数据太多
                        if($require <= 0){
                            $data = substr($this -> read_temp,0,$reader[3]);
                            $this -> read_temp = substr($this -> read_temp,$reader[3]);
                        // 正常读取数据
                        }else{
                            $data = fread($this -> stream,min($this -> read_buffer_size,$require));
                            if(!$data) return;
                            // 数据长度
                            $dlen = strlen($data);
                            // 数据充足
                            if($dlen == $require || feof($this -> stream)){
                                $data = $this -> read_temp . $data;
                                $this -> read_temp = '';
                            // 数据量不足
                            }else{
                                // 写入到缓冲区
                                $this -> read_temp .= $data;
                                // 减少需读量
                                $reader[3] -= $dlen;
                                // 等待下一次
                                return;
                            }
                        }
                        
                        // 返回数据
                        $reader[1]($data);
                        // 善后
                        $reader = NULL;
                    break;

                    // ...{int:length}, {callable:onmessage}
                    case self::RM_READLEN_STREAM:
                        $require = $reader[3] - strlen($this -> read_temp);
                        // 无限读取
                        if($reader[3] == -1){
                            $data = $this -> read_temp . fread($this -> stream,$this -> read_buffer_size);
                            $reader[4]($data);  // 返回数据
                            $this -> read_temp = '';
                            return;
                        // 缓冲区数据太多
                        }elseif($reader[3] != -1 && $require <= 0){
                            $data = substr($this -> read_temp,0,$reader[3]);
                            $this -> read_temp = substr($this -> read_temp,$reader[3]);
                            $reader[4]($data);  // 返回数据
                            $reader[1]();       // 完成
                            break;
                        // 正常读取数据
                        } else {
                            $data = fread($this->stream, min($this->read_buffer_size, $require));
                            // 数据长度
                            if (!$data) return;
                            $dlen = strlen($data);
                            // 数据充足
                            if ($dlen == $require) {
                                $data = $this->read_temp . $data;
                                $this->read_temp = '';
                                $reader[4]($data);  // 返回数据
                                $reader[1]();       // 完成
                                break;
                            } else {
                                // 写入到缓冲区
                                $reader[4]($this->read_temp . $data);
                                $this->read_temp = '';
                                // 减少需读量
                                if ($reader[3] != -1) $reader[3] -= $dlen;
                                // 等待下一次
                                return;
                            }
                        }
                        
                        $reader[4]($data);  // 返回数据
                        $reader[1]();       // 完成
                        // 善后
                        $reader = NULL;
                    break;

                    // ...{bool:waiting}
                    case self::RM_READLINE:
                        if($reader[3]){
                            $reader[3] = false;
                            // 第一次读
                            $pos = self::findLine($this -> read_temp);
                            $data = $this -> read_temp;
                            if($pos !== false) goto find_pos;
                        }
                        // 正常读取数据
                        $data = fread($this -> stream,$this -> read_buffer_size);
                        if(!$data) return;
                        else $data = $this -> read_temp . $data;

                        // 找到回车
                        $pos = self::findLine($data);
                        if(false === $pos) return;
                        
                        // 开始响应
                        find_pos:{
                            // 完成使命
                            $this -> read_temp = self::_trim(substr($data,$pos),true);
                            $data = self::_trim(substr($data, 0, $pos), false);
                            $this -> read_filter = null;
                            $reader[1]($data);
                            $reader = NULL;
                            break;
                        }
                    return;
                    
                    default:
                        $reader = NULL;
                        throw new \Error('unknown readtype: ' . $reader[0]);
                    break;
                }
            }

            // 清理垃圾
            if($i === 0)
                $this -> read_queue = [];
            // $this -> time = time();
        }

        /**
         * 获取缓冲区状态
         */
        function _status():bool{
            $r = $this -> status;
            $vaild = is_resource($this -> stream);

            // 存活检测
            if(!$vaild || !$this -> __alive()){
                // 强制关闭
                if($vaild)
                    fclose($this -> stream);

                // 触发事件
                if($r -> alive){
                    $this -> reader = null;
                    $this -> _trig('close');
                }
                return false;
            }

            // meta
            $meta = stream_get_meta_data($this -> stream);

            // 可读
            if($this -> mode & self::T_READABLE){
                $r -> read = strlen($this -> read_temp) + $meta['unread_bytes'];
                $r -> readable = $r -> read > 0;
                $r -> _read = $this -> read_filter 
                    ? call_user_func($this -> read_filter)// 调用自定义拦截器
                    : count($this -> read_queue) > 0;     // 队列中有任务

                // 已经到末端
                if(@$meta['eof'] && !$r -> eof){
                    $this -> _trig('eof');
                    $r -> eof = true;
                }
            }

            // 可写
            if($this -> mode & self::T_WRITABLE){
                $r -> write = $this -> write_len;
                $r -> _write = count($this -> write_queue) > 0;
                $r -> writable = $this -> write_len < $this -> write_buffer_size /2;
            }

            return true;
        }
        
        /**
         * 可扩展接口：获取是否已经关闭
         */
        abstract function __alive():bool;

        /**
         * 关闭IO
         */
        public function __destruct(){
            if(!is_resource($this -> stream))
                return;
            if($this -> status -> write > 0)
                $this -> once('empty', fn() => fclose($this -> stream));
            else fclose($this -> stream);
        }
    }

    /**
     * 文件流管道
     */
    class FilePipe extends BaselinePipe{

        /**
         * 移动起始点
         * @return bool
         */
        function seek(int $len,int $mode = SEEK_SET){
            return fseek($this -> stream,$len,$mode);
        }

        /**
         * 获取当前指针位置
         * @return int
         */
        function tell(){
            return ftell($this -> stream);
        }

        /**
         * 获取是否已经关闭
         */
        public function __alive():bool{
            return true;
        }

        /**
         * 获取文件信息
         */
        public function stat(){
            return fstat($this -> stream);
        }

        /**
         * 锁定文件
         */
        public function lock(int $type = LOCK_EX){
            return flock($this -> stream,$type);
        }
    }

    /**
     * 包装异步IO
     */
    class AsyncIOWrapper{
        /**
         * @var resource 上下文选项
         */
        public $context;

        /**
         * @var FilePipe 处理类
         */
        private $stream;

        /**
         * 重命名
         */
        public function rename(string $path_from, string $path_to): bool{
            return 0 == count(\Fiber::suspend(move($path_from,$path_to)));
        }

        /**
         * 关闭
         */
        public function stream_close(){
            return $this -> stream -> __destruct();
        }

        /**
         * 已经读完
         */
        public function stream_eof(){
            return $this -> stream -> status() -> eof;
        }

        /**
         * 同步缓冲区
         */
        public function stream_flush(): bool{
            if(!$this -> stream -> status() -> alive)
                return false;
            $prom = new Promise($rs,$rj);
            $this -> stream -> on('empty',$rs);
            \Fiber::suspend($prom);
            return true;
        }

        /**
         * 假函数，设置参数
         */
        public function stream_set_option(){
            return true;
        }

        /**
         * 获取状态
         */
        public function stream_stat(){
            return $this -> stream -> stat();
        }

        /**
         * 获取URL状态
         */
        public function url_stat(string $path){
            return stat(self::parseURL($path));
        }

        static function parseURL($path){
            if(!preg_match('/^fs:\/\/((?:[a-z]+:\/\/)?[^?*|\<>\']+)$/i',$path,$match))
                throw new \Error('illegal pattern');
            return preg_replace('/[\\/\\\\]+/','/',$match[1]);
        }

        /**
         * 打开stream
         */
        public function stream_open(
            string $path,
            string $mode
        ): bool{
            try{
                $this -> stream = open(self::parseURL($path),$mode);
                return true;
            }catch(\Throwable $e){
                trigger_error('Unable to open [' . $path . ']: ' . $e -> getMessage(),\E_USER_WARNING);
                return false;
            }
        }

        /**
         * 异步读取
         */
        public function stream_read(int $count): string|false{
            return \Fiber::suspend($this -> stream -> read($count));
        }

        /**
         * 偏移到指定位置
         */
        public function stream_seek(int $offset, int $whence = SEEK_SET): bool{
            return $this -> stream -> seek($offset,$whence);
        }
        
        /**
         * 获取偏移位置
         */
        public function stream_tell(): int{
            return $this -> stream -> tell();
        }
        
        /**
         * 写入数据并同步
         */
        public function stream_write(string $data): int{
            \Fiber::suspend($this -> stream -> write($data));
            return strlen($data);
        }

        /**
         * 自动关闭
         */
        public function __destruct(){
            if(!$this -> stream) return;
            $this -> stream -> __destruct();
        }
    }
    if (in_array('fs', stream_get_wrappers()))
        stream_wrapper_unregister('fs');
    // 注册异步文件系统IO
    stream_wrapper_register('fs','\\MomentCore\\AsyncIOWrapper');

    /**
     * 用于读取单一可读管道
     */
    class ReadaonlyChildPipe implements PipeFactory{
        /**
         * @param resource 对象
         */
        private $pipe;

        /**
         * @param callable 传递数据的回调
         */
        private $postman;

        /**
         * 初始化一个影子管道
         * @param resource $pipe 需要读取的管道
         * @param callable $cb 传递数据的回调
         */
        public function __construct($pipe,callable $cb){
            $this -> pipe = $pipe;
            $this -> postman = $cb;
        }

        /**
         * 是否存活
         */
        public function _status():bool{
            return is_resource($this -> pipe);
        }

        /**
         * 可读
         */
        public function _read(): void{
            call_user_func($this -> postman,fread($this -> pipe,EventLoop::$READ_BUFFER_SIZE));
        }
        
        /**
         * 可写
         */
        public function _write(): void{}

        /**
         * 状态
         */
        public function status():statStruct{
            $c = new statStruct();
            $c -> readable = true;
            $c -> _read = true;
            return $c;
        }
    }

    /**
     * 进程管道，支持日志化操作
     */
    class ProcPipe extends Event implements Pipe{

        const MAX_LINE = 20;
        const MAX_PER_LINE = 2 * 1024;
        const MAX_STDIN_BUFFER = 16 * 1024;
        const POLL_INTERVAL = 1 * 1000;

        const SIGHUP = 1;
        const SIGINT = 2;
        const SIGQUIT = 3;
        const SIGTRAP = 5;
        const SIGABRT = 6;
        const SIGKILL = 9;
        const SIGUSR1 = 10;
        const SIGUSR2 = 12;
        const SIGPIPE = 13;
        const SIGALRM = 14;
        const SIGTERM = 15;
        const SIGSTKFLT = 16;
        const SIGCHLD = 17;
        const SIGCONT = 18;
        const SIGSTOP = 19;
        const SIGTSTP = 20;
        const SIGTTIN = 21;
        const SIGTTOU = 22;
        const SIGURG = 23;
        const SIGXCPU = 24;
        const SIGXFSZ = 25;
        const SIGVTALRM = 26;
        const SIGPROF = 27;
        const SIGWINCH = 28;
        const SIGIO = 29;
        const SIGSYS = 31;

        /**
         * @var array 全部ProcPipe
         */
        static $allproc = [];

        /**
         * @var resource 当前处理的Proc
         */
        protected $proc;

        /**
         * @var ?resource STDIN
         */
        protected $stdin;

        /**
         * @var ?ReadaonlyChildPipe STDOUT
         */
        protected $stdout;

        /**
         * @var ?ReadaonlyChildPipe STDERR
         */
        protected $stderr;

        /**
         * @var string 写入缓冲区
         */
        protected $input = '';

        /**
         * @var array 读取缓冲区
         */
        protected $log = [];

        /**
         * @var string STDERR缓冲区
         */
        protected $stderr_tmp = '';

        /**
         * @var string STDOUT缓冲区
         */
        protected $stdout_tmp = '';

        /**
         * @var string pipeTo缓冲区
         */
        protected $pipeCache = '';

        /**
         * @var PipeLiked pipeTo对象
         */
        protected $pipeTo;

        /**
         * @var statStruct stat缓存
         */
        protected $status;

        /**
         * 创建Handler
         * 注意，不应该由用户自行创建。此函数由Eventloop自动调用
         */
        public function __construct($proc,array $stdpipe) {
            $this -> proc = $proc;

            $this -> status = new statStruct;

            if($this -> stdin = @$stdpipe[0])
                EventLoop::add($this -> stdin,$this,'p/in');
            else{
                $esc = interval(fn() =>
                    $this -> _status(),
                self::POLL_INTERVAL);
                $this -> on('close',$esc);
            }

            $sync_to_pipe = function(string $data){
                // 缓冲数据
                $this -> pipeCache .= $data;
                if(!$this -> pipeTo){
                    if(strlen($this -> pipeCache) > EventLoop::$WRITE_BUFFER_SIZE /2)
                        $this -> pipeCache = substr($this -> pipeCache, EventLoop::$WRITE_BUFFER_SIZE /2);
                    return;
                }
                // 数据
                $data = $this -> pipeCache;
                // 长度
                $len = strlen($data);
                // 可写长度
                $rest = $this -> pipeTo -> status() -> write;
                $write = EventLoop::$WRITE_BUFFER_SIZE /2 - $rest - $len;
                // 缓冲区不足
                if($write <= 0)
                    return;
                // 缓冲区
                $this -> pipeTo -> write(substr($this -> pipeCache,0,$write));
                $this -> pipeCache = substr($this -> pipeCache,$write);
            };

            if(@$stdpipe[1])
                EventLoop::add($stdpipe[1],$this -> stdout = new ReadaonlyChildPipe($stdpipe[1],function(string $readed) use ($sync_to_pipe){
                    $data = $this -> stdout_tmp . $readed;
                    $pos = strpos($data, "\n");
                    if (false === $pos) $pos = strpos($data, "\r");
                    if ($pos !== false) {
                        $this -> stdout_tmp = self::_trim(substr($data, $pos + 1));
                        $this -> _pushLog(self::_trim(substr($data, 0, $pos), false));
                    }else{
                        $this -> stdout_tmp = $data;
                    }
                    if('' != $readed)  $sync_to_pipe($readed);
                }),'p/out');

            if(@$stdpipe[2])
                EventLoop::add($stdpipe[2],$this -> stderr = new ReadaonlyChildPipe($stdpipe[2],function(string $readed) use ($sync_to_pipe){
                    
                    $data = $this -> stderr_tmp . $readed;
                    $pos = strpos($data, "\n");
                    if (false === $pos) $pos = strpos($data, "\r");
                    if ($pos !== false) {
                        $this -> stderr_tmp = self::_trim(substr($data, $pos + 1));
                        $this -> _pushLog(self::_trim(substr($data, 0, $pos), false));
                    }else{
                        $this -> stderr_tmp = $data;
                    }
                    if('' != $readed)  $sync_to_pipe($readed);
                }),'p/err');
        
            $id = count(self::$allproc);
            self::$allproc[$id] = $this;
            $this -> on('close',function() use ($id){
                unset(self::$allproc[$id]);
            });
        }

        /**
         * 向进程写入内容
         * @param string $data 写入的数据
         */
        public function write(string $data):Promise{
            $prom = new Promise($rs,$rj);
            $done = function() use ($rs,$data){
                $this -> input .= $data;
                $rs();
            };
            if(!$this -> status() -> writable)
                $this -> once('empty',$done);
            else $done();
            return $prom;
        }

        /**
         * 读取全部缓冲区
         * **注意** 返回的是`array`不是数据
         * 
         * @param int $line 无效数据
         * @return Promise 返回数组的Promise
         */
        public function read(int $line = -1):Promise{
            return Promise::new(fn($rs) => $rs($this -> log));
        }

        /**
         * 同步读取缓冲区
         * @return array
         */
        public function readSync(){
            return $this -> log;
        }

        public function _read():void{}

        static function _trim(string $str,bool $left = true){
            if($str == '') return $str;
            if($left)
                if($str[0] == "\r" || $str[0] == "\n")
                    return substr($str,1);
                else return $str;
            else
                if($str[$len = (strlen($str) -1)] == "\r" || $str[$len] == "\n")
                    return substr($str,0,$len);
                else return $str;
        }

        /**
         * 读取一行文本，同时兼容Windows与Linux
         */
        public function readline(){
            return end($this -> log);
        }

        /**
         * 重定向输出到指定管道
         * @param PipeLiked 重定向的Pipe
         * @return Promise
         */
        public function pipeTo(PipeLiked $pipe):Promise{
            $prom = new Promise($rs,$rj);
            $pipe -> write($this -> pipeCache);
            $this -> pipeCache = '';
            $this -> pipeTo = $pipe;
            $this -> on('close',$rs);
            return $prom;
        }

        /**
         * 写入进程
         */
        public function _write():void{
            $write = @fwrite($this -> stdin,$this -> input);
            $this -> input = substr($this -> input,$write);
            $this -> _trig('write');
        }

        /**
         * 写入一行日志
         */
        private function _pushLog(string $data){
            if(count($this -> log) == self::MAX_LINE-1)
                $this -> log = array_slice($this -> log,1);
            $this -> log[] = $data;
            $this -> _trig('read');
        }

        /**
         * 关闭Stream
         */
        function __destruct(){
            $this -> status() -> alive && proc_terminate($this -> proc,self::SIGKILL);
        }

        /**
         * 发送指定信号到进程
         * @param int $sig 信号，建议使用`\MomentCore\ProcPipe::{信号名}`
         * @return bool 是否进程退出
         */
        function sendSig(int $sig = self::SIGTERM){
            if(!$this -> status() -> alive)
                throw new \Error('Pipe has already closed.');
            return proc_terminate($this -> proc,$sig);
        }

        /**
         * 等待进程关闭
         * @return int 退出状态码
         */
        function close_sync(){
            if(!$this -> status() -> alive)
                throw new \Error('Pipe has already closed.');
            return proc_close($this -> proc);
        }

        /**
         * 获取进程状态
         */
        function status():statStruct{
            return $this -> status;
        }
        
        /**
         * 获取是否已经关闭
         */
        function _status():bool{
            $info = $this -> status;
            $info -> write = strlen($this -> input);
            $info -> _write = $info -> write > 0;
            $info -> writable = $info -> write <= self::MAX_STDIN_BUFFER;
            if(is_resource($this -> proc)){
                $status = proc_get_status($this -> proc);
                $info -> command = $status['command'];
                $state = $info -> alive = $status['running'];
                $info -> exitcode = $status['exitcode'];
                $info -> termsig = $status['termsig'];
                $info -> pid = $status['pid'];
            }else{
                $info -> pid = -1;
                $state = $info -> alive = false;
                $info -> command = [];
                $info -> exitcode = 1;
                $info -> termsig = self::SIGABRT;
            }

            if(!$state) $this -> _trig('close');
            return $state;
        }
    }

    /**
     * fetch返回的对象
     * 几乎等价JS的Response
     */
    class Response extends BaselinePipe{

        const CREATED = 0;
        const READY = 1;
        const WAITING = 2;
        const DONE = 3;

        /**
         * @var int 超时时间
         */
        public $timeout = 10;

        /**
         * @var int 状态
         */
        protected $state = self::CREATED;

        /**
         * 获取是否已经关闭
         */
        public function __alive():bool{
            // $timeout = time() - $this -> time > $this -> timeout;
            // if($timeout){
                // $this -> _trig('timeout');
                // return false;
            // }else{
                // return true;
            // }
            return true;
        }

        /**
         * @var int HTTP状态码
         */
        public $status;

        /**
         * @var string 状态解释
         */
        public $statusText;

        /**
         * @var float HTTP版本
         */
        public $httpVersion;

        /**
         * @var array HTTP头
         */
        public $header;

        /**
         * @var array 重定向记录
         */
        public $redirect;

        /**
         * @var string 目标地址
         */
        public $url;

        /**
         * @var resource ctx
         */
        public $context;

        /**
         * 初始化
         */
        public function init(int $status,string $statusText,float $httpVersion,array $redirect,array $header) {
            $this -> status = $status;
            $this -> statusText = $statusText;
            $this -> httpVersion = $httpVersion;
            $this -> header = $header;
            $this -> redirect = $redirect;
            $this -> url = $redirect[0];
            $this -> state = self::READY;
        }

        private function _text(){
            if($this -> state != self::READY)
                throw new \Error('Response is now unreadable.');
            if($this -> status() -> eof)
                throw new \Error('Connection has been closed.');
            $this -> state = self::WAITING;
            $len = @$this -> header['content-length'];
            if(
                $this -> status == 204 ||
                $this -> status == 304 ||
                floor($this -> status) == 1
            ) return '';
            $data = '';
            if(
                in_array('Transfer-Encoding',@$this -> header) && 
                @strtolower(@$this -> header['Transfer-Encoding']) == 'chunked'
            )
                while(true){
                    // 长度\r\n
                    $size = hexdec(yield $this -> readline());
                    if($size <= 0) return $data;   // 读完了
                    // 数据
                    $data .= yield $this -> read($size);
                    // \r\n
                    yield $this -> readline();
                }
            elseif(!$len)
                while(!$this -> status() -> eof)
                    $data .= yield $this -> read();
            else
                return yield $this -> read((int)$len);
            $this -> state = self::DONE;
            $this -> __destruct();
            return $data;
        }

        /**
         * 响应结果输出到文本
         * @link https://developer.mozilla.org/zh-CN/docs/Web/API/Response/text
         * @return string|Promise 文本
         */
        public function text(){
            $prom = new Promise($rs,$rj);
            Promise::await($this -> _text()) -> then($rs) -> catch($rj);
            $esc = $this -> on('close',fn() => 
                $rj(new \Error('Server unexpectedly close the connection'))
            );
            $prom -> then($esc);
            if(\Fiber::getCurrent())
                return \Fiber::suspend($prom);
            else
                return $prom;
        }

        public function __toString(){
            $rt = ([
                self::CREATED => 'pipe created',
                self::READY => 'ready to read',
                self::WAITING => 'reading from remote',
                self::DONE => 'request completed'
            ])[$this -> state];
            $this -> state = self::DONE;
            return "Response({$this -> status}) {$this -> url}[$rt]"; 
        }

        /**
         * 关闭
         */
        public function stream_close(){
            return $this -> __destruct();
        }

        /**
         * 已经读完
         */
        public function stream_eof(){
            return !$this -> status() -> eof;
        }

        /**
         * 同步缓冲区
         */
        public function stream_flush(): bool{
            if(!$this -> status() -> alive) return false;
            $prom = new Promise($rs,$rj);
            $this -> on('empty',$rs);
            \Fiber::suspend($prom);
            return true;
        }

        /**
         * 假函数，设置参数
         */
        public function stream_set_option(){
            return true;
        }

        /**
         * 打开stream
         */
        public function stream_open(
            string $path
        ): bool{
            try{
                $meta = stream_context_get_options($this -> context);
                \Fiber::suspend(request($path,[
                    'method' => $meta['method'] ?? 'GET',
                    'header' => $meta['header'] ?? [],
                    'redirect' => $meta['follow_location'] ?? true,
                    'connect_timeout' => $meta['timeout'] * .5,
                    'timeout' => $meta['timeout'] * .5,
                    'query' => [],
                    'body' => $meta['content'] ?? ''
                ]));
                return true;
            }catch(\Throwable $e){
                trigger_error($e -> getMessage(),\E_USER_WARNING);
                return false;
            }
        }

        /**
         * 异步读取
         */
        public function stream_read(int $count): string|false{
            return \Fiber::suspend($this -> read($count));
        }
        
        /**
         * 写入数据并同步
         */
        public function stream_write(string $data): int{
            \Fiber::suspend($this -> write($data));
            return strlen($data);
        }
    }
    @stream_wrapper_unregister('http');
    @stream_wrapper_unregister('https');
    stream_wrapper_register('http','\\MomentCore\\Response');
    stream_wrapper_register('https','\\MomentCore\\Response');

    /**
     * TLS响应
     */
    class TlsResponse extends Response{
        private $enabled_crypto = false;
        
        function __construct($client,$timeout,$read,$write){
            parent::__construct($client,$read,$write);
            delay($timeout * 1000) -> then(function(){
                if(!$this -> enabled_crypto)
                    $this -> _trig('tls_timeout');
            });
        }

        private function crypto(){
            if(!$this -> enabled_crypto)
                if(@stream_socket_enable_crypto($this -> stream,true,
                    STREAM_CRYPTO_PROTO_TLSv1_3 | STREAM_CRYPTO_PROTO_SSLv3
                )){
                    trigger_error('crypto connection established',E_USER_NOTICE);
                    $this -> enabled_crypto = true;
                    $this -> _trig('tls');
                }else{
                    $this -> _trig('tls_error');
                    $this -> __destruct();
                }
        }

        function _write(): void{
            $this -> crypto();
            parent :: _write();
        }

        function _read(): void{
            $this -> crypto();
            parent :: _read();
        }
    }

    /**
     * Tcp处理,只保留了最简单的功能
     */
    class TcpPipe extends BaselinePipe{
        function __alive(): bool{
            return true;
        }

        function addr(){
            return stream_socket_get_name($this -> stream,false);
        }
    }

    /**
     * 标注 `$handle -> client` 成员的类
     */
    class Client extends \stdClass{
        /**
         * @var string 请求方法，一般是GET/POST
         */ 
        public $method;

        /**
         * @var string 原始传递的URI路径
         */ 
        public $uri;

        /**
         * @var string URI路径
         */ 
        public $path;

        /**
         * @var string BODY参数，只有为POST且非常规form才有效
         */
        public $body;

        /**
         * @var array 客户端的请求头
         */
        public $header;

        /**
         * @var array 客户端传递的COOKIE
         */
        public $cookie;

        /**
         * @var array 请求参数
         */
        public $param;

        /**
         * @var string 解析前的参数字符串
         */
        public $param_str;

        /**
         * @var ?array POST参数，只有为application/x-www-form-urlencoded或multipart/form-data时有效
         */
        public $post;

        /**
         * @var ?array POST文件，只有为multipart/form-data才有效
         */
        public $file;

        /**
         * @var array HTTP版本号，一般都是1.1
         */
        public $version;

        /**
         * @var ?array Session内容
         */
        public $session;
    }

    /**
     * 处理HTTP的类
     */
    class HttpHandle extends BaselinePipe{
        /**
         * @var array 状态解释
         */
        static public $statusMap = [     // HTTP状态对应描述
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];

        /**
         * @var array 默认响应头
         */
        static $default = [
            'Server'        =>  'MomentPHP/' . VERSION,
            'Content-Type'  =>  'text/html ;charset=UTF-8',
            'Connection'    =>  'keep-alive'
        ];

        /**
         * @var array MIME列表
         */
        static $mime = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'shtml' => 'text/html',
            'md' => 'text/markdown',
            'txt' => 'text/plain',
            'conf' => 'text/plain', 
            'log' => 'text/plain', 
            'ini' => 'text/plain', 
            'yaml' => 'text/plain', 
            'vbs' => 'text/vbscript',
            'vtt' => 'text/vtt',
            'dtb' => 'text/x-devicetree-binary',
            'dts' => 'text/x-devicetree-source',
            'dtd' => 'text/x-dtd',
            'py' => 'text/x-python',
            'xml' => 'text/xml',
            'css' => 'text/css',
            'xml' => 'text/xml',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'application/javascript',
            'atom' => 'application/atom+xml',
            'rss' => 'application/rss+xml',
            'mml' => 'text/mathml',
            'txt' => 'text/plain',
            'jad' => 'text/vnd.sun.j2me.app-descriptor',
            'wml' => 'text/vnd.wap.wml',
            'htc' => 'text/x-component',
            'png' => 'image/png',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'wbmp' => 'image/vnd.wap.wbmp',
            'ico' => 'image/x-icon',
            'jng' => 'image/x-jng',
            'bmp' => 'image/x-ms-bmp',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'heif' => 'image/heif',
            'heic' => 'image/heif',
            'jxl' => 'image/jxl',
            'psa' => 'image/vnd.adobe.photoshop',
            'woff' => 'application/font-woff',
            'jar' => 'application/java-archive',
            'war' => 'application/java-archive',
            'ear' => 'application/java-archive',
            'json' => 'application/json',
            'hqx' => 'application/mac-binhex40',
            'doc' => 'application/msword',
            'pdf' => 'application/pdf',
            'ps' => 'application/postscript',
            'eps' => 'application/postscript',
            'ai' => 'application/postscript',
            'rtf' => 'application/rtf',
            'm3u8' => 'application/vnd.apple.mpegurl',
            'xls' => 'application/vnd.ms-excel',
            'eot' => 'application/vnd.ms-fontobject',
            'ppt' => 'application/vnd.ms-powerpoint',
            'wmlc' => 'application/vnd.wap.wmlc',
            'kml' => 'application/vnd.google-earth.kml+xml',
            'kmz' => 'application/vnd.google-earth.kmz',
            '7z' => 'application/x-7z-compressed',
            'cco' => 'application/x-cocoa',
            'jardiff' => 'application/x-java-archive-diff',
            'jnlp' => 'application/x-java-jnlp-file',
            'run' => 'application/x-makeself',
            'pl' => 'application/x-perl',
            'pm' => 'application/x-perl',
            'prc' => 'application/x-pilot',
            'pdb' => 'application/x-pilot',
            'rar' => 'application/x-rar-compressed',
            'rpm' => 'application/x-redhat-package-manager',
            'sea' => 'application/x-sea',
            'swf' => 'application/x-shockwave-flash',
            'sit' => 'application/x-stuffit',
            'tcl' => 'application/x-tcl',
            'tk' => 'application/x-tcl',
            'der' => 'application/x-x509-ca-cert',
            'pem' => 'application/x-x509-ca-cert',
            'crt' => 'application/x-x509-ca-cert',
            'xpi' => 'application/x-xpinstall',
            'xhtml' => 'application/xhtml+xml',
            'xspf' => 'application/xspf+xml',
            'zip' => 'application/zip',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mid' => 'audio/midi',
            'midi' => 'audio/midi',
            'kar' => 'audio/midi',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/x-m4a',
            'ra' => 'audio/x-realaudio',
            'flac' => 'audio/x-flac',
            'caf' => 'audio/x-caf',
            'mka' => 'audio/webm',
            'weba' => 'audio/webm',
            'opus' => 'audio/ogg',
            'aac' => 'audio/x-aac',
            'wav' => 'audio/wav',
            '3gpp' => 'video/3gpp',
            '3gp' => 'video/3gpp',
            'ts' => 'video/mp2t',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'mpg' => 'video/mpeg',
            'mov' => 'video/quicktime',
            'mkv' => 'video/webm',
            'webm' => 'video/webm',
            'flv' => 'video/x-flv',
            'm4v' => 'video/x-m4v',
            'mng' => 'video/x-mng',
            'asx' => 'video/x-ms-asf',
            'asf' => 'video/x-ms-asf',
            'wmv' => 'video/x-ms-wmv',
            'avi' => 'video/x-msvideo',
            'ogv' => 'video/ogg',
            'ttf' => 'font/ttf'
        ];

        /**
         * @var array 路径映射
         */
        static $alias = [];

        /**
         * @var string autolist CSS文件
         */
        static $css = 'f,d,h {
                display: block;
                margin: .2rem .5rem;
            }
            h > c,h > a {
                font-weight: 400;
                font-size: .9rem;
            }
            h > a{
                padding-left: .5rem;
            }
            c, a {
                line-height: 1.5rem;
                box-sizing: border-box;
                display: inline-block;
            }
            a {
                width: 60%;
                word-break: break-all;
                white-space: nowrap;
                overflow: hidden;
                position: relative;
                text-overflow: ellipsis;
            }
            c:nth-child(even) {
                width: 15%;
                text-align: center;
            }
            c:nth-child(odd) {
                width: 25%;
                text-align: right;
                padding-right: 1rem;
            }
            a {
                text-decoration: none;
                color: inherit;
            }
            body {
                background-color: #F5F5F5;
                width: 90vw;
                max-width: 50rem;
                margin: auto;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            }
            h2 {
                margin-bottom: 12px;
            }
            div {
                background-color: white;
                border-radius: .5rem;
                box-shadow: 0 .6rem 1rem -.3rem #00000017, 0 .3rem .5rem -.4rem #0000001a;
                font-size: .85rem;
                padding: .35rem 0;
            }
            span {
                font-size: .95rem;
                color: #787878;
                margin: .5rem;
            }
            f > a::before {
                content: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" fill="aqua" viewBox="0 0 16 16"><path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/></svg>\');
            }
            d > a::before {
                content: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" fill="aquamarine" viewBox="0 0 16 16"> <path d="M.54 3.87.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.826a2 2 0 0 1-1.991-1.819l-.637-7a1.99 1.99 0 0 1 .342-1.31zM2.19 4a1 1 0 0 0-.996 1.09l.637 7a1 1 0 0 0 .995.91h10.348a1 1 0 0 0 .995-.91l.637-7A1 1 0 0 0 13.81 4H2.19zm4.69-1.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981l.006.139C1.72 3.042 1.95 3 2.19 3h5.396l-.707-.707z"/> </svg>\')
            }
            a::before {
                display: inline-block;
                width: 1em;
                margin: 0 .5em;
                transform: scale(1.5);
            }
        ';

        /**
         * @var array Option请求的返回内容
         */
        static $option = [
            'DAV' => '1',
            'Method' => 'GET,POST,PUT,DELETE,COPY,PROPFIND,PROPPATCH,MKCOL,HEAD,MOVE'
        ];
        
        const WEBSOCKET = -2;
        const DESTROYED = -1;
        const CREATED = 0;
        const READING_META = 1;
        const READING_HEADER = 2;
        const READING_BODY = 3;
        const PENDING = 4;
        const CHUNKED = 5;

        /**
         * @var int 最大上传文件个数
         */
        static $MAX_UPLOAD = 5;

        /**
         * @var int 最大上传大小
         */
        static $MAX_UPLOAD_SIZE = 100 * 1024 * 1024;

        /**
         * @var int 最大body缓冲区大小
         */
        static $MAX_BODY_BUFFER = 512 * 1024;

        /**
         * @var bool 当body缓冲区满载时是否开始发送数据
         */
        static $FLUSH_ON_FULL = true;

        /**
         * @var array SESSION存储器
         */
        static private $session = [];

        /**
         * @var array 锁定的内容
         */
        static private $locked = [];

        /**
         * @var array 存储设置的COOKIE
         */
        protected $cookies = [];          // COOKIE设置需要很多行

        /**
         * @var int 请求状况
         */
        protected $state = self::CREATED;

        /**
         * @var array 存储用户响应头
         */
        public $headers = [];

        /**
         * @var bool 
         */
        public $multiHeader = true;

        /**
         * @var ?Client 客户端
         */
        public $client;

        /**
         * @var int 最终HTTP响应状态码
         */
        public $http_status = 200;

        /**
         * @var string 内容缓冲区
         */
        public $temp = '';

        /**
         * @var string 客户端地址
         */
        readonly string $addr;

        /**
         * @var int 最大保持连接时间
         */
        static $KEEPALIVE_TIMEOUT = 1 * 60;

        /**
         * 创建HTTPHandle
         * @param resource 客户端Socket
         */
        public function __construct($socket){
            $this -> addr = stream_socket_get_name($socket,true);
            parent::__construct($socket,self::T_RW);
        }

        /**
         * HTTP是否被关闭
         */
        public function __alive(): bool{
            // if(($this -> state == self::WEBSOCKET 
                    // ? true     // WebSocket无需担心
                    // : (time() - $this -> time <= self::$KEEPALIVE_TIMEOUT ))
                // && is_resource($this -> stream) && !feof($this -> stream)
            // )
                // return true;
            if(!is_resource($this -> stream)){
                $this -> state = self::DESTROYED;
                return false;
            }else return true;
            // return false;
        }

        /**
         * 关闭连接
         */
        public function close():void{
            $this -> state = self::DESTROYED;
            $this -> __destruct();
        }

        /**
         * 获取Handle状况
         */
        public function state(){
            return $this -> state;
        }

        /**
         * 开始响应请求
         * 请使用`Promise::await()`以复用TCP连接
         */
        public function __invoke(){

            if($this -> state == self::DESTROYED)
                throw new \Error("Client has already closed.");

            // 读取body
            if(
                $this -> client &&
                is_numeric(@$this -> client -> header['content-length']) &&
                null == $this -> client -> post &&
                null == $this -> client -> file &&
                null == $this -> client -> body
            )
                yield $this -> read((int)$this -> client -> header['content-length']);

            // 结束chunk
            if($this -> state == self::CHUNKED)
                $this -> endChunk();
            // 检查状态
            elseif(
                $this -> state == self::READING_BODY ||
                $this -> state == self::READING_HEADER ||
                $this -> state == self::READING_META ||
                $this -> state == self::PENDING
            )
                throw new \Error('The request is still running.(' . $this -> state . ')');
            elseif($this -> state == self::WEBSOCKET)
                throw new \Error('Cannot accept requests from WebSocket Content');

            // 重新定义
            $this -> state = self::CREATED;
            $this -> cookies = [];
            $this -> headers = [];
            $c = $this -> client = new \stdClass();
            $this -> http_status = 200;

            // 读取基础信息，若非3个参数、非GET、POST响应拦截
            $this -> state = self::READING_META;
            $line = yield $this -> readline();
            if(!preg_match('/^([a-z]{1,10})\\s*(\\S+)\\s*HTTP\\/([0-9]{1}\\.[0-9]{1,10})$/i',$line,$match)){
                $this -> error(400,'Bad header');
                throw new \Error('Client SyntaxError');
            }
            $path = $c -> uri = str_replace(
                ['/./', '//',   '\\'],
                ['/',   '/',    '/'],
                urldecode($match[2])
            );
            $c -> method = strtoupper($match[1]);
            $c -> version = (float)$match[3];

            if ($path[0] != '/')
                return $this -> error(400, "Bad URL");

            // 基本处理URL参数，用查询"?"
            if(strpos($path,'?') !== false){
                list($path,$param) = \explode('?',$path,2);
                $c -> param_str = $param;
                parse_str($param,$c -> param);
            }else{
                $c -> param = [];
                $c -> param_str = '';
            }
            $c -> path = $path;

            // 路径映射
            if(array_key_exists($path,self::$alias))
                $c -> path = self::$alias[$path];

            // 读取所有header
            $this -> state = self::READING_HEADER;
            $c -> header = [];
            while(true) {
                $str = trim(yield $this -> readline());
                if(trim($str) == "") break;
                @list($name,$value) = \explode(':',$str,2);
                $c -> header [ \strtolower($name) ] = trim($value);
            }

            // 读取Cookie
            $cookie = @$c -> header['cookie'];
            $c -> cookie = $cookie 
                ? $this -> parseParam($cookie,';')
                : [];

            // 读取Session
            $pos = strrpos($this -> addr,':');
            $ip = substr($this -> addr,0,$pos);
            $sessid = @$c -> cookie['__token'];
            $uuid = "{$this -> client -> header['host']}/{$ip}/{$sessid}";
            if(array_key_exists($uuid,self::$session))
                $c -> session = &self::$session[$uuid];

            // 开始响应
            $this -> state = self::PENDING;
        }

        /**
         * 为一个请求使用BasicAuth
         * 示例：
         * ```
         * if(!$handle -> auth('iz','hiiz','websocket'))
         *     return ...
         * // 已经认证成功
         * ......
         * ```
         * @param string $user 用户名
         * @param string $pw 密码
         * @param string $desc 账户命名空间
         */
        public function auth(string $user,string $pw,string $desc = 'MomentDefaultNamespace'){
            $auth = $this -> client -> header['authorization'];
            if(!$auth) goto auth;
            if(!preg_match('/^\\s*basic\\s+([a-z0-9=+\\/=]+)\\s*$/i',$auth,$match))
                goto error;
            $rs = base64_decode($match[1]);
            if($rs === false)
                goto error;
            if("$user:$pw" == $rs) return true;
            else goto auth;

            error:
                return !! yield $this -> finish(400,'illegal BasicAuth data');

            auth:
                return !! yield $this -> finish(401,'Auth required.',[
                    'WWW-Authenticate' => "Basic realm= \"$desc\" "
                ]);
        }

        /**
         * 启动session
         * @return array Session数组
         */
        public function session_start(){
            if($this -> client -> session)
                goto end;

            $sessid = uniqid('s_');
            $pos = strrpos($this -> addr,':');
            $ip = substr($this -> addr,0,$pos);
            $uuid = "{$this -> client -> header['host']}/{$ip}/{$sessid}";

            $this -> cookie('__token',$sessid);
            $this -> client -> session = &self::$session[$uuid];

            end:
                return $this -> client -> session;
        }

        /**
         * 输出内容并结束请求
         * 此后不能再写入任何东西
         * 
         * @param ?string $content 额外的数据
         * @param int $status HTTP状态码
         * @param array $header 额外添加的Header
         */
        public function finish(int $status = -1,string $content = '',array $header = []){
            if($this -> state == self::CHUNKED)
                return $this -> endChunk();
            if($this -> state != self::PENDING)
                throw new \Error('The request is HEADED or not ACTIVE.');

            // 写HTTP响应头
            if(!in_array($status,[
                204,304,100
            ]))
                $header['Content-Length'] = strlen($content) + strlen($this -> temp);
            if($status > 0) $this -> http_status = $status;      // HTTP状态码
            
            $this -> state = self::CREATED;      // 使命结束
            if($this -> client -> method == 'HEAD')
                return $this -> sendHeader($this -> http_status,$header);
            else
                // 写入缓冲区&&追加的内容到缓冲区
                return Promise::all($this -> sendHeader($this -> http_status,$header),parent::write($this -> temp . $content) -> then(function(){
                    // 完成请求
                    $this -> temp = '';                  // 清空body缓冲区
                    $this -> _trig('responsed');
                }));
        }

        /**
         * 输出内容并**关闭请求**
         * @param int $status 状态码
         * @param string $content 错误内容
         * @param array $header 额外的Header
         */
        public function error(int $status = 400,?string $content = 'Bad request',?array $header = []){
            $this -> state = self::PENDING;
            if($this -> state == self::CHUNKED)
                $this -> endChunk();
            elseif($this -> state == self::PENDING)
                $this -> finish($status,$content,$header);
            $this -> close();
        }

        /**
         * 解析请求参数
         * @param string $param 参数字符串
         * @param ?string $split 分割符，如COOKIE是";"
         * @return array 参数 
         */
        protected function parseParam(string $param,string $split = '&'):array{
            $tmp = [];
            foreach(\explode($split,$param) as $_) {
                list($name,$value) = \explode('=',$_,2);
                $tmp[urldecode(trim($name))]=\urldecode(trim($value));
            }
            return $tmp;
        }

        /**
         * 根据后缀名获取指定文件的MIME类型
         * @param string $file 文件名称
         * @return string mime类型
         */
        static function getMime(string $file):string{
            $ext = @end(explode('.',$file));             // 截取末尾扩展名
            return self::$mime[$ext] ?? 'application/octet-stream';
        }

        /**
         * 创建并发送响应头
         * 
         * **注意**
         *  - 由于Moment队列化设计，因此无需等待Promise即可继续
         *  -  发送完Header后此时`header()`不再可用
         * 
         * @internal 建议使用`flushHeader()`
         * @param ?int $status 状态码
         * @param ?array $header 额外的Header
         * @return Promise 当写入TCP底层后的回调
         */
        public function flushHeader(?int $status = null , array $header = []){
            // 必须是响应状态时
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            // 发送header
            return $this -> sendHeader($status ?? $this -> http_status,$header);
        }
        
        /**
         * 创建响应头
         * @internal 建议使用`flushHeader()`
         * @param ?int $status 状态码
         * @param ?array $header 额外的Header
         */
        protected function sendHeader($status, $header):Promise {
            // 10x允许多header
            if(!$this -> multiHeader && floor($status /100) == 1)
                $this -> multiHeader = true;
            // 状态码和解释字符串
            $desc = @self::$statusMap[$status] ?? 'OK';
            $data = "HTTP/1.1 $status $desc\r\n";
            // 发送headers
            if(is_array($this -> headers)){
                // 写入到buffer
                foreach(\array_merge(self::$default,$this -> headers,$header) as $n=>$v)
                    if(null !== $v && $v !== '')
                        $data .= "$n: $v\r\n";
                // 写入所有cookie
                foreach($this -> cookies as $cookie)
                    $data .= "\r\nSet-Cookie: $cookie";
                // 写入EOF并清理header
                $this -> headers = false;
                log("{color_pink}C{/} {-color_green}$status{/} {$this -> client -> method}{color_gray}({$this -> addr}){/} {$this -> client -> path}");
            // Connection:close
            }elseif(
                array_key_exists('connection',$this -> client -> header) && 
                strtolower(@$this -> client -> header['connection']) == 'close'
            ) $this -> close();
            $data .= "\r\n";
            return parent::write($data);
        }
        
        /**
         * 设置响应Header，没有提供第二个参数则认为是获取
         * 
         * @param string $name 名称
         * @param string $value 内容
         * @return self
         */
        public function header(string $name,mixed $value = null) {
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            if($value == null) return @$this -> headers[$name];
            else $this -> headers[$name] = (string)$value;
            return $this;
        }

        /**
         * 解析请求头的末尾位置，一般是POST数据，不存在返回NULL
         */
        public function parseBody() {
            if(
                @$this -> client -> method != 'POST' || 
                null != $this -> client -> post ||
                null != $this -> client -> file ||
                null != $this -> client -> body
            )
                return;
            $this -> state = self::READING_BODY;
            $len = (int)@$this -> client -> header['content-length'];      // 最终数据大小
            $ctype = @$this -> client -> header['content-type'];      // 类型
            if($len > self::$MAX_UPLOAD_SIZE)
                return $this -> error(413,'上传内容太大');
            elseif($len <= 0)
                return;
            $dat = yield $this -> read($len);          // 传递数据
            if(str_starts_with($ctype,'application/x-www-form-urlencoded')){
                // 以 名=值&...传递
                $this -> client -> post = $this -> parseParam($dat);
            }elseif(preg_match('/^\\s*multipart\\/form-data;\s*boundary\s*=\s*(.+)\\s*$/i',$ctype,$match)){
                $boundary = $match[1];
                $this -> client -> file = [];
                // 上传文件
                $arr = @explode("--$boundary",$dat);
                if(array_pop($arr) != '--')
                    return $this -> error(400,'Bad FormData');
                foreach($arr as $f)
                    $this -> client -> file[] = new FileObject($f);
            } elseif (str_starts_with($ctype,'application/json')) {
                // JSON
                $this -> client -> post = json_decode($dat,true);
            } else {
                // 不清除，直接保存
                $this -> client -> body = $dat;
            }
            $this -> state = self::PENDING;
        }

        /**
         * 向客户端公布更新一个COOKIE
         * 
         * @param string $cookie_name Cookie名称
         * @param string $value Cookie值
         * @param ?int $timeout Cookie过期时间，默认为浏览器退出自动销毁
         * @param ?bool $httponly 只允许Http访问,JavaScript则不行
         * @param ?bool $subdomain 允许子域名访问Cookie 
         * @param ?bool $subpath 允许子路径使用Cookie
         * @return self
         */
        function cookie(string $cookie_name,string $value,?int $timeout=null,bool $httponly = false,bool $subdomain = false,bool $subpath = true){
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            $e = "$cookie_name=$value; ";
            if(!is_null($timeout)) $e .= "Max-Age=$timeout; ";  // 失效时间
            if($subdomain) $e .= 'Domain='. $this ->  client -> header['host'] .'; ';
            if($subpath) $e .= 'Path=./; ';
            if($httponly) $e .= 'HttpOnly; ';
            $this -> cookies[] = $e;
            return $this;
        }

        /**
         * 缓存一些数据
         * 
         * @param string $str 文本
         */
        function write(string $str):Promise{
            // Chunk模式下编码直接写入
            if($this -> state == self::CHUNKED)
                return parent::write(dechex(strlen($str)) . "\r\n" . $str . "\r\n");
            // WebSocket或者已经发送header时直接加入缓冲区
            elseif($this -> state == self::WEBSOCKET || !is_array($this -> headers))
                return parent::write($str);
            
            // 正常写入模式
            else return Promise::new(function($rs) use ($str){
                if(strlen($this -> temp) > EventLoop::$WRITE_BUFFER_SIZE){
                    if(self::$FLUSH_ON_FULL){
                        $this -> useChunk();
                        return $this -> write($this -> temp) -> then($rs);
                    }else{
                        throw new \Error('Allocate memory failed: Buffer overflow.');
                    }
                }else{
                    $this -> temp .= $str;
                    $rs();
                }
            });
        }

        /**
         * 使用HTTP Chunk分块发送数据
         * **强烈建议使用chunk缓解缓冲区负担**
         * 
         * @param ?int $status 状态码
         * @param ?array $header 额外的Header
         * @return Promise
         */
        function useChunk(?int $status = null,?array $headers = []){
            if($this -> state != self::PENDING || !is_array($this -> headers))
                throw new \Error('Request is not active');

            $headers['Transfer-Encoding'] = 'chunked';
            $this -> state = self::CHUNKED;
            return $this -> sendHeader($status,$headers);
        }

        /**
         * 结束HTTP Chunk
         * **必须响应结束后调用这个函数 ，否则客户端将一直阻塞**
         */
        function endChunk(){
            $this -> state = self::CREATED;
            return parent::write("0\r\n\r\n");
        }
        
        /**
         * 输出一个文件。若指定offset为true将允许客户端断点续传
         * 【警告】若使用TEST模式，限速只会大大降低并发。
         * 不过可以减少带宽太小导致缓冲区挤满，可以设置为服务器的实际带宽
         * 
         * @param string $file_path 文件路径
         * @param string $offset 允许断点续传模式 
         */
        function file(string $file_name,bool $offset = true){
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            if(!is_file($file_name) or !is_readable($file_name)){
                yield $this -> finish(404,'File not exists!');
                return false;
            }

            // 先决定文件类型等
            clearstatcache(false,$file_name);
            $name = basename($file_name);       // 实际名称
            $ext  = $this -> getMime($name);    // MIME类型
            $this -> header('Last-Modified', $mtime = gmdate('D, d M Y H:i:s T',filemtime($file_name)));
            $this -> header('Content-Type',$ext);

            // 判断是否客户端是最新的文件，是的话返回304告诉客户端不用传了
            $fsize = filesize($file_name);
            if($offset and $this -> client -> header['if-modified-since'] == $mtime)
                $this -> finish(304,'',[
                    'Content-Length' => $fsize
                ]);
            
            // 决定是否内联，如果参数里内置download则直接下载
            $type = isset($this -> client -> param['download']) ? 'attachment' : 'inline';
            $this -> header('Content-Transfer-Encoding','binary');
            $this -> header('Content-Disposition',"$type;filename=\"$name\"");

            $f = open($file_name,'rb');
            
            if($offset == true && $this -> client -> header['range']) {
                $range = $this -> client -> header['range'];
                preg_match('/^\\s*([a-z]+)=([0-9]*)-([0-9]*)\\s*$/i',$range,$match);

                // 两个都没有注明：错误
                if($match[2] == '' && $match[3] == ''){
                    return $this -> error(400,'Bad Range');
                // 倒数n个字符串
                }elseif($match[2] == ''){
                    $start = $fsize - (int)$match[3];
                    $end = $fsize -1;
                // 正数n到最后面
                }elseif($match[3] == ''){
                    $start = (int)$match[2];
                    $end = $fsize -1;
                // 两个都写明了
                }else{
                    $start = (int)$match[2];
                    $end = (int)$match[3];
                }

                // 缩放
                // momentPHP额外支持百分数(percent)
                $scale = @([
                    'bytes' => 1,
                    'percent' => $fsize / 100
                ])[strtolower($match[1])];
                if(!$scale)
                    return $this -> error(400,'RangeMode is not supported');
                
                $start *= $scale;       // 起始位置
                $end   *= $scale;       // 结束位置

                // 判断range
                if($end >= $fsize)
                    return yield $this -> finish(416,"Out of fileSize($fsize)");
                elseif($end < $start)
                    return yield $this -> finish(400,"Illegal range(#0:$start >= #1:$end)");

                // 读取长度
                $read_length = $end - $start +1;
                $this -> header('Content-Range', "bytes $start-$end/$fsize");
                $this -> header('Content-Length',$read_length);

                $this -> sendHeader(206,$this -> headers); // 开始断点续传
                $f -> seek( $start );
            }else{
                $read_length = $fsize;
                yield $this -> sendHeader(200,[
                    'Content-Length' => (string)$fsize
                ]);
            }

            $sig = $f -> on('close', fn() => $this -> close());
            try{
                yield $f -> pipeTo($this,$read_length);
            }catch(\Throwable){
                return $this -> close();
            }
            $sig();

            $f -> __destruct();
            $this -> _trig('responsed');
            $this -> state = self::CREATED;      // 使命结束
            return true;
        }

        /**
         * 针对类XML(`CSS` `HTML` `JavaScript` `XML`)压缩减小体积，比GZ压缩更有效
         * 并写入缓冲区而不是输出到客户端
         * 
         * @param string $str 要压缩的文本
         */
        function compress(string $str){
            yield $this -> write(preg_replace([
                    // 过滤标记中空格    过滤HTML注释       过滤JS注释            过滤CSS注释
                    '/> *([^ ]*) *</', '/<!--[^!]*-->/', '/^"?\\/\\/[^\n]+/' , '/\\/\\*[^(\*\/)]+\\*\\//','/[\s]+/'   
                ],[ '>\\1<'          , ''              ,''                   ,''                     , ' '],$str)
            );
            return $this;
        }

        /**
         * 列举文件，样式表使用内置CSS `self::$css`
         * 如果`$parent`设置为`true`,将添加 **上一级目录** 选项
         * HTML标签对应如下:
         *  - `<h>`表示表头
         *  - `<f>`表示文件行
         *  - `<d>`表示文件夹行
         *  - `<a>` `<c>`表示一列
         * 
         * @param string $dir 列举的文件夹
         * @param bool $parent 是否有父目录可列举
         * @param string $relative URL相对路径，用于链接
         */
        function listDir(string $dir,bool $parent=false,string $relative_path = '.'){
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            if(false === $data = opendir($dir))
                return yield $this -> finish(403,'Failed to open dir');
            $this -> useChunk(200);
            // 编码
            $relative_path = urlencode($relative_path);
            // 头与CSS
            yield $this -> write('<html><head><meta http-equiv="content-type" content="text/html; charset=UTF-8"><title>WebList</title><style>' . 
                preg_replace('/\\s+/',' ',self::$css).
                '</style></head><body><h2> WebList </h2><div><h><a>名称</a><c>大小</c><c>最后更新</c></h>'
            );
            // 非根文件夹
            if($parent)
                yield $this -> write('<d><a href="../">上一级目录</a><c>-</c><c>-</c></d>');
            $file = [];
            while($fname = readdir($data)) {
                // 隐藏文件
                if($fname[0] == '.') continue;
                $path = "$dir/$fname";
                $fname_encode = urlencode($fname);
                $fname_html = htmlspecialchars($fname,ENT_HTML5);
                // 文件夹
                if(is_dir($path))
                    yield $this -> write("\n<d><a href=\"$relative_path/$fname_encode/\">$fname_html/</a><c>-</c><c>-</c></d>");
                // 文件
                else{
                    // 尝试获取信息
                    try{
                        $stat = @lstat($path);
                    }catch(\Throwable){
                        $stat = [
                            "size"  => -1,
                            "mtime" => 0
                        ];
                    }
                    if(!$stat) continue;
                    // 创建、修改日期
                    if($stat['mtime'] > 0)
                        $date = date('Y-m-d H:i',$stat['mtime']);
                    else $date = '-';
                    // 大小
                    if($stat['size'] > 0){
                        foreach (['B','KB','MB','GB','TB','PB'] as $i => $unit)
                            if($stat['size'] / 1024 ** $i < 800){
                                $size = round($stat['size'] / 1024 ** $i,1) . $unit;
                                break;
                            }
                    }elseif($stat['blocks'] > 0){
                        foreach (['B','KB','MB','GB','TB','PB'] as $i => $unit)
                            if($stat['blocks'] / 1024 ** $i < 2){
                                $size = round(($stat['blocks'] / 1024 ** $i) * 512,1) . $unit;
                                break;
                            }
                    }else{
                        $size = '-';
                    }
                    $file[$fname] = "\n<f><a href=\"$relative_path/$fname_encode\">$fname_html</a><c>$size</c><c>$date</c></f>";
                }
            }
            @array_multisort($file,SORT_ASC,SORT_STRING);
            yield $this -> write(join('',$file));
            yield $this -> write('</div><span> MomentPHP WebList </span></body></html>');
            yield $this -> endChunk();
        }

        /**
         * 变成WebSocket请求
         */
        function ws():WebSocket{
            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');
            if(
                \trim(\strtolower(@$this ->  client -> header['upgrade'])) != 'websocket' ||
                !$this ->  client -> header['sec-websocket-key']
            )
                throw new \Error('Error: Not a WebSocket request.');
            $key = $this->client->header['sec-websocket-key'];

            $accept = \base64_encode(\pack(
                'H*',
                \sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')
            ));
            $this->sendHeader(101, [
                "Upgrade" => "websocket",
                "Connection" => "Upgrade",
                "Sec-WebSocket-Version" => "13",
                "Sec-WebSocket-Accept" => "$accept"
            ]);
            $this->state = self::WEBSOCKET;

            @ob_clean();
            return new WebSocket($this,false);
        }

        /**
         * 使用内置WebDAV服务器
         * 注意：应该先身份认证
         */
        function webdav(string $dav_root){
            $path = $dav_root . $this -> client -> path;

            if($this -> state != self::PENDING)
                throw new \Error('The request is not in PENDING state.');

            $get_dest = function() use ($path,$dav_root){
                $dest = $this ->  client -> header['destination'];
                if($dest[0] == '/')
                    return $dav_root . $dest;
                elseif(is_dir($path))
                    return $path . '/' . $dest;
                else
                    return dirname($path) . '/' . $dest;
            };

            switch (strtoupper($this -> client -> method)) {
                // GET: 文件服务
                case 'GET':
                    yield from $this -> file($path);
                return;

                // PUT: 文件上传
                case 'PUT':
                    if (null == $this -> client -> header['content-length'])
                        return yield $this -> finish(400, "No Length specificed");

                    $read_length = $this -> client -> header['content-length'];
                    // $_100 = strtolower(trim($this -> client -> header['expect'])) == '100-continue';

                    $f = fopen($path, 'w');
                    if ($f === false) 
                        return $this -> error(403, 'OpenFile failed.');

                    // 100 Continue
                    // if($_100) yield $this -> sendHeader(100,[]);
                    
                    // 写入
                    yield $this -> pipeTo($f,(int)$read_length);
                    yield $this -> finish(201);

                    // 结束
                    $this -> headers = [];
                return;

                // DELETE: 删除
                case 'DELETE':
                    if(@unlink($path))
                        yield $this -> finish(204);
                    else yield $this -> finish(424);
                return;

                // MOVE: 流式复制+删除
                case 'MOVE':
                    $mv = true;
                // COPY: 流式复制
                case 'COPY':
                    $destination = $get_dest();
                    if(file_exists($destination) && strtoupper(trim($this -> client -> header['overwrite'])) != 'T')
                        return yield $this -> finish(412);
                    $e = yield copy($path,$get_dest());
                    yield $this -> finish(200);
                    if(@$mv) unlink($path);
                return;

                // MKCOL: 创建文件夹
                case 'MKCOL':
                    if(file_exists($path))
                        $status = 405;
                    elseif(!is_dir(dirname($path)))
                        $status = 409;
                    elseif(@mkdir($path))
                        $status = 201;
                    else
                        $status = 403;
                    yield $this -> finish($status);
                return;

                // PROPFIND: 获取文件(夹)信息
                case 'PROPFIND':
                    if(!file_exists($path))
                        return yield $this -> finish(404);
                    $this -> header('Content-Type', 'application/xml');
                    yield $this -> write('<?xml version="1.0" encoding="utf-8" ?><D:multistatus xmlns:D="DAV:">');
                    yield from $this -> _propfind(
                        $path,
                        dirname($this -> client -> path),
                        basename( $this -> client -> path),
                        (int)$this -> client -> header['depth']
                    );
                    yield $this -> finish(207,'</D:multistatus>');
                return;
                
                case 'PROPPATCH':
                    yield $this -> finish(200);
                return;

                // 假方法：没有用处，完全为了应付Explorer对于WebDAV的要求
                case 'LOCK':
                    @list($type,$timeout) = explode(',',$this -> client -> header['timeout']);
                    $lock = trim($this -> client -> header['if'],' ()<>[]');
                    yield from $this -> compress("<?xml version=\"1.0\" encoding=\"utf-8\" ?> 
                        <D:prop xmlns:D=\"DAV:\"> 
                            <D:lockdiscovery> 
                                <D:activelock> 
                                    <D:locktype><D:write/></D:locktype> 
                                    <D:lockscope><D:exclusive/></D:lockscope> 
                                    <D:depth>$type</D:depth> 
                                    <D:owner> 
                                        <D:href>{$this -> addr}</D:href> 
                                    </D:owner> 
                                    <D:timeout>$timeout</D:timeout> 
                                    <D:locktoken> 
                                        <D:href>$lock</D:href> 
                                    </D:locktoken> 
                                    <D:lockroot> 
                                        <D:href>{$this -> client -> path}</D:href> 
                                    </D:lockroot> 
                                </D:activelock> 
                            </D:lockdiscovery> 
                        </D:prop>");
                    if(file_exists($path)) yield $this -> finish(200);
                    else yield $this -> finish(touch($path) ? 201 : 403);

                    self::$locked[trim($path,'\\/')] = true;
                return;

                case 'UNLOCK':
                    unset(self::$locked[trim($path,'\\/')]);
                    yield $this -> finish(204);
                return;

                default:
                    yield $this -> finish(405,'Unknown method '.$this -> client -> method);
                return;
            }
        }

        static private function getLock(string $path){
            foreach (self::$locked as $dir => $info)
                if(trim($path,'\\/') == $path)
                    return $info;
            return false;
        }

        private function _propfind(string $path,string $parent_uri,string $fname,int $depth_rest){
            $data = @lstat($path);
            $fname = htmlspecialchars($fname,ENT_XML1);
            $relative_uri = preg_replace('/(?:\/|\\\\)+/','/',"$parent_uri/$fname");

            if(!$data){
                // 不存在
                return yield from $this -> compress("<D:response>
                    <D:href>$relative_uri</D:href>
                    <D:status>HTTP/1.1 404 Not Found</D:status>
                </D:response>");
            }
            
            $lock = self::getLock($path);
            if(is_dir($path)){
                // -----文件夹
                if(!$lock) yield from $this -> compress("<D:response>
                    <D:href>$relative_uri</D:href>
                    <D:propstat>
                        <D:prop>
                            <D:displayname>$fname</D:displayname>
                            <D:resourcetype><D:collection/></D:resourcetype>
                            <D:getlastmodified>" . date('D, j M Y H:i:s \G\M\T', $data['mtime']) . "</D:getlastmodified>
                            <D:creationdate>" . date('Y-m-d\TH:i:s\Z', $data['ctime']) . "</D:creationdate>
                        </D:prop>
                        <D:status>HTTP/1.1 200 OK</D:status>
                    </D:propstat>
                    <D:propstat>
                        <D:prop>
                            <D:getcontentlength/>
                            <D:supportedlock/>
                        </D:prop>
                        <D:status>HTTP/1.1 404 Not Found</D:status>
                    </D:propstat>
                </D:response>");
                else yield from $this -> compress("<D:response>
                    <D:href>$relative_uri</D:href>
                    <D:propstat>
                        <D:prop>
                            <D:displayname>$fname</D:displayname>
                            <D:resourcetype><D:collection/></D:resourcetype>
                            <D:getlastmodified>" . date('D, j M Y H:i:s \G\M\T', $data['mtime']) . "</D:getlastmodified>
                            <D:creationdate>" . date('Y-m-d\TH:i:s\Z', $data['ctime']) . "</D:creationdate>
                            <D:supportedlock>
                                <D:lockentry>
                                    <D:lockscope><D:exclusive/></D:lockscope>
                                    <D:locktype><D:write/></D:locktype>
                                </D:lockentry>
                            </D:supportedlock>
                        </D:prop>
                        <D:status>HTTP/1.1 200 OK</D:status>
                    </D:propstat>
                    <D:propstat>
                        <D:prop>
                            <D:getcontentlength/>
                        </D:prop>
                        <D:status>HTTP/1.1 404 Not Found</D:status>
                    </D:propstat>
                </D:response>");
                // 扫描子目录
                if($depth_rest > 0)
                    foreach (scandir($path) as $item)
                        if($item != '.' && $item != '..')
                            yield from $this -> _propfind(
                                "$path/$item", 
                                $relative_uri,
                                $item,
                                $depth_rest -1
                            );
            }elseif($data['size'] >= 0){
                // ----- 文件
                if(!$lock) yield from  $this -> compress("<D:response>
                    <D:href>$relative_uri</D:href>
                    <D:propstat>
                        <D:prop>
                            <D:getlastmodified>" . date('D, j M Y H:i:s \G\M\T', $data['mtime']) . "</D:getlastmodified>
                            <D:creationdate>" . date('Y-m-d\TH:i:s\Z', $data['ctime']) . "</D:creationdate>
                            <D:getcontentlength>" . $data['size'] . "</D:getcontentlength>
                            <D:resourcetype/>
                        </D:prop>
                        <D:status>HTTP/1.1 200 OK</D:status>
                    </D:propstat>
                    <D:propstat>
                        <D:prop>
                            <D:getcontentlength/>
                            <D:supportedlock/>
                        </D:prop>
                        <D:status>HTTP/1.1 404 Not Found</D:status>
                    </D:propstat>
                </D:response>");
                else yield from $this -> compress("<D:response>
                    <D:href>$relative_uri</D:href>
                    <D:propstat>
                        <D:prop>
                            <D:getlastmodified>" . date('D, j M Y H:i:s \G\M\T', $data['mtime']) . "</D:getlastmodified>
                            <D:creationdate>" . date('Y-m-d\TH:i:s\Z', $data['ctime']) . "</D:creationdate>
                            <D:getcontentlength>" . $data['size'] . "</D:getcontentlength>
                            <D:resourcetype/>
                            <D:supportedlock>
                                <D:lockentry>
                                    <D:lockscope><D:exclusive/></D:lockscope>
                                    <D:locktype><D:write/></D:locktype>
                                </D:lockentry>
                            </D:supportedlock>
                        </D:prop>
                        <D:status>HTTP/1.1 200 OK</D:status>
                    </D:propstat>
                </D:response>");
            }
        }
    }

    /**
     * 实现Ftp与HTTP的协同
     */
    class FtpHandle extends TcpPipe{

        /**
         * @var ?string FTP用户名
         */
        static $user = null;

        /**
         * @var ?string FTP密码
         */
        static $pass = null;

        const L_USER = 1;
        const L_OK = 2;
        const L_PW = 3;

        /**
         * @var int 当前状态
         */
        private $status = self::L_USER;

        /**
         * @var callable 被动模式下客户端连接的回调
         */
        public $callback;

        /**
         * @var ?resource 数据连接
         */
        private $data_socket;

        /**
         * @var array 指明当前客户端的连接目的地
         */
        static $client_bindto = [];

        /**
         * @var int 数据端绑定的端口
         */
        static $bind_port = 0;

        /**
         * @var string 工作目录
         */
        static $root = '';

        /**
         * @var string 当前客户端工作目录
         */
        protected $working_dir = '/';

        /**
         * @var ?string 重命名原始位置
         */
        protected $rename_from;

        /**
         * @var int 移动头位置
         */
        protected $seek = 0;

        /**
         * 初始化数据监听接口
         * @param string $addr 监听地址
         * @return callable 停止函数
         */
        static function initDataServer(int $port){
            self::$bind_port = $port;
            return bind("tcp://0.0.0.0:$port",function($ctx,string $addr){
                // 非正常连接
                $ip = self::getIPAddr($addr);
                if(!array_key_exists($ip,self::$client_bindto))
                    return fclose($ctx);
                // 绑定
                Promise::call(self::$client_bindto[$ip] -> callback,$ctx);
                unset(self::$client_bindto[$ip]);
            });
        }

        /**
         * 开始接收请求
         * 如果是FTP请求，那么永久阻塞
         * 支持MIXIN
         */
        public function __invoke(){
            // 发送欢迎请求
            yield $this -> write("220 Welcome to MomentPHP built-in FTP service.\r\n");
            // 无限接受请求
            while(true){
                $txt = yield $this -> readline();
                $match = preg_split('/\\s+/',trim($txt),2);
                trigger_error("Ftp Command: {$match[0]}");
                $cmd = strtolower($match[0]);
                $param = $match[1] ?? '';
                // 设置用户名
                if($cmd == 'user'){
                    if(self::$user === null || self::$user == $param){
                        $this -> status = self::L_PW;
                        yield $this -> write("331 Please specify the password.\r\n");
                    }else{
                        yield $this -> write("530 invaild username\r\n");
                    }
                // 设置密码
                }elseif($cmd == 'pass'){
                    if(self::$pass === null || self::$pass == $param){
                        $this -> status = self::L_OK;
                        yield $this -> write("230 Login successful.\r\n");
                    }else{
                        yield $this -> write("530 incorrect password\r\n");
                    }
                // 配置
                }elseif($cmd == 'opts'){
                    // 没有配置
                    if(!$param){
                        yield $this -> write("501 Option not understood.\r\n");
                        continue;
                    }
                    // 虚假设置
                    $data = preg_split('/\\s+/',$param,2);
                    yield $this -> write("200 Always in {$data[0]} mode\r\n");
                // 未登录：拒绝
                }elseif($this -> status != self::L_OK){
                    yield $this -> write("331 Login First\r\n");
                // SITE子命令
                }elseif($cmd == 'site'){
                    $cmds = preg_split('/\\s+/',$param);
                    switch (strtoupper($cmds[0])) {
                        case 'chmod':
                            $mod = (int)$cmd[1];
                            if($mod <= 0) $mod = 0755;
                            if(chmod($this -> realpath($cmd[2]),$mod))
                                yield $this -> write("200 SITE CHMOD command ok.\r\n");
                            else
                                yield $this -> write("550 SITE CHMOD command failed.\r\n");
                        break;

                        case 'umask':
                            $mask = (int)$cmd[1];
                            if($mask <= 0) $mask = 0755;
                            umask($mask);
                            yield $this -> write("200 UMASK set to $mask\r\n");
                        break;

                        case 'help':
                            yield $this -> write("214 CHMOD UMASK HELP");
                        break;

                        default:
                            yield $this -> write("500 Unknown SITE command.\r\n");
                        break;
                    }
                // 匹配开始
                }else{
                    goto cases;
                }
                continue;

                cases:
                switch ($cmd) {
                    // 被动模式
                    case 'pasv':
                        $this -> data_socket = yield from $this -> fork_conn();
                    break;

                    // 主动模式
                    case 'port':
                        $_ = explode(',',$param,6);
                        $addr = "{$_[0]}.{$_[1]}.{$_[2]}.{$_[3]}";
                        $port = (int)$_[4] * 256 + (int)$_[5];
                        $this -> data_socket = @stream_socket_client("tcp://$addr:$port",$ec,$em,10,STREAM_CLIENT_ASYNC_CONNECT);
                        yield $this -> write("200 PORT($port) command successful. Consider using PASV.\r\n");
                    break;

                    // DATA端接受数据
                    case 'list':
                        if(!is_resource($this -> data_socket)){
                            yield $this -> write("425 Use PORT or PASV first.\r\n");
                            break;
                        }
                        $tcp = $this -> setFlag($this -> data_socket);
                        yield $this -> write("150 Here comes the directory listing.\r\n");
                        yield from $this -> __stat_dir($param,$tcp);
                        yield $this -> write("226 Directory send OK\r\n");
                        $tcp -> __destruct();
                    break;

                    // 直接接收数据
                    case 'stat':
                        yield $this -> write("213-Status follows:\r\n");
                        if(is_dir(self::$root . $param)){
                            yield from $this -> __stat_dir($param);
                        }elseif(is_file($param)){
                            yield from $this -> __stat_file($this -> realpath($param),$this);
                        }
                        yield $this -> write("213 End of status\r\n");
                    break;

                    // 文件大小
                    case 'size':
                        $f = @filesize($this -> realpath($param));
                        if($f === false)
                            yield $this -> write("550 Could not get file size.\r\n");
                        else
                            yield $this -> write("213 $f\r\n");
                    break;

                    /**
                     * 可用空间
                     * @link https://datatracker.ietf.org/doc/html/draft-peterson-streamlined-ftp-command-extensions-10#section-4.1
                     */
                    case 'avbl':
                        $sp = disk_free_space($this -> realpath($param));
                        if($sp !== false)
                            $this -> write("213 $sp\r\n");
                        else
                            $this -> write("550 GetFreeSpace Failed\r\n");
                    break;

                    // 删除内容
                    case 'rmd':
                    case 'dele':
                        $from = $this -> realpath($param);
                        if(!file_exists($from)){
                            yield $this -> write("550 File not exists.\r\n");
                            break;
                        }
                        if(@unlink($from))
                            yield $this -> write("250 Delete operation successful\r\n");
                        else
                            yield $this -> write("550 DELE command failed.\r\n");
                    break;

                    // 建文件夹
                    case 'mkd':
                        $from = $this -> realpath($param);
                        if(file_exists($from)){
                            yield $this -> write("550 Dir exists.\r\n");
                            break;
                        }
                        if(@mkdir($from,recursive:true))
                            yield $this -> write("250 MKD operation successful\r\n");
                        else
                            yield $this -> write("550 MKD command failed.\r\n");
                    break;

                    // 读取文件
                    case 'retr':
                        // 判断源文件
                        $from = $this -> realpath($param);
                        if(!is_file($from)){
                            yield $this -> write("550 File not exists.\r\n");
                            break;
                        }

                        // 判断数据socket
                        if(!is_resource($this -> data_socket)){
                            yield $this -> write("425 Use PORT or PASV first.\r\n");
                            break;
                        }

                        // 打开文件
                        $fd = open($from,'r');
                        $size = $fd -> stat()['size'];
                        $name = basename($from);

                        // 使用seek
                        if($this -> seek > 0 && $this -> seek < $size){
                            $fd -> seek($this -> seek);
                            $size = $size - $this -> seek;
                            $this -> seek = 0;
                        }

                        // 写入数据
                        yield $this -> write("150 Opening $name for reading ($size bytes)\r\n");
                        $fd -> pipeTo($this -> data_socket,$size)
                         -> catch(fn($e) => $this -> write("550 Connection unexpectedly closed.\r\n"))
                         -> then(fn() => $this -> write("226 File send OK.\r\n"))
                         -> finally(fn() => fclose($this -> data_socket));
                    break;

                    // 写入文件
                    case 'appe':
                        $mode = 'a';
                    case 'stor':
                    case 'stou':
                        $from = $this -> realpath($param);
                        if(!is_resource($this -> data_socket)){
                            yield $this -> write("425 Use PORT or PASV first.\r\n");
                            break;
                        }

                        yield $this -> write("150 Ok to send data.\r\n");

                        // 打开文件
                        $f = @fopen($from,$mode ?? 'w');
                        try{
                            // 打开失败
                            if($f === false) throw new \Error('');
                            // 写入文件
                            $pipe = $this -> setFlag($this -> data_socket,self::T_READABLE);
                            $pipe -> pipeTo($f)
                             -> then(fn() => $this -> write("200 Write OK\r\n")) 
                             -> catch(fn() => $this -> write("553 Open file failed.\r\n"))
                             -> finally(fn() => fclose($f));
                        }catch(\Error){
                            yield $this -> write("553 Open file failed.\r\n");
                            break;
                        }
                    break;

                    // 上一级/设置工作目录
                    case 'cdup':
                        $param = '../';
                    case 'cwd':
                        $dir = $this -> realpath($param);
                        if(is_dir($dir)){
                            $this -> working_dir = $param;
                            yield $this -> write("250 Directory successfully changed.\r\n");
                        }else{
                            yield $this -> write("550 Failed to change directory.\r\n");
                        }
                    break;

                    // 设置工作目录
                    case 'pwd':
                        yield $this -> write("257 \"{$this -> working_dir}\"\r\n");
                    break;

                    // 移动源文件
                    case 'rnfr':
                        $from = $this -> realpath($param);
                        if(!file_exists($from)){
                            yield $this -> write("550 RNFR command failed.\r\n");
                            break;
                        }
                        $this -> rename_from = $from;
                        yield $this -> write("350 Ready for RNTO.\r\n");
                    break;

                    // 移动
                    case 'rnto':
                        // 来源
                        if(!$this -> rename_from){
                            yield $this -> write("503 RNFR required first.\r\n");
                            break;
                        }
                        $from = $this -> rename_from;

                        // 目标
                        $to = $this -> realpath($param);
                        if(!file_exists($to)){
                            yield $this -> write("550 RNTO command failed.\r\n");
                            break;
                        }

                        // 移动开始
                        $this -> rename_from = null;
                        $list = yield move($from,$to);

                        // 移动完成
                        if(count($list) > 0)
                            yield $this -> write("550 Rename failed.\r\n");
                        else
                            yield $this -> write("250 Rename successful.\r\n");
                    break;

                    // 设置类型，但是PHP没有ArrayBuffer，因此没有任何用
                    case 'type':
                        yield $this -> write("200 Understand.\r\n");
                    break;

                    // 设置指针位置
                    case 'rest':
                        $this -> seek = (int)$param;
                        if($this -> seek < 0) $this -> seek = 0;
                    break;

                    // 退出
                    case 'quit':
                        if($this -> data_socket)
                            fclose($this -> data_socket);
                        $this -> __destruct();
                    return;

                    // 系统类型
                    case 'syst':
                        yield $this -> write("218 " . PHP_OS . "\r\n");
                    break;

                    // 结束流
                    case 'abor':
                        if(is_resource($this -> data_socket)){
                            fclose($this -> data_socket);
                            yield $this -> write("226 Aborted.\r\n");
                        }else{
                            yield $this -> write("225 No transfer to ABOR.\r\n");
                        }
                    break;

                    case 'feat':
                        $this -> write(<<<EOF
211-Extensions supported:
 SIZE
 AVBL
 SITE
211 END\r\n
EOF);
                    break;
                    
                    // 未实现的功能
                    case 'acct':
                    case 'smnt':
                    case 'rein':
                    case 'stru':
                    case 'mode':
                    case 'allo':
                    case 'rest':
                    case 'nlst':
                    case 'help':
                        $cmd = strtoupper($cmd);
                        yield $this -> write("502 $cmd not implemented.\r\n");
                    break;

                    // PING
                    case 'noop':
                        yield $this -> write("200 NOOP ok.\r\n");
                    break;

                    // 默认应答
                    default:
                        yield $this -> write("500 Unknown command.\r\n");
                    break;
                }
            }
        }

        /**
         * 列举一个目录并且重定向到管道
         * @param string $dir 源目录
         * @param BaselinePipe $pipe 目标管道
         */
        protected function __stat_dir(string $dir,?BaselinePipe $pipe = null){
            $dir = $this -> realpath($dir) . '/';
        $fd = @opendir($dir);
            if($fd === false) return false;
            while(false !== ($fname = readdir($fd)))
                yield from $this -> __stat_file($dir . $fname,$pipe ?? $this);
            return true;
        }

        /**
         * stat一个文件并且重定向到管道
         * @param string $dir 源文件
         * @param BaselinePipe $pipe 目标管道
         */
        protected function __stat_file(string $file,BaselinePipe $pipe){
            $info = @stat($file);
            if(false === $info) return;
            // 权限位 子项目 所有用户 所有组 文件大小 更改日期 文件名
            $mod = self::mode2str($info['mode']);
            $ctime = date('M d H:i',$info['ctime']);
            $name = basename($file);
            yield $pipe -> write("$mod  1  {$info['uid']}  {$info['gid']}  {$info['size']}  $ctime  $name\r\n");
        }

        /**
         * 将一个路径转换成绝对路径
         * @param string $path 路径
         * @return string
         */
        protected function realpath(string $path){
            if($path[0] == '/')
                $str = self::$root . $path;
            else
                $str = self::$root . $this -> working_dir . '/' . $path;
            return preg_replace('/\/([\w\W^\\/]+)\/..\//i','',
                str_replace(['//','\\','/./'],['/','/','/'],$str)
            );
        }

        /**
         * 将权限数字转换为权限字符串
         * @param int $perms 权限(6位八进制)
         * @return string
         */
        static function mode2str(int $perms) {
            if (($perms & 0xC000) === 0xC000)
                $info = 's';
            elseif (($perms & 0xA000) === 0xA000)
                $info = 'l';
            elseif (($perms & 0x8000) === 0x8000)
                $info = '-';
            elseif (($perms & 0x6000) === 0x6000)
                $info = 'b';
            elseif (($perms & 0x4000) === 0x4000)
                $info = 'd';
            elseif (($perms & 0x2000) === 0x2000)
                $info = 'c';
            elseif (($perms & 0x1000) === 0x1000)
                $info = 'p';
            else
                $info = 'u';
        
            $info .= ($perms & 0x0100) ? 'r' : '-';
            $info .= ($perms & 0x0080) ? 'w' : '-';
            $info .= ($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-');
            $info .= ($perms & 0x0020) ? 'r' : '-';
            $info .= ($perms & 0x0010) ? 'w' : '-';
            $info .= ($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-');
            $info .= ($perms & 0x0004) ? 'r' : '-';
            $info .= ($perms & 0x0002) ? 'w' : '-';
            $info .= ($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-');
        
            return $info;
        }

        /**
         * 初始化流管道
         * @param resource $s 流资源
         * @param int $flag 策略，读/写
         * @return TcpPipe
         */
        private function setFlag($s,int $flag = self::T_WRITABLE){
            $tcp = new TcpPipe($s,$flag);
            EventLoop::add($s,$tcp,'ftpd');
            $ip = stream_socket_get_name($s,true);
            log("{-color_red}C{/} New FTP DataPipe establish.IP: {color_gray}$ip{/}");
            $cancel = $this -> on('close',fn() => $tcp -> __destruct());
            $tcp -> on('close',fn() => $cancel());
            return $tcp;
        }

        /**
         * 获取IP地址
         * @param string $addr 完整的地址
         */
        static function getIPAddr(string $addr){
            // 获取应该指明的IP地址
            $pl = strrpos($addr,':');
            return str_replace(['.',':'],[',',','],substr($addr,0,$pl));
        }

        /**
         * 初始化客户端连接
         * 默认为同一个IP的第一个连接为当前数据
         * @param string 客户端IP地址
         */
        public function fork_conn(){
            // 获取IP地址
            $ip = self::getIPAddr(stream_socket_get_name($this -> stream,false));
            $cip = self::getIPAddr(stream_socket_get_name($this -> stream,true));

            // Promise
            $prom = new Promise($rs,$rj);
            self::$client_bindto[$cip] = $this;
            $this -> callback = $rs;

            // 告诉客户端
            $rate = floor(self::$bind_port / 256);
            $rest = self::$bind_port % 256;
            yield $this -> write("227 Entering Passive Mode ($ip,$rate,$rest).\r\n");
            return yield $prom;
        }
    }

    class WebSocket extends Event implements PipeLiked{

        /**
         * @var BaselinePipe 基准TcpHandle
         */
        protected $handle;

        /**
         * @var bool 被锁定
         */
        protected $locked = false;

        /**
         * @var bool 是否使用mask编码
         */
        protected $use_mask = false;

        const MAX_BODY = 1 * 1024 * 1024;
        const SPLIT_BODY = 256 ** 8;

        /**
         * 创建一个WebSocket对象，兼容客户端和服务端
         * @param Pipe $handle HttpTCP连接对象，支持任何socket连接
         * @param bool $mask 是否使用Mask加密，`true`只有在客户端模式下才有效
         */
        public function __construct(Pipe $handle,bool $mask = false) {
            $this -> handle = $handle;
            $this -> use_mask = $mask;
            trigger_error('New WebSocket Object Created',E_USER_NOTICE);
        }

        /**
         * 向远程写入数据
         * 
         * **注意** 在浏览器端，如果数据流不能转换为文本且`$bin`设置为`false`，将会断开连接并报错
         *      推荐始终设置为`true`
         * 
         * @param string $content 写入内容
         * @param bool $bin 是否是二进制数据流
         * @param bool $last 是否是最后一帧
         */
        public function send(string $content,bool $bin = false,bool $last = true):Promise{
            return Promise::await($this -> _send($content,$bin,$last));
        }

        private function _send(string $content,bool $bin,bool $last){
            if(!$content) return;

            // 辨识字节
            yield $this -> handle -> write(chr(
                $bin
                ? ($last ? 0b10000010 : 0b00000010)
                : ($last ? 0b10000001 : 0b00000001)
            ));

            // 有效负载数据的长度
            $len = strlen($content);
            $mask = $this -> use_mask ? 0b10000000 : 0b00000000;
            if ($len > 0 && $len <= 125)
                yield $this -> handle -> write( chr($mask | $len));
            elseif ($len <= 65535)
                yield $this -> handle -> write(
                    pack('C*',$mask | 126,$len >> 8,$len & 0xff)
                );
            else
                yield $this -> handle -> write(
                    pack('C*',$mask | 127, $len >> 56, $len >> 48, $len >> 40, $len >> 32, $len >> 24, $len >> 16, $len >> 8, $len >> 0)
                );

            // 写入掩码和数据
            if($this -> use_mask){
                $mask = pack('C*', rand(1, 255), rand(1, 255), rand(1, 255), rand(1, 255));
                yield $this -> handle -> write($mask);
                $datalen = strlen($content);
                // 写入数据
                for ($i = 0; $i < $datalen; $i += 4)
                    yield $this -> handle -> write(
                        substr($content,$i * 4,4) ^ $mask
                    );
            // 不编码
            }else{
                yield $this -> handle -> write($content);
            }
        }

        public function status():statStruct{
            return $this -> handle -> status();
        }

        /**
         * 写入数据
         */
        public function write(string $data):Promise{
            $split = str_split($data,min(self::SPLIT_BODY,PHP_INT_MAX));
            $len = count($split);
            return Promise::await(function() use ($split,$len){
                foreach ($split as $i => $value)
                    yield $this -> send($value,true,$i == $len-1);
            });
        }

        /**
         * 传递是否存活
         */
        public function alive():bool{
            return $this -> handle -> status() -> alive;
        }

        /**
         * 传递状态
         */
        public function _status():statStruct{
            return $this -> handle -> status();
        }

        /**
         * 连续接受数据
         */
        public function onMessage(callable $cb){
            if($this -> locked)
                throw new \Error('WebSocket is locked.');
            Promise::await(function() use ($cb){
                $this -> locked = true;
                while(!$this -> handle -> status() -> eof)
                    if($data = yield $this -> _read())
                        Promise::call($cb,$data);
            });
        }

        /**
         * 读取客户端数据
         * 注意：这是异步函数，使用yield + Promise::await才能运行
         * 
         * @link https://github.com/walkor/workerman/blob/master/src/Protocols/Websocket.php
         * @link https://apifox.com/apiskills/websocket-protocol/
         */
        public function _read(){
            // 读取头
            $buffer = yield $this -> handle -> read(2);
            $firstByte = ord(@$buffer[0]);
            $secondByte = ord(@$buffer[1]);
            // 解析数据
            $dataLen = $secondByte & 127;
            $isFinFrame = $firstByte >> 7;
            $masked = $secondByte >> 7;
            $opcode = $firstByte & 0xf;

            // 解析内容长度
            if ($dataLen <= 125)
                // 数据长度: $dataLen
                $len = $dataLen;
            elseif ($dataLen == 126){
                // 数据长度: 读取2位
                $buffer = yield $this -> handle -> read(2);
                $len = unpack('n',$buffer)[1];
            }elseif ($dataLen == 127){
                // 数据长度: 读取8位
                $buffer = yield $this -> handle -> read(8);
                $len = unpack('J',$buffer)[1];
            }
            
            // 超过限制长度
            if($len > self::MAX_BODY){
                $this -> _trig('error',['content_too_long']);
                return $this -> close();
            }

            // 读取掩码
            if($masked){
                $mask = yield $this -> handle -> read(4);
                // 符合长度
                $masks = $len < 4 ? substr($mask,0,$len) : str_pad($mask,$len,$mask);
            }

            // 读取数据
            $readed = 0;
            if($masked){
                $result = '';
                do{
                    $rest = $len - $readed;
                    $read = $rest > 4 ? 4 : $rest;
                    $data = yield $this -> handle -> read($read);
                    if($read < 4) $masks = substr($masks,0,$read);
                    $result .= $data ^ $masks;
                    $readed += $read;
                }while($readed < $len);
            }else{
                $result = yield $this -> handle -> read($len);
            }

            // 解析OPCode
            switch ($opcode) {
                // ===== 数据帧 =====
                // 连续帧
                case 0x0:
                // 文本帧
                case 0x1:
                // 二进制帧
                case 0x2:
                break;
                
                // ===== 功能帧 =====
                // PING
                case 0x9:
                    if (!$isFinFrame)
                        return $this -> handle -> __destruct();
                    yield $this -> handle -> write($data);
                break;

                // PONG
                case 0xa:
                    $this -> _trig('ping');
                break;

                // 连接断开
                case 0x8:
                    $this -> _trig('close');
                    // 关闭连接
                    
                break;

                // Wrong opcode.
                default :
                    trigger_error('ClientError: Unknown websocket opcode',E_USER_WARNING);
                    $this -> close();
                break;
            }

            if($isFinFrame)
                return $result;
            else
                return $result . (yield $this -> read()); 
        }

        /**
         * 兼容的读取方法
         */
        public function read($len = -1):Promise{
            return Promise::await($this -> _read());
        }

        /**
         * 关闭WebSocket连接
         */
        public function close(int $code = 1000){
            if($code < 1000 || $code >= 5000)
                throw new \Error('illegal status code(not in range:1000-4999)');

            if($this -> use_mask)
                $this -> handle -> write(pack('C*',
                    0b10001000,     // 断开帧
                    0b00000100,     // 4byte数据
                ) . (string)$code) -> then(fn() => 
                    $this -> handle -> __destruct()
                );
            else $this -> handle -> write(pack('C*',
                    0b10001000,     // 断开帧
                    0b10000100,     // 4byte数据
                ) . 
                // 掩码
                ($mask = pack('C*',rand(0,255),rand(0,255),rand(0,255),rand(0,255))) . 
                // 数据
                ((string)$code ^ $mask)
                ) -> then(fn() => 
                    $this -> handle -> __destruct()
                );
        }
    }

    /**
     * 准备调用函数构造方法，允许连续赋值
     */
    class Prepared{

        private $callback;
        private $prepared = [];

        /**
         * 初始化Prepare
         * @param callable $cb 回调，将数据返回
         */
        public function __construct(callable $cb){
            $this -> callback = $cb;
        }

        /**
         * 继续调用，参考 `RPC.prepare()` 的JSDOC文档
         * 
         * @param callable $call 调用的函数，允许 "&.func"
         * @param array $args 参数，允许 "$[data]"
         * @return self
         */
        public function then(string $call,Array $args){
            $this -> prepared[] = [
                'call' => $call,
                'args' => $args
            ];
            return $this;
        }

        /**
         * 立即发送请求，返回最后一个函数的调用结果
         * @param bool $pcall 安全调用，不会报错
         * @return bool 结果
         */
        public function send(bool $pcall = false){
            return call_user_func($this -> callback,$this -> prepared,$pcall);
        }
    }

    /**
     * 默认RPC环境
     */
    class RPCEnv{
        protected $env;
        static $global;

        protected function _get(\stdClass &$current,array $var,bool $create){
            $len = count($var)-1;
            for ($i = 0; $i < $len; $i++) { 
                $key = $var[$i];
                if(!$current -> $key)
                    if($create)
                        $current -> $key = new \stdClass;
                    else $current = null;
                $current = &$current -> $key;
            }
        }

        public function __construct(){
            $this -> env = new \stdClass;
        }

        public function __invoke(string $var,mixed $data = PHP_INT_MAX){
            $data = explode('.',$var);
            $target = $this -> env;
            if($data !== PHP_INT_MAX){
                $this -> _get($target,$data,true);
                $target = $data;
            }else{
                $this -> _get($target,$data,true);
                if(null == $target){
                    $target = self::$global;
                    $this -> _get($target,$data,true);
                }
                return $target;
            }
        }
    }
    RPCEnv::$global = new \stdClass;

    /**
     * 标准RPC，客户端版和服务端版需要额外填充
     * 其中单下划线的是接收方法，双下划线的是实用函数
     */
    class RPC extends Event{

        /**
         * @var array 等待的请求
         */
        protected $requests = [];

        /**
         * @var \MomentCore\WebSocket WebSocket对象
         */
        protected $ws;

        /**
         * @var array 队列，当WebSocket可用时发送
         */
        protected $eque = [];

        /**
         * @var callable 调用函数、获取/设置内容的回调
         */
        public $handler;

        public function __construct(?WebSocket $ws = null){
            if($ws) $this -> bind($ws);
        }

        /**
         * 绑定一个WebSocket
         * 在Web端强烈建议不要修改此项而是使用自带的连接管理
         */
        public function bind(WebSocket $ws){

            // 发送队列数据
            foreach($this -> eque as $item)
                $ws -> send($item);
            
            // 清理数据
            $this -> eque = [];
            $this -> ws = $ws;

            // 处理字符串数据
            $ws -> onMessage(fn (string $data) => 
                $this -> __accept($data)
            );
        }

        protected function __accept(string $str){
            try{
                $data = @json_decode($str,true);
                if(!$data) return $this -> ws -> close();

                switch ($data['type']) {
                    case "reject":
                        $this -> _reject($data);
                    break;

                    case "resolve":
                        $this -> _resolve($data);
                    break;

                    case "call":
                        $cb = call_user_func($this -> handler,$data['name']);
                        if(is_callable($cb)) try{
                            $res = call_user_func($cb,$data['args']);
                            if($data['id'])
                                $this -> __show([
                                    'type' => 'resolve',
                                    'data' => $res,
                                    'id' => $data['id']
                                ]);
                            if($data['once']) call_user_func($this -> handler,NULL);
                        }catch(\Throwable $e){
                            $this -> __reject($e,$data['id']);
                        }else $this -> __reject(
                            new \TypeError("{$data['name']} is not callable")    
                        ,$data['id']);
                    break;

                    case "var":
                        $this -> _var($data);
                    break;

                    case "pcall":
                        $this -> _prepare($data);
                    break;
                }
            }catch(\Throwable $e){
                trigger_error('RPCError: ' . (string)$e,E_USER_WARNING);
            }
        }
        
        protected function __reject(mixed $e,string $id){
            if($e instanceof \Error){
                $this -> __show([
                    'type' => 'reject',
                    'name' =>  get_class($e),
                    'message' => $e -> getMessage(),
                    'trace' =>  (function() use ($e){
                        $tmp = [];
                        foreach ($e -> getTrace() as $value) {
                            if(
                                str_starts_with(@$value['class'],'\\MomentCore\\') ||
                                @$value['file'] == __FILE__
                            ) continue;
                            $tmp[] = [
                                'file' => @$value['file'],
                                'line' => @$value['line'] ?? -1,
                                'col'  => 0,
                                'func' => @$value["class"] . @$value["type"] . @$value["function"]
                            ];
                        }
                        return $tmp;
                    })(),
                    'id' => $id
                ]);
            }else{
                $this -> __show([
                    'type' => 'reject',
                    'name' => 'Error',
                    'message' => (string)$e,
                    'trace' => [],
                    'id' => $id
                ]);
            }
        }

        protected function _prepare(array $data){
            $prepared = $data['call'];
            $id = $data['id'];
            $result = [];

            // 根据索引找内容
            $getElement = function(string $key,array $current) use ($data){
                if(!$key) return $current;

                $path = explode('.',$key);
                $last = $path[count($path) -1];
                foreach ($path as $i => $next) {
                    if($current == null || !is_object($current))
                        if($data['safe']) return null;
                        else throw new \TypeError('Cannot read properties of '+gettype($current)+' (reading \'' . $next . '\')');
                    $current = $current[$next];
                }
                if(!$current || !is_object($current))
                    throw new \TypeError("Object $last not found.");
                return $current[$last];
            };

            // 解析字符串，支持插值、引用
            $parseStr = function (string $str) use ($result,$getElement){
                // 引用
                if(preg_match('/^\&([0-9]+)?(?:\.(.+))?$/;',$str,$match)){
                    $id = $match[1];
                    $path = $match[2];
                    if(!$id){
                        if(array_key_exists($id,$result))
                            $target = $getElement($path,$result[$id]);
                        else
                            throw new \TypeError("result id#$id not exists.");
                    }else
                        $target = $getElement($path,end($result));
                // 插值
                }else
                    $target = preg_replace_callback('/$(?:[0-9]+)?\[(.+)\]/g',fn($match) =>
                        (string)($getElement($match[1],end($result)) ?? ''),$str
                    );
                return $target;
            };

            // 深度搜索
            $tree = function(array $object) use ($parseStr,&$tree){

                $newData = [];

                foreach($object as $key => $element){
                    if(is_string($element)){
                        // 分析并替换
                        return $parseStr($element);
                    }else if(is_object($element)){
                        // 深度搜索
                        $newData[$key] = $tree($element);
                    }else{
                        // 直接原样传递
                        $newData[$key] = $element;
                    }
                }

                return $newData;
            };

            // 主程序
            foreach($prepared as $call){
                $target = $parseStr($call['call']);
                if(is_string($target))
                    $target = call_user_func($this -> handler,$target);
                if(is_callable($target)){
                    if($target['pipe'])
                        return $this -> __reject('PipeOnly function is not allowed.Use call() instead.',$id);
                    try{
                        $arg = $tree($call['args']);
                        if(is_object($arg))
                            $arg = get_object_vars($arg);
                        else
                            $arg = [$arg];
                        $result[] = $target($this,...$arg);
                    }catch(\Throwable $e){
                        // 安全调用不会报错
                        if(!$data['safe']) $this -> __reject($e,$id);
                        $result[] = [];
                    }
                }else $this -> __reject($call['args'] . ' is not callable',$id);
            }

            // 返回结果
            $this -> __show([
                'type' => 'resolve',
                'id' => $id,
                'data' => end($result)
            ]);
        }

        /**
         * 向远程发送调用函数请求。
         * 只能调用非pipe函数，否则会报错
         * 
         * 如果抛出错误，将传递并抛出RPCError
         * 大部分报错内容会直接传递，做到好像真的是本地调用
         * 
         * ```
         * // expect: hello everyone
         * await RPC.call('a.b.c.d.hello',['hello','everyone']);
         * ```
         * @param string $func 调用的内容，一般是函数名
         * @param array $args 参数，调用函数时会用到
         * @return mixed 返回的内容
         */
        function call(string $func,array $args = []){
            $id = uniqid('rpc');
            $prom = new Promise($rs,$rj);
            $this -> __show([
                'type' => 'call',
                'args' => $args,
                'id' => $id,
                'name' => $func
            ]);
            $this -> requests[$id] = [
                "clear" => "once",
                "handle" => $rs,
                "error" => $rj,
                'id' => $id
            ];
            return $prom;
        }

        /**
         * 向远程发送一个操作变量请求
         * 如果提供了第二个参数，则认为是赋值操作
         * 
         * 这个函数不会报错，请放心使用
         * 
         * 类型检查提供了简易的方法在对方发送前判断类型
         * 比如需要一个Boolean却返回了一个超大的Object，这个方法就十分有用
         * **注意** `$check`的字符串只能是JS `typeof`的返回值
         * 
         * ```
         * $data = 'hello';
         * await RPC.query('test.temp',data);
         * await RPC.query('test').temp == data;   // true
         * await RPC.query('test',null,'boolean'); // null
         * ```
         * @param string $name 变量名
         * @param mixed $value 赋值
         * @param string $check 类型检查
         * @returns 变量内容
         */
        function query(string $name,mixed $value,string $check){
            $id  = uniqid('rpc');
            $this -> __show([
                'type' => 'var',
                'data' => $value,
                'var' => $name,
                'check' => $check,
                'id' => $id
            ]);
            $prom = new Promise($rs,$rj);
            if(!$value) $this -> requests[$id] = [
                'clear' => 'once',
                'handle' => $rs,
                'id' => $id
            ];
            else $rs(null);
            return $prom;
        }

        /**
         * RPC3 扩展 连续赋值调用
         * 可以让函数返回值立即用于调用其他函数
         * 
         * 虽然这是一个实验性的功能，但是很好用！
         * 
         * # 示例
         * 
         * 在任何地方，只需要以 `$[a.b.c]` 即可插值，如
         * ```js
         * // 假设1返回值是 ['Good morning!','zlh',{a:1,b:{c:'Hello again'}}]
         * "$[1]hello,im$[2]"   // 在Array返回值中提取
         * "&.3.b"              // 此处的&表示引用，其指向内容 可以为任何值，包括Object
         * "$[3.b]"             // 此时由于RPC调用.toString()方法，变成了[Object object]/
         * "$[4.b]/"            // Uncaught TypeError，如果设置了safeMode则不插入任何值
         * ```
         * 但是我们不推荐复杂的Object传入，开销很大
         * 
         * # 语法
         * 
         *  - `&` 用于引用内容，必须顶格且不能有空格，如 `&.body.getReader`
         *    **注意** 引用后字符串将替换为指向的内容，不一定是string
         *  - `$[]` 表示插值，所有插入的内容会转换为String，可以在任何地方出现
         * 
         * **注意** 如果指向不存在的内容，会触发错误。在 send() 第一个参数设置为 `true` 即可使用安全模式
         * 
         * ```
         * // 需要将Deno和fetch对象暴露，即Env.provide(...);
         * RPC.prepare('Deno.open', ['/demo.mp4',{read: true,write: false}])
         *      .then('fetch',[{
         *           body: "&.readable",   // 这个地方使用了引用
         *           method: 'POST',
         *           headers: {
         *               'Content-Type': 'video/mpeg4'
         *           }
         *       }])
         *       // &表示对上一个结果的引用，只限于参数1中
         *       .then('&.json',[])
         *       // 使用自带的函数echo，回显结果
         *       .then('echo',"state: $[code]",true);
         * ```
         * @param string $name 调用的函数，允许 "&.func"
         * @param array $args 参数，允许 "$[data]"
         * @return Prepared
         */
        function prepare(string $name,array $args){
            $id = uniqid('rpc');
            return (new Prepared(fn($call,$safe) => 
                Promise::new(function($rs,$rj) use ($call,$safe,$id){
                    $this -> __show([
                        'type' => 'pcall',
                        'call' => $call,
                        'id' => $id,
                        'safe' => $safe
                    ]);
                    $this -> requests[$id] = [
                        "clear" => "once",
                        "handle" => $rs,
                        "error" => $rj,
                        'id' => $id
                    ];
                })
            )) -> then($name,$args);
        }

        /**
         * 输出到客户端
         */
        protected function __show(array $data){
            $str = json_encode($data);

            // 删除handle
            if(array_key_exists($data['id'], $this -> requests) && $this -> requests[$data['id']]['clear'] == 'once')
                unset($this -> requests[$data['id']]);

            // 加入队列
            if($this -> ws) $this -> ws -> send($str);
            else $this -> eque[] = $str;
        }

        /**
         * 响应一个请求
         */
        protected function _resolve(array $data){
            $handle = $this -> requests[$data['id']];
            $handle['handle']($data['data']);
        }

        /**
         * 回拒一个请求
         */
        protected function _reject($data){
            $handle = $this -> requests[$data['id']];
            if($handle['error']) $handle['error'](new \Error("{$data['name']}: {$data['message']}"));
            else trigger_error("RPC UncaughtError: {$data['name']}: {$data['message']}\n" . join(PHP_EOL,array_map(
                fn($data) => "\t at {$data['file']}:{$data['line']}:{$data['col']}",$data['trace']
            )),E_USER_ERROR);
        }

        /**
         * 客户端调用: var方法
         */
        protected function _var(array $data){
            if(!is_string($data['var']))
                return $this -> __reject(new \TypeError( 'invalid data found.' ),$data['id']);
            if($data['data']) call_user_func($this -> handler,$data['var'],$data['data']);
            else {
                $result = call_user_func($this -> handler,$data['var']);
                if($data['check'] && (([
                        'boolean' => 'boolean',
                        'integer' => 'number',
                        'double' => 'number',
                        'float' => 'number',
                        'string' => 'string',
                        'array' => 'object',
                        'object' => 'object',
                        'resource' => 'object',
                        'resource (closed)' => 'object',
                        'NULL' => 'null'
                    ])[gettype($result)] ?? 'object') != $data['check'])
                    $result = null;
                $this -> __show([
                    'type' => 'resolve',
                    'data' => $result,
                    'id' => $data['id']
                ]);
            }
        }
    }

    class FtpClient{

        const T_OK_PREPARE_DATA = 1;
        const T_OK_FINISH = 2;
        const T_OK_WAIT_CLIENT = 3;
        const T_ERR_TEMPORARY = 4;
        const T_ERR_NEVER = 5;

        const M_SYNTAX_ERROR = 0;
        const M_MESSAGE = 1;
        const M_CONNECT = 2;
        const M_USER = 3;
        const M_MIXIN = 4;
        const M_FS_STATE = 5;

        const STATUS_TRANSMISSION_START = 125;
        const STATUS_READY = 214;
        const AUTH_PW_REQUIRED = 331;
        const PASV_MODE = 425;
        const FILE_WRITE_FAILED = 452;
        const COMMAND_NOT_FOUND = 500;
        const SYNTAX_ERROR = 501;
        const COMMAND_NOT_IMPLERED = 502;
        const NO_USER_EXPLAINED = 503;
        const NOT_LOGINED = 530;

        /**
         * @var TcpPipe 管道
         */
        protected $pipe;

        /**
         * 打开一个Ftp连接
         * 在vThread下同步执行
         * @param string $addr 远程地址
         * @param int $port 远程端口
         * @param string $user 登录用户名
         * @param string $password 密码
         * @return Promise|self
         */
        static function open(string $addr,string $user,string $password,int $port = 21){
            $prom = Promise::await(self::__open($addr,$port,$user,$password));
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        /**
         * @internal
         */
        static private function __open($addr,$port,$user,$password){
            $tcp = client($addr,$port);
            $client = new self($tcp);

            // 读取服务端消息
            trigger_error('FTP: ' . yield $tcp -> readline(),E_USER_NOTICE);
            
            // 登录
            $p1 = yield from $client -> sendCmd("USER $user");
            $p2 = yield from $client -> sendCmd("PASS $password");
            
            // 登录是否成功
            if($p1[0] != self::T_OK_WAIT_CLIENT || $p2[0] != self::T_OK_FINISH)
                throw new \Error('Error: login failed');

            // 设置UTF-8
            if((yield from $client -> sendCmd('OPTS UTF8 ON'))[0] != self::T_OK_FINISH)
                trigger_error('FTP: set utf-8 failed');
            
            return $client;
        }
        
        public function __construct(TcpPipe $pipe) {
            $this -> pipe = $pipe;
        }

        /**
         * vThread内同步执行一条**无**数据的命令
         * @return array
         */
        public function sendSync(string $cmd){
            return \Fiber::suspend($this -> exec($cmd));
        }

        /**
         * 向服务器发送一条**无**数据的命令
         * @param string $cmd 命令
         */
        public function sendCmd(string $cmd,?callable $ondata = null){
            // 写入命令
            yield $this -> pipe -> write($cmd . "\r\n");

            // 读取响应
            $data = [];
            $line = yield $this -> pipe -> readline();
            if(!preg_match('/^\\s*([0-9]{1,3})(?:-([a-z]+))?\\s*([\w\W]+)$/i',$line,$match)){
                $this -> pipe -> __destruct();
                throw new \Error('incorrect FTP response found');
            }

            // 响应类型
            $mode = (int)$match[1][1];
            $type = (int)$match[1][2];
            $code = (int)$match[1];

            // 错误响应
            if($mode == self::T_ERR_NEVER)
                throw new \ParseError($match[3]);
            // block
            if($match[2])
                while(true){
                    $line = yield $this -> pipe -> readline();
                    // close标志
                    if(str_starts_with($line,$match[1]))
                        return  [
                            $mode, 
                            $type, 
                            $code,
                            join(PHP_EOL,$data)
                        ];
                    // 输出到函数
                    elseif($ondata)
                        $ondata($line);
                    // 输出到array
                    else
                        $data []= $line;
                }

            return [$mode, $type, $code, $match[3]];
        }

        private function __pasv(string $cmd,int $mode = TcpPipe::T_READABLE){
            // 获取端口
            $line = (yield from $this -> sendCmd('pasv'))[3];
            if(!preg_match(
                '/\\(([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3}),([0-9]{1,3})\\)/',
                $line,$match
            )) throw new \Error('Enter PASV mode failed');
            $addr = "{$match[1]}.{$match[2]}.{$match[3]}.{$match[4]}";
            $port = (int)$match[5] * 256 + (int)$match[6];

            // 打开端口
            $c = client($addr,$port,mode:$mode);

            // 执行命令
            $data = yield from $this -> sendCmd($cmd);
            if(($mode & TcpPipe::T_READABLE) && $data[0] != self::T_OK_PREPARE_DATA)
                throw new \TypeError('Not contain data');

            return $c;
        }

        /**
         * vThread内同步执行一条**有**数据的命令
         * @return string
         */
        public function execSync(string $cmd){
            return \Fiber::suspend(Promise::await($this -> exec($cmd)));
        }

        /**
         * 向服务器发送一条**有**数据的命令
         * @param string $cmd 命令
         */
        public function exec(string $cmd){
            // 获取端口
            $recv_socket = yield from $this -> __pasv($cmd);

            // 获取结果
            $data = '';
            do
                $data .= yield $recv_socket -> read();
            while(!$recv_socket -> status() -> eof);

            // 查看是否结束
            $pread = (int)((yield $this -> pipe -> readline())[0]);
            if($pread != self::T_OK_FINISH)
                throw new \Error('Short readed: server unexpectedly closed the connection');

            // 返回
            return $data;
        }

        public function cwd(string $dir){
            $dat = yield $this -> sendCmd("CWD $dir");
            if($dat[0] == self::T_OK_FINISH)
                return true;
            else{
                trigger_error('Failed to cwd(): ' . $dat[3],E_USER_WARNING);
                return false;
            }
        }

        private function __sync_pipe($cmd,$then,$type = TcpPipe::T_READABLE){
            $prom = new Promise($rs,$rj);
            Promise::await($this -> __pasv($cmd,$type)) -> then(
                fn($rt) => $then($rt,$rs)
            ) -> catch($rj);
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        private function __sync($cmd,$then){
            $prom = new Promise($rs,$rj);
            Promise::await($this -> sendCmd($cmd)) -> then(
                fn($rt) => $then($rt,$rs)
            ) -> catch($rj);
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        /**
         * 打开一个目录
         * @return Promise|FakeReadFile
         */
        public function opendir(string $dir){
            return $this -> __sync_pipe("LIST $dir",fn($rt,$rs) => $rs(new FakeReadFile($rt)));
        }

        /**
         * 获取文件大小
         * @return Promise|int
         */
        public function fsize(string $file){
            return $this -> __sync("SIZE $file",fn($rt,$rs) => $rs((int)$rt[3]));
        }

        /**
         * 删除文件
         * @return Promise|true
         */
        public function delete(string $file){
            return $this -> __sync("SIZE $file",fn($rt,$rs) => $rs(true));
        }

        /**
         * 创建文件夹
         * @return Promise|true
         */
        public function mkdir(string $file){
            return $this -> __sync("MKD $file",fn($rt,$rs) => $rs(true));
        }

        /**
         * 删除文件夹
         * @return Promise|true
         */
        public function rmdir(string $file){
            return $this -> __sync("RMD $file",fn($rt,$rs) => $rs(true));
        }

        /**
         * 重命名文件
         * @return Promise
         */
        function move(string $from,string $to){
            $prom = Promise::await(function() use ($from,$to){
                $rt0 = yield from $this -> sendCmd("RNFR $from");
                if($rt0[0] != self::T_OK_WAIT_CLIENT)
                    throw new \Error("Lock origin file failed: {$rt0[3]}");
                $rt1 = yield from $this -> sendCmd("RNTO $to");
                if($rt1[0] != self::T_OK_FINISH)
                    throw new \Error("Move Failed: {$rt1[3]}");
                return true;
            });
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        /**
         * 读取文件
         * @return Promise|TcpPipe
         */
        public function get(string $file,int $seek = -1){
            if($seek == -1)
                return $this -> __sync_pipe("RETR $file",fn($rt,$rs) => $rs(true));
            else
                $prom = Promise::await(function() use ($seek,$file){
                    yield from $this -> sendCmd('TYPE i');
                    $rt = yield from $this -> sendCmd("REST $seek");
                    if($rt[0] != self::T_OK_WAIT_CLIENT)
                        throw new \Error('Failed to seek(): '.$rt[3]);
                    $pipe = yield from $this -> __pasv("RETR $file");
                    yield from $this -> sendCmd('TYPE a');
                    return $pipe;
                });
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        /**
         * 打开一个可写的远程文件管道
         * 可以向管道写入数据,支持SEEK
         */
        public function writable(string $to,bool $append = false){
            return $this -> __sync_pipe(
                $append ? "APPE $to" : "STOR $to",
                function($pipe){
                    $pi = new TcpPipe($pipe);
                    EventLoop::add($pipe,$pi,'ftp');
                    return $pi;
                },
                TcpPipe::T_WRITABLE
            );
        }

        /**
         * 销毁FTP
         */
        public function __destruct(){
            Promise::await($this -> sendCmd('QUIT'))
                -> then(fn() => $this -> pipe -> __destruct());
        }
    }

    class FDStruct{
        /**
         * @var string 权限模式
         */
        public $mode;

        /**
         * @var int 所有用户ID
         */
        public $user;

        /**
         * @var int 所有组ID
         */
        public $group;

        /**
         * @var int 子文件(夹)个数
         */
        public $child;

        /**
         * @var int 文件大小
         */
        public $fsize;

        /**
         * @var string 更改时间
         */
        public $modified;

        /**
         * @var bool 是否是文件
         */
        public $is_file;

        /**
         * @var string 文件名称
         */
        public $name;

        /**
         * 转string
         */
        public function __toString(){
            return $this -> name;
        }
    }

    class FakeReadFile{
        /**
         * @var TcpPipe 管道
         */
        protected $socket;

        /**
         * @param TcpPipe $pipe
         */
        public function __construct(TcpPipe $pipe) {
            $this -> socket = $pipe;
        }

        /**
         * @return FDStruct|Promise
         */
        public function next(){

            if(!$this -> socket -> status() -> alive)
                throw new \Error('end of the list');

            $prom = new Promise($rs,$rj);
            $this -> socket -> readline() -> then(function($line) use ($rs){
                // 权限位 子项目 所有用户 所有组 文件大小 更改日期 文件名
                $dat = preg_match('/^([-rwxst]+)\\s+([0-9]+)\\s+([0-9]+)\\s+([0-9]+)\\s+([0-9]+)\\s+([a-z]{3}\\s+[0-9]{2}\\s+[0-9:]{3,5}+)\\s+([\w\W]+)\\s*$/i',$line,$match);
                $str = new FDStruct;
                $str -> mode = $match[0];
                $str -> child = (int)$match[1];
                $str -> user = (int)$match[2];
                $str -> group = (int)$match[3];
                $str -> fsize = (int)$match[4];
                $str -> modified = $match[5];
                $str -> name = $match[6];
                $rs($str);
            }) -> catch($rj);

            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }

        /**
         * @return array|Promise
         */
        public function all(){
            $prom = Promise::await((function(){
                $arr = [];
                while(true) try{
                    $arr[] = yield from $this -> next();
                }catch(\Throwable){
                    return $arr;
                }
            })());
            if(\Fiber::getCurrent()) return \Fiber::suspend($prom);
            else return $prom;
        }
    }

    /**
     * 文件处理类，此可以轻松替换rename类函数
     */
    class FileObject{
        public 
            $data,  // 文件数据
            $name,  // 文件名称
            $type,  // 文件类型(MIME)
            $form;  // 在form中它的名称

        /**
         * 解析内容
         * 
         * @param string $file 要保存的原生POST内容
         */
        function __construct(string $file){
            list($head,$this->data) = explode("\r\n\r\n",$file,2);
            $tmp = [];              // 读取头部
            foreach(preg_split('/[\r\n]+/',$file) as $n){
                if($n == '') return;
                list($n,$v) = explode(':',$n);
                $tmp[strtolower(trim($n))] = trim($v);
            }
            $this->type = $tmp['content-type']??'text/plain';// 如果是文本，没有MIME
            list($type,$name,$fname) = explode(';',$tmp['content-disposition']);
            if(is_null($fname) or is_null($name)) return;   // 错误的文件
            $this->name = trim(explode('=',$fname,2)[1]);
            $this->form = trim(explode('=',$name,2)[1]);
        }

        /**
         * 保存文件
         * 
         * @param string $path 保存到的路径
         * @param bool $force 强制保存
         * @return bool 是否成功
         */
        function save(string $path,bool $force = false){
            if(!is_dir(dirname($path))) 
                if($force) 
                    mkdir(dirname($path));
                else return false;
            if(file_exists($path) and !$force)
                return false;
            try{
                $fp = open($path,'w');
                $fp -> write($this -> data);
                return true;
            }catch(\Throwable){
                return false;
            }
        }
        
    }

    /**
     * 轻量级二进制JSON数据
     */
    class JsonDB{

        const T_FALSE = 0;
        const T_TRUE = 1;
        const T_OBJECT = 2;
        const T_STRING = 3;
        const T_INT = 4;
        const T_NEGATIVE_INT = 5;
        const T_FLOAT = 6;
        const T_NEGATIVE_FLOAT = 7;
        const T_DOUBLE = 8;
        const T_NEGATIVE_DOUBLE = 9;
        const T_NULL = 10;

        /**
         * 从一个流中解码bJSON
         * 
         * @param BaselinePipe $pipe 管道
         * @return Promise 返回一个bJSON对象
         */
        static function decode(BaselinePipe $pipe){
            $prom = new Promise($rs,$rj);
            Promise::await(self::_decode($pipe))
                -> then(fn($data) => $rs($data[1])) 
                -> catch($rj);
            return $prom;
        }

        static private function _decode($pipe){
            $data = yield $pipe -> read(2);
            $type = ord($data[0]) >> 4;         // 类型
            $nlen = (ord($data[0]) & 0b1111) << 8 + ord($data[1]);
            $key = yield $pipe -> read($nlen);  // 键名

            if($type == self::T_FALSE) return [$key,false];
            elseif($type == self::T_TRUE) return [$key,true];
            elseif($type == self::T_NULL) return [$key,null];

            // body长度
            $lenbyte = ord(yield $pipe -> read(1));
            $mode = $lenbyte >> 6;
            $rest = $lenbyte & 0b00111111;
            if($mode == 0b00){
                $len = $lenbyte & 0b01111111;
            }elseif($mode == 0b01){
                $len = $rest + unpack('n',yield $pipe -> read(2))[1];
            }elseif($mode == 0b10){
                $len = $rest + unpack('N',yield $pipe -> read(4))[1];
            }else{
                $len = $rest + unpack('J',yield $pipe -> read(8))[1];
            }

            switch($type){
                case self::T_FLOAT:
                    $data = unpack('G',yield $pipe -> read($len))[1];
                break;

                case self::T_NEGATIVE_FLOAT:
                    $data = -unpack('G',yield $pipe -> read($len))[1];
                break;

                case self::T_DOUBLE:
                    $data = unpack('E',yield $pipe -> read($len))[1];
                break;

                case self::T_NEGATIVE_DOUBLE:
                    $data = -unpack('E',yield $pipe -> read($len))[1];
                break;

                case self::T_INT:
                    $data = 0;
                    $body = yield $pipe -> read($len);
                    for ( $i = strlen($body)-1 ; $i >= 0 ; $i--)
                        $data += ord($body[$i]) << (8 * $i);
                break;

                case self::T_NEGATIVE_INT:
                    $data = 0;
                    $body = yield $pipe -> read($len);
                    for ( $i = strlen($body)-1 ; $i >= 0 ; $i--)
                        $data += ord($body[$i]) << (8 * $i);
                    $data = -$data;
                break;

                case self::T_OBJECT:
                    $data = [];
                    for ( $i = 0 ; $i < $len ; $i++) { 
                        $_data = yield self::_decode($pipe);
                        $data[$_data[0]] = $_data[1];
                    }
                break;

                case self::T_STRING:
                    $data = yield $pipe -> read($len);
                break;

                default:
                    trigger_error('Unsupport type: ' . $type,E_USER_WARNING);
                    $data = yield $pipe -> read($len);
                break;
            }
            return [$key,$data];
        }

        /**
         * 编码一个对象，支持任意数据
         * 不支持的对象(如`resource`)会编码为`string`
         * 
         * @param mixed $data 数据
         * @param BaselinePipe $pipe 编码输出管道
         * @return Promise
         */
        static function encode(mixed $data,BaselinePipe $pipe){
            return Promise::await(self::_encode('[main]',$data,$pipe));
        }

        static private function _encode($key,$data,$pipe){
            $type = @[
                'integer' => self::T_INT,
                'double' => self::T_DOUBLE,
                'float' => self::T_FLOAT,
                'string' => self::T_STRING,
                'array' => self::T_OBJECT,
                'object' => self::T_OBJECT,
                'null' => self::T_NULL
            ][strtolower(gettype($data))];
            if(null === $type){
                $type = self::T_STRING;
                $data = (string)$data;
            }
            if(
                ($type == self::T_INT || $type == self::T_DOUBLE || $type == self::T_FLOAT) &&
                $type < 0
            ) $type ++;

            // 写入类型+键
            $keylen = strlen($key);
            if($keylen >= 0xfff)
                throw new \Error('object key length exceded maxlen(0xff)');
            yield $pipe -> write(chr(($type << 4) + ($keylen >> 8)) . chr($keylen));
            yield $pipe -> write($key);

            // 写入主数据
            switch($type){
                case self::T_DOUBLE:
                case self::T_NEGATIVE_DOUBLE:
                    $data = pack('E',abs($data));
                break;

                case self::T_FLOAT:
                case self::T_NEGATIVE_FLOAT:
                    $data = pack('G',abs($data));
                break;

                case self::T_INT:
                case self::T_NEGATIVE_INT:
                    $rest = abs($data);
                    for ( $i = 0 ; $rest > 0 ; $i++)
                        $data = chr($rest = $rest >> (8 * $i)) . $data;
                break;

                case self::T_FALSE:
                case self::T_TRUE:
                case self::T_NULL:
                return;

                case self::T_OBJECT:
                    // 取出对象变量
                    $data = is_array($data)
                        ? $data
                        : get_object_vars($data);
                    $len = count($data);
                    // 编码子对象数量
                    if($len >= 0b1111111){
                        yield $pipe -> write(
                            chr(($len & 0b1111111) | 0b10000000) . 
                            pack('J',$len >> 7)
                        );
                    }else{
                        yield $pipe -> write(chr($len));
                    }
                    // 编码子对象
                    foreach ($data as $key => $value)
                        yield self::_encode($key,$value,$pipe);
                return;
            }
            
            // 写入主体大小
            $len = strlen($data);
            if($len >= 0b1111111){
                yield $pipe -> write(
                    chr(($len & 0b1111111) | 0b10000000) . 
                    pack('J',$len >> 7)
                );
            }else{
                yield $pipe -> write(chr($len));
            }
            yield $pipe -> write($data);
        }
    }

    /**
     * 轻量JSON数据库
     */
    class ReactiveTable{
        static $table = [];
        static $changed = false;

        /**
         * @var array 数据
         */
        private $data = [];

        public function __construct(array &$db){
            $this -> data = &$db;
        }

        public function __get(string $item){
            if(!array_key_exists($item,$this -> data))
                return null;
            $data = $this -> data[$item];
            if(is_array($data))
                return new ReactiveTable($data);
            else return $data;
        }

        public function __set(string $item, $value){
            if(is_object($value))   try{
                    $value = (array)$value;
                }catch(\Throwable){
                    throw new \Error("Unable to set $item: convert failed.");
                }
            if(is_resource($value))
                throw new \Error("Unable to set $item: store resource is illegal.");
            self::$changed = true;
            $this -> data[$item] = $value;
        }

        public function __unset(string $item){
            if(!array_key_exists($item,$this -> data))
                return false;
            unset($this -> data[$item]);
        }

        public function __isset(string $item){
            return array_key_exists($item,$this -> data);   
        }
    }

    /**
     * 主EventLoop
     */
    class EventLoop{

        static $server = [];
        static $handler = [];
        static private $stream = [];
        static private $client = [];

        static $timetable = [];

        /**
         * @var int 读取缓冲区大小
         */
        static $READ_BUFFER_SIZE = 128 * 1024;

        /**
         * @var int 写入缓冲区大小
         */
        static $WRITE_BUFFER_SIZE = 128 * 1024;

        /**
         * @var int CHUNK分块大小
         */
        static $CHUNK_SIZE = 64 * 1024;

        /**
         * @var int 最多stream对象个数
         */
        static $MAX_CLIENT = 256;

        /**
         * @var int (微秒为单位) EventLoop延迟时间
         */
        static $INTERVAL_SLEEP = 1000;

        /**
         * 向EventLoop加入一个对象
         * 可以是这些
         *  - `open()`      等打开的文件IO
         *  - `fsockopen()` 等打开的网络IO
         *  - `exec()`      等打开的进程输入输出IO
         * 
         * @param resource $stream Resource对象
         * @param PipeFactory $data 对应的Handler，必须具有`_read` `_write`函数
         * @param string $type 管道类型,可以自由取名,方便Debug
         * @return string 管道ID
         */
        static function add($stream,PipeFactory $data,string $type){
            if(!is_resource($stream))
                throw new \Error('Add a non-resource Client is illegal');
            $id = $type . ':' . uniqid();
            self::$stream[$id] = $stream;
            self::$client[$id] = $data;

            // 设置socket非阻塞
            $error = [];
            $error[] = stream_set_blocking($stream, false) == false;
            $error[] = !!stream_set_read_buffer($stream, self::$READ_BUFFER_SIZE /2);
            $error[] = !!stream_set_write_buffer($stream  , self::$WRITE_BUFFER_SIZE /2);
            $error[] = stream_set_chunk_size($stream, self::$CHUNK_SIZE) != self::$CHUNK_SIZE;
            if(in_array(false,$error,false))
                trigger_error('Set BufferSize for ['.$id.'] Failed',E_USER_NOTICE);

            return $id;
        }

        /**
         * 主函数
         */
        static function start(){

            while(true){

                $readable = [];
                $writable = [];
                $server = self::$server;
                $null = NULL;

                if(count($server) > 0 && stream_select($server,$null,$null,0) > 0){
                    // 接受客户端
                    foreach($server as $sid => $server)
                        self::$handler[$sid](stream_socket_accept($server, 0, $name),$name);
                }

                // 检查读写需求
                foreach (self::$client as $uuid => $handle){
                    if(!$handle -> _status()){
                        unset(self::$client[$uuid],self::$stream[$uuid]);
                        trigger_error("Socket [$uuid] Closed",E_USER_NOTICE);
                        continue;
                    }
                    if($handle -> status() -> _read)
                        $readable[$uuid] = self::$stream[$uuid];
                    if($handle -> status() -> _write)
                        $writable[$uuid] = self::$stream[$uuid];
                }

                // 检查是否有客户端
                if(count($readable) + count($writable) == 0)
                    continue;

                // 核心select()
                if(stream_select($readable,$writable,$null,0,0) > 0){
                    
                    // 读取客户端
                    foreach ($readable as $client => $_)
                        self::$client[$client] -> _read();

                    // 写入
                    foreach ($writable as $client => $_)
                        self::$client[$client]->_write();
                }  

                // 调用定时器
                self::_feedCron();
                    
                // 避免CPU占用率高
                // usleep(self::$INTERVAL_SLEEP);
                sleep(0);
            }
        }

        static private function _feedCron(){
            $now = microtime(true) * 1000;
            foreach(self::$timetable as $id => &$timer){
                if($now - $timer['last'] < $timer['interval']) continue;
                $timer['last'] = $now;
                Promise::call($timer['callback'],$timer['args'])
                    -> catch(function($e) use ($id){
                        if($e instanceof Signal)
                            unset(self::$timetable[$id]);
                        elseif($e instanceof \Throwable)
                            trigger_error('Failed to enable Timer: ' . $e -> getMessage(),E_USER_WARNING); 
                    });
            }
        }
    }

    // ==============================================================================
    // =============== 实用函数开始 ==================================================
    // ==============================================================================

    /**
     * 定时执行回调
     * 当抛出 `Signal` 时，定时器将自动销毁
     * @param int $after_ms 执行间隔时间
     * @param callable $callback 回调函数
     * @param array $args参数列表
     * @link https://developer.mozilla.org/zh-CN/docs/Web/API/setInterval
     * @return callable 用于取消的函数,调用可以取消定时器
     */
    function interval(callable $callback,int $after_ms,... $args){
        if($after_ms <= 0)
            throw new \Error('illegal interval_time');
        try{
            Promise::await(call_user_func_array($callback,$args));
            EventLoop::$timetable[$uid = uniqid('t_')] = [
                'last'     => microtime(true) * 1000,
                'interval' => $after_ms,
                'callback' => $callback,
                'args'     => $args ?? []
            ];
            return function() use ($uid){
                unset(EventLoop::$timetable[$uid]);
            };
        }catch(\Throwable $e){
            trigger_error('Failed to enable Timer: ' . $e -> getMessage(),E_USER_WARNING); 
            return false;
        }
    }

    /**
     * 简便的方法，延迟一段时间后实现Promise
     * @param int $after_ms 延迟时间
     * @return Promise 一段时间后实现的Promise
     */
    function delay(int $after_ms){
        $prom = new Promise($rs,$rj);
        if($after_ms <= 0)
            return $rj(new \Error('illegal delay_time'));
        $id = uniqid('t_');
        EventLoop::$timetable[$id] = [
            'last'     => microtime(true) * 1000,
            'interval' => $after_ms,
            'callback' => function() use ($rs,$id){
                try{
                    $rs(null);
                }catch(\Throwable $e){
                    trigger_error('TimerError(line ' . $e -> getLine() . '):' . $e -> getMessage(),E_USER_NOTICE);
                }
                unset(EventLoop::$timetable[$id]);
            },
            'args'     => $args ?? []
        ];
        return $prom;
    }

    /**
     * 执行PHP脚本并将输出重定向到HTTPClient
     * @param string $path 文件位置
     * @param HttpHandle $handle 相对于的Handler
     * @param array $param 传入脚本的参数
     */
    function run(string $path, HttpHandle $handle, array $param = []){
        if(!($path = @realpath($path)))
            throw new \Error("File not exists");

        // 优化变量
        static $server_name;
        if(!$server_name) $server_name = gethostname();
        static $server_addr;
        if(!$server_addr) $server_addr = gethostbyname($server_name);
        $server = [
            'PHP_SELF' => $path,
            'argv' => $handle -> client -> param ?? [],
            'GATEWAY_INTERFACE' => 'CGI/1.1',
            'SERVER_ADDR' => $server_addr,
            'SERVER_NAME' => $server_name,
            'SERVER_SOFTWARE' => 'moment',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => $handle -> client -> method,
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'DOCUMENT_ROOT' => __CONFIG__['rounger']['root'],
            'REMOTE_ADDR' => $handle -> addr,
            'REMOTE_PORT' => @end(explode(':',$handle -> addr)),
            'SCRIPT_FILENAME' => $path,
            'SCRIPT_NAME' => basename($path),
            'REQUEST_URI' => $handle -> client -> path,
            'QUERY_STRING' => $handle -> client -> param_str
        ];
        ($fn_run = function() use ($handle,$server){
            $GLOBALS['__HANDLE__'] = $handle;
            $_GET = $handle -> client -> param ?? [];
            $_POST = $handle -> client -> post ?? [];
            $_SERVER = $server;
            $_REQUEST = ($handle -> client -> method == 'POST' 
                ? $handle -> client -> post
                : $handle -> client -> param) || [];
            $_ENV = $handle -> client -> header;
        })();
        \array_unshift($param, $handle);

        // 超时检测
        $done = false;
        delay(__CONFIG__['rounger']['script_timeout'] * 1000) 
            -> then(function() use (&$done,$handle){
                if(!$done){
                    $GLOBALS['__HANDLE__'] = null;
                    @ob_flush();
                    $handle -> finish(500,'Gateway Timeout');
                    $done = true;
                }
            });

        // 获取入口函数
        static $cache = [];
        $include = true;
        if (__CONFIG__['rounger']['script_cache'])
        if (array_key_exists($path, $cache)) {
            $tg = $cache[$path];
            clearstatcache(false, $path);
            if (
                // 在timeout前，有效
                $tg['updated'] + __CONFIG__['rounger']['cache_timeout'] >= time() ||
                // 文件未更改
                $tg['mtime'] == filemtime($path)
            ) {
                $func = $tg['func'];
                $handle->header('X-Cache', 'hit');
                $include = false;
            } else {
                $handle->header('X-Cache', 'expired');
            }
        } else {
            $handle->header('X-Cache', 'miss');
        }

        return go(function () use (&$cache, $path, $handle, &$done, $include,&$func) {
            if ($include) try {
                $cache[$path] = [
                    'func' => $func = include('fs://' . $path),
                    'mtime' => filemtime($path),
                    'updated' => time()
                ];
            } catch (\ParseError $e) {
                return $handle->finish(
                    500,
                    '<p><b>SyntaxError</b>: ' . $e->getMessage() . '<br>at line ' . $e->getLine() . '</p>'
                );
            } catch (\Throwable $e) {
                return $handle->finish(
                    500,
                    '<p><b>InitError</b>: ' . $e->getMessage() . '<br>at line ' . $e->getLine() . '</p>'
                );
            }

            if($func == false){
                $done = true;
                return $handle -> finish(403, 'Failed to open script');
            }
            if (!is_callable($func)){
                $done = true;
                return $handle -> finish(500, 'main function not found');
            }

            try {
                @ob_clean();
                $func($handle);
                @ob_flush();
            } catch (\Throwable $e) {
                if (!$done){
                    $handle -> http_status = 500;
                    echo parseError($e);
                    ob_flush();
                }
                return;
            } finally {
                $done = true;
            }

            if ($handle -> state() == $handle::PENDING)
                $handle -> finish();
            elseif($handle -> state() == $handle::PENDING)
                $handle -> endChunk();
        }, onext: $fn_run) -> then(fn() => $GLOBALS['__HANDLE__'] = null);
    }

    /**
     * 解析错误调用，屏蔽此文件的函数调用
     * 可以使用以下代码装饰
     * ```css
     * div.__error {
     *    position: fixed;
     *    top: 50vh;
     *    left: 50vw;
     *    width: 60vw;
     *    transform: translate(-50%, -50%);
     *    padding: 1.5rem;
     *    background-color: #e17272;
     *    border-left: solid .6rem #34e0e6;
     *    color: white;
     *    box-shadow: #c7bebe 0 0 1rem;
     * }
     * ```
     * @param \Throwable $data Error对象
     * @return string 返回的HTML数据
     */
    function parseError(\Throwable $data){
        $name = get_class($data);
        $file = $data -> getFile();
        $line = $data -> getLine();
        $msg = $data -> getMessage();
        $tmp = "<div class=\"__error\"><p>Uncaught <b>$name</b>: $msg</p>";
        $tmp .= "<div style=\"padding-left: 2em;\"><b>at</b> <b>?</b> <span style=\"color: gray;\">($file:$line) </span>";
        foreach (array_reverse($data->getTrace()) as $trace)
            if (__FILE__ !== @$trace["file"] && !str_starts_with($trace['function'], 'MomentCore\\'))
                $tmp .= "<div style=\"padding-left: 2em;\"><b>at</b> {$trace["class"]}<b>{$trace["type"]}{$trace["function"]}</b>:{$trace["line"]} <span style=\"color: gray;\">({$trace["file"]})</span></div>";
        return $tmp . "</div>";
    }

    /**
     * 读取一个文件，目前只支持文件
     * @param string $fname 文件名称
     */
    function file(string $fname){
        $file = open($fname,'r');
        $temp = '';
        while (!$file -> status() -> eof)
            $temp .= yield $file->read();
        return $temp;
    }

    /**
     * 打开一个持久化轻量级数据库
     * @param string  $key 索引键
     * @return ReactiveTable
     */
    function dbopen(string $key){
        if(!__CONFIG__['db']['enable'])
            throw new \TypeError('DB was disabled.Change db.enable to enable it.');
        if(!array_key_exists($key,ReactiveTable::$table))
            ReactiveTable::$table[$key] = [];
        return new ReactiveTable(ReactiveTable::$table[$key]);
    }

    /**
     * 连接到远程服务器
     * @param string $addr 连接的客户端的地址
     * @param float $timeout 超时时间
     * @param resource $ctx `stream_context_create`返回的内容
     * @param string $type 为这个连接起一个标识性前缀
     * @return TcpPipe
     */
    function client(string $addr,int $port,float $timeout = 10.0,$ctx = null,string $type = 'tcp',int $mode = TcpPipe::T_RW){
        $addr = @gethostbyname($addr);
        $socket = @stream_socket_client("tcp://$addr:$port",$ec,$em,$timeout,STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT,$ctx ?? stream_context_create([
            'tcp' => [
                'so_reuseport' => true,
                'tcp_nodelay' => true
            ]
        ]));
        
        if(!$socket)
            throw new \Error("Failed to bind($ec): $em");
        $pipe = new TcpPipe($socket,$mode);
        EventLoop::add($socket,$pipe,$type);
        return $pipe;
    }

    /**
     * 以HTTP/1.1(不可复用)请求URL
     * 在虚拟线程中建议使用`\MomentAdaper\fetch`
     * # 如果是http(s)
     * 与JS有相似之处，但是第二份个参数只提供了少量的功能
     * ```
     *  request('http://baidu.com/',[
     *      'method' => 'GET',           // HTTP方法
     *      'header' => [],              // 请求头
     *      'redirect' => true,          // 允许重定向
     *      'connect_timeout' => 10.0,   // 连接超时
     *      'timeout' => 20              // 总体请求超时时间
     * ])
     *  ```
     * # 如果是ws(s)
     * 返回一个`WebSocket`对象,可以自由收发WebSocket数据
     * ```
     * $ws = request('wss://echo.websocket.org/',[
     *      'method' => 'GET',           // HTTP方法
     *      'header' => [],              // 请求头
     *      'redirect' => true,          // 允许重定向
     *      'connect_timeout' => 10.0,   // 连接超时
     *      'query' => [],               // 查询数组
     *      'body' => ''                 // HTTP请求的body
     * ]);
     * $ws -> onMessage(fn($data) => log($data));
     * interval(fn() => $ws -> send(rand()),1000);
     *  ```
     * # 我们也支持`unix:/`
     * 直接使用:
     * ```
     * $ws = request('ws://unix:/tmp/rpc.sock');
     * ```
     * @param string $url 地址
     * @param array $option 请求参数
     * @return Promise 一个Response对象
     * @see Response 响应内容
     * @see WebSocket 如果协议是`ws://`的WebSocket元素
     */
    function request(string $url,array $option = []){
        $redirect = [];
        $fetch = static function(string $url, array $option, array &$redirect) use (&$fetch){
            $prom = new Promise($rs, $rj);
            $header = [];
            // 重定向管理
            $redirect[] = $url;
            // 解析URL
            $data = parse_url($url);
            $is_https = in_array(strtolower($data['scheme']),['wss','https']);
            $is_ws = str_starts_with(strtolower($data['scheme']),'ws');
            $option = array_merge([
                'method' => 'GET',
                'header' => [
                    'User-Agent' => 'MomentPHP',
                    'Host' => $data['host']
                ],
                'redirect' => true,
                'connect_timeout' => 10.0,
                'timeout' => 30,
                'query' => [],
                'body' => ''
            ],$option);
            if($data['host'] == 'unix'){
                if(PHP_OS == 'Linux'){
                    $host = 'unix://' . $data['path'];
                    $uri = ($data['fragment'] ?? '/');
                    if(count($option['query']) > 0)
                        $uri .= '&' . http_build_query($option['query']);
                    $option['header']['Host'] = null;
                }else{
                    return $rj(new \Error('UnixSocket can only be used in UnixLikedOS'));
                }
            }else{
                $addr = @gethostbyname($data['host']);
                if(!$addr) return $rj(new \Error("getAddr [{$data['host']}] failed"));
                $host = "tcp://$addr:" . ($data['port'] ?? ($is_https ? 443 : 80));
                $param = array_merge($option['query'], $option['query']);
                $uri = ($data['path'] ?? '/') . 
                    (@$data['query'] ? '?' . $data['query'] : '') .
                    (count($param) > 0 ? '?' . http_build_query($param) : '');
            }

            // 创建客户端socket
            $client = stream_socket_client($host, $ec, $em, $option['connect_timeout'], STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT, stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]));

            if (false === $client)
                return $rj(new \Error("Connect to [$host] failed($ec): $em"));
            
            if($is_ws){
                $handle = new TcpPipe($client,TcpPipe::T_RW);
            }else{
                $handle = $is_https
                    ? new TlsResponse($client,$option['connect_timeout'],true,true)
                    : new Response($client,TcpPipe::T_RW);
                $handle -> timeout = $option['timeout'];
                $handle -> on('timeout',fn () =>
                    $rj(new \Error('fetch failed: Read Timeout')));
            }
            EventLoop::add($client, $handle,$is_ws ? 'ws' : 'fetch');

            // 写请求头
            yield $handle -> write("{$option['method']} $uri HTTP/1.1\r\n");
            yield $handle -> write("Connection: keep-alive\r\n");
            foreach ($option['header'] as $key => $value)
                if($value != null)
                    yield $handle -> write("$key: $value\r\n");
            if($is_ws){
                $seckey = base64_encode(md5((string)rand(0, PHP_INT_MAX), true));
                $expect = base64_encode(sha1($seckey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                yield $handle -> write("Upgrade: websocket\r\n");
                yield $handle -> write("Connection: Upgrade\r\n");
                yield $handle -> write("Sec-WebSocket-Key: $seckey\r\n");
                yield $handle -> write("Sec-WebSocket-Version: 13\r\n");
            }
            yield $handle -> write("\r\n");

            // 读取第一行
            $line = yield $handle -> readline();
            if (!preg_match('/^HTTP\\/([0-9]\\.[0-9]{1,2})\\s+([0-9]{1,10})\\s+(.+)\\s*$/i', $line, $meta))
                throw new \Error('illegal data(E)');
            $status = (int)$meta[2];

            // 读取Header
            while ($_ = yield $handle -> readline())
                if(preg_match(
                    '/^\s*([a-z-_]+)\s*:\s*(.+)\s*$/i',$_,$match
                )) $header[strtolower($match[1])] = $match[2];

            // WebSocket检测
            if($is_ws){
                if($status == 101) {
                    if($header['sec-websocket-accept'] != $expect)
                        return $rj(new \Error('CheckSec Failed: not matching'));
                    else
                        return new WebSocket($handle,true);
                }else{
                    return $rj(new \Error('illegal response'));
                }
            }

            // 重定向检测
            if ($option['redirect'] && ($status == 301 || $status == 302 || $status == 307)) {
                if ($header['location']){
                    $handle -> __destruct();
                    $rs(yield $fetch($header['location'], $option, $redirect));
                }else
                    $rj(new \Error('FetchError: illgal redirect'));
            } else {
                $handle -> init($status, $meta[3], (float)$meta[1], $redirect, $header);
                return $handle;
            }
            return $prom;
        };
        return Promise::await($fetch($url,$option,$redirect)) -> then(
            fn() => trigger_error(COLOR_PINK . 'Fetch' . COLOR_UNSET . 
                ' Finished: [' . COLOR_GRAY .
                (strlen($url) > 50 ? substr($url,0,50) . '...' : $url) .
                COLOR_UNSET . ']',E_USER_NOTICE
            )
        );
    }

    /**
     * 向终端写入内容，而不是客户端
     * @param string $data 写入的数据
     * @param bool $wrap 是否自动换行
     */
    function log(string $data, bool $wrap = true):void{
        if(defined('__CONFIG__') && false === @__CONFIG__['behavior']['log']) return;
        fwrite(STDOUT, preg_replace_callback('/\\{\\s*(-|\\/)?\\s*([a-z_]+)?\\s*}/i', function (array $match) {
            if ($match[1] == '/')
                return FONT_UNSET_ALL;
            $type = strtoupper($match[2]);
            if ($match[1] == '-')
                $type = $type . '_LIGHT';
            try {
                return constant("\\MomentCore\\$type");
            } catch (\Throwable) {
                return "{$type}";
            }
        }, $data) . FONT_UNSET_ALL);
        if ($wrap) fwrite(STDOUT, PHP_EOL);
    }

    /**
     * 创建一个监听服务器
     * @param string $addr 绑定地址
     * @param object $content `stream_context_create`创建的context
     * @param callable $handle 当客户端连接时调用回调 
     * @return callable 用于停止server的函数
     */
    function bind(string $addr, callable $handle, ?object $context = null){
        if (!$context)
            $context = \stream_context_create([
                'tcp' => [
                    'backlog'       => 16,
                    'so_reuseport'  => true,
                    'tcp_nodelay'   => true
                ],
                'tls' => [
                    'verify_peer'           => @!__CONFIG__['server']['ssl']['ignore_error'],
                    'allow_self_signed'     => @!__CONFIG__['server']['ssl']['ignore_error'],
                    'disable_compression '  => true,
                    'local_cert'            => @__CONFIG__['server']['ssl']['cert'],
                    'local_pk'              => @__CONFIG__['server']['ssl']['key'],
                    'ssltransport'          => 'tlsv1.3'
                ]
            ]);

        $ec = 0;
        $emsg = '';
        try {
            $socket = \stream_socket_server($addr, $ec, $emsg, context: $context);
            if(!$socket) throw new \Error($emsg);
            stream_set_blocking($socket,false);
        } catch (\Throwable $e) {
            trigger_error("Failed to bind port: " . $e->getMessage(),E_USER_WARNING);
            return false;
        }

        trigger_error("Listen on $addr",E_USER_NOTICE);

        $id = count(EventLoop::$server);
        EventLoop::$handler[$id] = $handle;
        EventLoop::$server[$id] = $socket;
        return function () use ($id){
            unset(EventLoop::$handler[$id],EventLoop::$server[$id]);
            trigger_error('Server#' . $id . ' aborted',E_USER_NOTICE);
        };
    }

    /**
     * 类似于`var_dump`的方法，将变量打印到终端
     * 注意：是终端不是客户端，否则请使用`var_dump`
     * 
     * @param mixed $obj 打印的变量
     * @param string $obj_indent 缩进多少，一般不用管这个参数
     */
    function dump(mixed $obj, string $obj_indent = ""):void{
        switch (strtolower(gettype($obj))) {
            case "boolean":
                $obj = $obj ? ' {color_green}true{/} ' : '{color_red}false{/}';
            break;

            case 'integer':
            case 'double':
            case 'float':
                $obj = "{color_green} $obj {/}";
            break;

            case 'object':
                $start = "Object {\n";
                $end = "}";
                $obj = (array)$obj;
            case 'array':
                log(@$start ?? "Array [");
                foreach ($obj as $key => $value) {
                    log("$obj_indent\t{color_cran}$key{/} => ", false);
                    dump($value, $obj_indent . "\t");
                }
                log($obj_indent . (@$end ?? "]"));
            return;

            case 'resource':
                $obj = '{color_green} Resource {/}';
            break;

            case 'resource (closed)':
                $obj = '{color_red} !Resource {/}';
            break;

            case 'string':
                $obj = " \"{color_pink}$obj{/}\" ";
            break;

            case 'null':
                $obj = ' {color_gray}NULL{/} ';
            break;
        }
        log($obj);
    }

    

    /**
     * 将一个文件拷贝到`$to`
     * 使用stream流，因此不会阻塞
     * 
     * @param string $from 原文件
     * @param string $to 目标文件
     * @return Promise
     */
    function copy(string $from,string $to){
        $failed = [];
        $prom = new Promise($rs,$rj);
        $copy = static function (string $from,string $to,array &$failed) use (&$copy){
            if(!file_exists($from))
                throw new \Error('OriginFile not found');
            // 复制文件
            if(is_file($from)) try{
                $dir = dirname($to);
                if(!file_exists($dir)){
                    if(!@mkdir($dir)) return false;
                }elseif(!is_dir($dir))
                    return false;
                $f = open($from,'rb');
                $d = open($to,'wb');
                yield $f -> pipeTo($d);
                return true;
            }catch(\Throwable){
                return false;
            }
            // 复制文件夹
            $d = opendir($from);
            while(false !== ($d = readdir($d)))
                if(!yield from $copy("$from/$d","$to/$d",$failed))
                    $failed[] = "$from/$d";
            return true;
        };
        Promise::await($copy($from,$to,$failed)) -> then(function($result) use (&$failed,$from,$rs){
            // 文件拷贝失败
            if(!$result) $failed[] = $from;
            // 返回
            $rs($failed);
        }) -> catch($rj);
        return $prom;
    }

    /**
     * 移动文件，当跨盘操作时自动使用`copy`+`unlink`
     * @param string $from 来源
     * @param string 目标
     * @return Promise 返回失败列表的Promise
     */
    function move(string $from,string $to){
        $failed = [];
        $prom = new Promise($rs,$rj);
        $move = static function(string $from,$to) use (&$move){
            // 是否存在
            $dir = basename($to);
            if(!file_exists($dir)){
                if(!@mkdir($dir)) return false;
            }elseif(!is_dir($dir))
                return false;

            // 比较是否在同一个磁盘
            $st = disk_total_space($from);
            $sf = disk_free_space($from);
            $tt = disk_total_space($dir);
            $tf = disk_free_space($dir);
            if($st == $tt && $sf == $tf){
                // 直接移动，很快几乎不阻塞
                move($from,$to);
            }else{
                // 流式拷贝，使用异步IO
                yield copy($from,$to);
                unlink($from);
            }
            return true;
        };
        // 主程序
        Promise::await(function() use ($from,$to,$move,&$failed,$rs){
            if(is_dir($from)){
                $d = opendir($from);
                while(false !== ($d = readdir($d)))
                    if(!yield from $move("$from/$d","$to/$d",$failed))
                        $failed[] = "$from/$d";
                $rs($failed);
            }elseif(is_file($from)){
                $move($from,$to);
            }else{
                throw new \Error('Unknown file/dir '.$from);
            }
        }) -> catch($rj);
        return $prom;
    }

    /**
     * 打开一个文件。
     * 第二个参数与PHP支持的一样
     *  ```
     * open('/b.txt') -> pipeTo($http_client);
     * ```
     * @param string $file 打开的文件
     * @param string $fmode 文件打开模式，可选[rwax]\+?
     * @param bool $uip 是否搜索include_path
     * @param ?resource $context `stream_content_create`返回的选项
     * @return FilePipe 文件管道
     */
    function open(string $file,string $fmode = 'r',bool $uip = false,$context = null){
        $f = @\fopen($file,$fmode,$uip,$context);
        if(!$f) throw new \Error("Failed to open file [$file]",1);
        
        $flag = 0b0;
        if(@$fmode[1] == '+') 
            $flag = FilePipe::T_RW;
        elseif($fmode[0] == 'r') 
            $flag |= FilePipe::T_READABLE;
        elseif($fmode[0] == 'w' || $fmode[0] == 'a' || $fmode[0] == 'x') 
            $flag |= FilePipe::T_WRITABLE;
        else
            throw new \TypeError('Unknown fopenMode ' . $fmode);

        $handle = new FilePipe($f,$flag);
        EventLoop::add($f,$handle,'fs');
        return $handle;
    }

    /**
     * 解析传递的参数
     * @param array $argv 参数数组
     * @param array $arglist 短字符对应的长参数
     * @return array 解析后的参数
     */
    function parseArgs(array $argv,array $arglist = []):array{
        $tmp = ['_' => []];
        foreach($argv as $data){
            if(\preg_match('/^--([a-z]+)(?:=([\w\W]+))?$/i',$data,$match)){     // --[长参数]
                $tmp[ $match[1] ] = @$match[2] ?? true;
            }elseif(\preg_match('/-[a-z]+/i',$data)){        // -[n个短参数]
                for($i = 1 ; $i < \strlen($data) ; $i++){
                    $c = $data[$i];                         // 逐个字母解析
                    if(!\array_key_exists($c,$arglist))
                        log ("{color_yellow}W{/} Unknown arg $c in [$data]");
                    else $tmp[$arglist[$c]] = true;         // 当作长命令启动
                }
            }else{
                $tmp['_'][] = $data;
            }
        }
        return $tmp;
    }

    /**
     * 启动一个虚拟线程(也可以称作协程)
     * vThread与异步函数唯一的不同就是看起来似乎是阻塞的写法完全实现虚拟线程内异步
     * 这样可以做到最小化实现异步调用
     * ```
     * go(function(){
     *      // 这些函数全部都是线程内同步哦
     *      $f = \MomentAdapter\file_get_contents('1.txt');
     *      \MomentAdapter\header("Content-Bytes: ".count($f));
     *      \MomentAdapter\readfile($f);
     * })
     * ```
     * @param callable $main 主线程
     * @param array $args 传递给线程的参数
     * @param callable $onext 当线程重新开始执行的回调
     * @return Promise
     */
    function go(callable $main,array $args = [],?callable $onext = null){
        $fiber = new \Fiber($main);
        $prom = new Promise($rs,$rj);
        $callback = function($data) use ($fiber,$rs,$rj,&$callback,$onext){
            // 已经返回
            if($fiber -> isTerminated())
                $rs($data);

            // 回调
            if($onext) $onext();

            // 继续vThread执行
            try{
                $data = $fiber -> resume($data);
            }catch(\Throwable $e){
                return $rj($e);
            }

            // 等待Promise
            if($data instanceof Promise){
                $data -> then($callback) -> catch(fn($e) => 
                    $fiber -> throw($e)
                );
            // 直接传递
            }else{
                if(!$fiber -> isTerminated())
                    trigger_error('vThread: suspend failed:non-Promise passed',E_USER_NOTICE);
                $callback($data);
            }
        };

        // 启动虚拟线程
        try{
            $data = $fiber -> start(...$args);
            if($data instanceof Promise){
                $data -> then($callback) -> catch(fn($e) => 
                    $callback($fiber -> throw($e))
                );
            }else{
                $callback($data);
            }
        }catch(\Throwable $e){
            return $rj($e);
        }

        return $prom;
    }

    /**
     * 打开一个进程Pipe
     * 设置第二个参数可实现可(异步)读可写
     * 注意返回Error的情况，一般是程序执行错误
     * ```
     * popen(['curl','imzlh.top'],[
     *     'read'        => true,
     *     'write'       => false,
     *     'working_dir' => '/',
     *     'env'         => []
     * ]) -> pipeTo($webSocket);
     * ```
     * @param array $args 传递的参数列表
     * @param array $option 可选参数(所有参数都是可选的)
     * @return ProcPipe 进程输入输出管道
     * ```
     */
    function popen(array $args,array $option){
        $desc = [];
        if($option['write']) $desc[0] = ['pipe','r'];
        if($option['read']) 
            if(PHP_OS == 'Linux') $desc[1] = $desc[2] = ['pipe','w'];
            else trigger_error('popen(): Non-Unix OS donot support async ProcPipe.Ignore {read} flag',E_USER_WARNING);
        $proc = @proc_open($args,$desc,$pipe,@$option['working_dir'],array_merge([
            "PARENT_PID" => getmypid()
        ],@$option['env'] ?? []));
        if(!$proc)
            throw new \Error("Open process failed:" . error_get_last()['message']);
        $pipe = new ProcPipe($proc,$pipe);
        return $pipe;
    }

    /**
     * @internal
     */
    define('default_http_handle',function ($client, ?string $name){

        // Http处理器
        $handle = new HttpHandle($client, $name);
        EventLoop::add($client, $handle,'client');

        while (true) {
            if($handle -> state() == HttpHandle::WEBSOCKET)
                return;
            try{
                yield from $handle();
            }catch(\Throwable){
                return;
            }

            if (\strpos("..", $handle->client->path) > 0)
                return yield $handle->finish(301, "", [
                    "Location" => \preg_replace('/\\/([\\w\\W^/]\\/)?\\.\\.\\//', '', $handle->client->path)
                ]);

            /**
             * @var string 文件路径
             */
            $path = $handle->client->path[0] == ':'
                ? substr($handle->client->path, 1)
                : __CONFIG__['rounger']['root'] . $handle->client->path;
            /**
             * @var string 文件名
             */
            $ext = @\strtolower(@\end(\explode(".", basename($path))));

            /**
             * @var bool 文件是否存在
             */
            $exist = \file_exists($path);

            // OPTIONS请求
            if ($handle -> client -> method == 'OPTIONS') {
                yield $handle->finish(204, '', HttpHandle::$option);
            // 是可执行PHP文件
            } elseif ($exist && \in_array($ext, __CONFIG__['rounger']['execable'])) {
                yield from $handle->parseBody();
                yield run($path, $handle);
            // HTTP FileServer
            } elseif ($handle -> client -> method == 'GET' || $handle -> client -> method == 'HEAD') {
                // 文件夹服务器
                if (!$exist)
                    yield $handle->finish(404, 'Not found');
                // 使用函数便于Escape
                elseif (\is_dir($path)) yield (function () use ($handle, $path) {
                    if (\substr($path, -1) != '/') {
                        $path .= '/';
                        return $handle->header('Content-Location', $path);
                    }
                    foreach (__CONFIG__['rounger']["index"] as $index)
                        if (\file_exists($path . $index))
                            if (\in_array(
                                \strtolower(@end(explode(".", $index))),
                                __CONFIG__['rounger']['execable']
                            )){
                                yield from $handle->parseBody();
                                return yield run($path, $handle);
                            }else{
                                return yield from $handle->file($path . $index);
                            }
                        // AutoIndex
                        if (__CONFIG__['rounger']['autoindex'])
                            yield from $handle -> listDir($path, $handle -> client -> path != '/');
                        else
                            yield $handle -> finish(403, 'Is a dir');
                })();
                // 文件服务
                else yield from $handle -> file($path);
                // WebDAV服务
            } elseif(__CONFIG__['rounger']['webdav']['enable']) {
                if(
                    ! __CONFIG__['rounger']['webdav']['auth'] ||
                    yield $handle -> auth(
                        __CONFIG__['rounger']['webdav']['user'],
                        __CONFIG__['rounger']['webdav']['pass'],
                        'webdav'
                    )
                ) yield from $handle -> webdav(__CONFIG__['rounger']['root']);
            } else {
                yield $handle -> finish(405);
            }
        }
    });

    /**
     * 主程序
     * @version 1.0
     * @license MIT
     * @copyright izGroup
     * @link http://moment.imzlh.top/
     */
    function __main(){
        // 初始化OB缓冲区
        @\ob_end_clean();
        \ob_start(function(string $data){
            /**
             * @var HttpHandle
             */
            $handle = @$GLOBALS['__HANDLE__'];
            if('' == $data) return '';
            if(!$handle)
                trigger_error('No output target found(len:' .strlen($data). ')',E_USER_WARNING);
            if($handle -> state() == HttpHandle::PENDING)
                $handle -> useChunk();
            $handle -> write($data);
            return '';
        },EventLoop::$CHUNK_SIZE,PHP_OUTPUT_HANDLER_CLEANABLE|PHP_OUTPUT_HANDLER_FLUSHABLE);

        // 初始化参数
        $self = array_shift($GLOBALS['argv']);
        $arg = parseArgs($GLOBALS['argv'],[
            'c' => 'config',
            'h' => 'help',
            'a' => 'alias'
        ]);
        if(@$arg['help']){
            log("
{font_bold}MomentPHP{/}
{color_gray}Copyright 2023 izGroup.MIT License{/}

{color_blue}Usage{/}
    {$self} --long_args=value -short_args

{color_blue}Args available{/}
    {color_green}Long{/}        {color_green}Short{/}   {color_green}Role{/}
    help        h       Get the help
    config      c       Set The ConfigFile
    root                Set the WWWRoot
    log                 Set whether display PHPLogs(true/false)
    alias       a       Set the AliasMap ([uripath]:[:REALPATH|alias_to_path],...)
",false);
            exit(0);
        }

        // 检测配置
        if((float)PHP_VERSION < 8.1)
            die('PHP version not support.Upgrade to >= 8.1 will solve the problem');

        // 初始化配置
        $cfile = "config.json";
        $config = [
            "server" => [
                "bind" => [
                    "tcp://0.0.0.0:80"
                ],
                "ftp" => [
                    "tcp://0.0.0.0:21"
                ],
                "ssl" => [
                    "enable" => false,
                    "cert" => "",
                    "key" => "",
                    "ignore_error" => true
                ]
            ],
            "rounger" => [
                "execable" => [
                    "php"
                ],
                "index" => [
                    "index.php",
                    "index.html"
                ],
                "autoindex" => true,
                "webdav" => [
                    "enable" => true,
                    "auth" => false,
                    "user" => '',
                    "pass" => ''
                ],
                "root" => __DIR__,
                "script_timeout" => 30
            ],
            'ftp' => [
                "enable" => true,
                "data_port" => 1024,
                "user" => null,
                "pass" => null,
                "root" => __DIR__
            ],
            "behavior" => [
                "warning" => true,
                "info" => true,
                "log" => true,
            ],
            "db" => [
                "enable" => true,
                "autoSave" => true,
                "path" => __DIR__ . '/data.jdb',
                "interval" => 60
            ]
        ];
        if(is_file($cfile)){
            $data = @json_decode(file_get_contents(@$arg['config'] ?? $cfile),true);
            if(!is_array($data))
                throw new \Error("Failed to load config.");
            $config = array_merge($config,$data);
        }
        if(@$arg['root'])
            if(is_dir($arg['root'])) $config['rounger']['root'] = $arg['root'];
            else trigger_error("set_root(): [{$arg['root']}] not exists.",E_USER_ERROR);
        if(@$arg['log'] == 'true')
            $config['behavior']['log'] = true;
        elseif(@$arg['warning'] == 'false')
            $config['behavior']['log'] = false;
        define('__CONFIG__',$config);

        $paniced = false;

        // 设置Error handle
        set_error_handler(function(int $errno,string $errstr,string $errfile,int $errline) use (&$paniced,$config){
            if($paniced) return;    // 报错时不要输出任何日志
            if( $errfile == __FILE__ ) $errfile = '[CORE]';
            if($errno == E_ERROR || $errno == E_USER_ERROR){
                return fwrite(STDOUT,
                    COLOR_RED . 'E ' . COLOR_UNSET . 
                    $errstr . ' ( ' . COLOR_RED . 
                        'at ' . COLOR_GRAY . $errfile .
                        ':' . COLOR_BLUE_LIGHT . $errline . COLOR_UNSET . PHP_EOL);
            }elseif($errno == E_USER_WARNING){
                if(@$config['behavior']['warning']) 
                    return fwrite(STDOUT,
                        COLOR_YELLOW . 'W ' . COLOR_UNSET . 
                        $errstr . ' ( ' . COLOR_RED . 
                            'at ' . COLOR_GRAY . $errfile .
                            ':' . COLOR_BLUE_LIGHT . $errline . COLOR_UNSET . PHP_EOL);
            }elseif($errno == E_USER_DEPRECATED){
                if(@$config['behavior']['warning']) 
                    return fwrite(STDOUT, COLOR_YELLOW . 'W ' . COLOR_UNSET . $errstr);
            }elseif($errno == E_WARNING || $errno == E_NOTICE || $errno == E_USER_NOTICE){
                if(@$config['behavior']['info']) 
                    return fwrite(STDOUT,COLOR_BLUE . 'I ' . COLOR_UNSET . $errstr . PHP_EOL);
            }
            return true;
        });

        set_exception_handler(function(\Throwable $e) use (&$paniced){
            if($paniced) return;    // 只报错一次
            $paniced = true;
            if($e -> getFile() == __FILE__){
                $errstr = $e -> getMessage();
                $errline = $e -> getLine();
                $errfile = $e -> getFile();
                $errtype = get_class($e);
                $error = $e -> getTrace()[0];
                log("---------- {color_red}Moment Error{color_gray}<CORE Panic>{/} ----------");
                log("[{color_red}$errtype{/}] {color_cran}$errstr {color_gray}<#$errline>{/}");
                log("{color_red}caused by{/} {color_blue}{$error['function']}{/} at line {color_green}{$error['line']}{/}");
                log('MomentPHP V' .VERSION. ' {color_gray}(PHP ' .PHP_VERSION. ' for ' .PHP_OS. ' , Zend ' .zend_version(). '){/}');
                log('');
                log("This Panic is caused by {color_yellow}MomentCore{/} and possibly a {font_bold}BUG{/}.");
                log("Please {font_bold}report the BUG{/} to us!Thank you for your using!");
                log('---------- {color_gray}END of panic massage{/} ----------');
                return true;
            }
        });

        // 设置exit handle
        register_shutdown_function(function(){
            trigger_error('Received exit SIGNAL.Exiting...',E_USER_NOTICE);
        });

        // 设置持久化DB
        if($config['db']['enable']){
            // 打开数据库文件
            try{
                $db = open($config['db']['path'],'w+');
            }catch(\Throwable $e){
                trigger_error('Failed to open DB: '.$e -> getMessage(),E_USER_ERROR);
                exit(1);
            }
            // 解析bJSON数据库
            if($db -> stat()['size'] >= 2)
                JsonDB::decode($db) -> then(function($db){
                    ReactiveTable::$table = $db;
                    trigger_error('Loaded Local bJSON DB successfully',E_USER_NOTICE);
                }) -> catch(function(){
                    trigger_error('DataBase Format Error!Data Lose.',E_USER_WARNING);
                    ReactiveTable::$changed = true;
                });
            // 重写DB
            else 
                JsonDB::encode([],$db);
            // 自动保存bJSON文件
            if($config['db']['autoSave']){
                interval(function() use (&$db){
                    if(!ReactiveTable::$changed) return;
                    $db -> seek(0);
                    JsonDB::encode(ReactiveTable::$table,$db);
                    ReactiveTable::$changed = false;
                    trigger_error('DataBase saved.',E_USER_NOTICE);
                },$config['db']['interval'] * 1000);
            }
            // 只有在退出时保存
            else register_shutdown_function(function() use ($db){
                if(!ReactiveTable::$changed) return;
                JsonDB::encode(ReactiveTable::$table,$db);
                trigger_error('Sync&Closed DataBase.',E_USER_NOTICE);
            });
        }

        // 自动关闭ProcPipe
        register_shutdown_function(fn() => array_walk(ProcPipe::$allproc,function($each){
            if($each && $each -> alive()) $each -> __destruct(); 
        }));

        // 设置AliasMap
        if(@$arg['alias']){
            foreach(explode(',',$arg['alias']) as $alias){
                $words = explode(':',$alias,2);
                HttpHandle::$alias[urldecode($words[0])] = $words[1];
            }
        }

        // 设置FTP
        FtpHandle::$root = $config['ftp']['root'];
        FtpHandle::$user = $config['ftp']['user'];
        FtpHandle::$pass = $config['ftp']['pass'];

        // 活动服务器个数
        $in = 0;

        // 绑定FTP
        FtpHandle::initDataServer($config['ftp']['data_port']);
        if($config['ftp']['enable'])
            foreach ($config['server']["ftp"] as $addr)
                if(bind($addr,function($c,$addr){
                    $handle = new FtpHandle($c);
                    EventLoop::add($c, $handle,'ftpc');
                    log("{-color_pink}C{/} {-color_green}:FTP{/} {color_gray}$addr{/}");
                    Promise::await( $handle() );
                }))
                    $in ++;

        // 绑定地址
        
        foreach ($config['server']["bind"] as $addr)
            if(bind($addr, fn($c,$addr) => Promise::call(default_http_handle,$c,$addr))) 
                $in ++;
        if($in == 0){
            trigger_error('No binded addr is available.exiting...',E_USER_ERROR);
            exit (10);
        }

        cli_set_process_title('MonentPHP Worker Process');
        EventLoop::start();
    }

    if(!defined('__MAIN__')){
        define('__MAIN__',__FILE__);
        __main();
    }
}?><?php namespace MomentAdaper{

    /**
     * 将整个文件读入一个字符串
     */
    function file_get_contents($fname,$uip = false,$ctx = null,$offset = -1,$maxlen = -1):string{
        $fp = \MomentCore\open($fname,'r',$uip,$ctx);
        if($offset) $fp -> seek($offset);
        return \Fiber::suspend($fp -> read($maxlen));
    }

    function file_put_contents(string $fname,string $data,int $flags = 0,$ctx = null):bool{
        try{
            $fp = \MomentCore\open($fname,
                $flags & FILE_APPEND ? 'a' : 'w',
                ($flags & FILE_USE_INCLUDE_PATH) == FILE_USE_INCLUDE_PATH,
                $ctx
            );
            if($flags & LOCK_EX)
                $fp -> lock(LOCK_EX);
            \Fiber::suspend($fp -> write($data));
            return true;
        }catch(\Throwable){
            return false;
        }
    }

    function readfile($fname,$uip,$ctx):void{
        ob_flush();
        if(!$GLOBALS['__HANDLE__'])
            throw new \Error('Not in a legal Request block');
        $fp = \MomentCore\open($fname,'r',$uip,$ctx);
        $fp -> pipeTo($GLOBALS['__HANDLE__']);
    }

    function header(string $content){
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$GLOBALS['__HANDLE__'])
            throw new \Error('Not in a legal Request block');
        if(preg_match('/^\s*http\/[0-9.]+\s*([0-9]{1,3})\s*/i',$content,$match)){
            $handle -> http_status = (int)$match[2];
            return;
        }elseif(preg_match('/^\s*([a-z0-9_-]+)\s*:\s*(.+)\s*/i',$content,$match)){
            $head = strtolower($match[1]);
            if($head == 'location' && $match[2])
                $handle -> http_status = 302;
            $handle -> header($head,$match[2]);
        }else{
            throw new \TypeError('Unknown header');
        }
    }

    function header_remove(string $header){
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        $handle -> header($header,null);
    }

    function headers_list():array{
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        return $handle -> headers;
    }

    function headers(){
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        return $handle -> headers === false;
    }

    function http_response_code(int $code = -1):int|null{
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        if($code >= 100) $handle -> http_status = $code;
        else return $handle -> http_status;
    }

    function setcookie(
        string $name,
        string $value = "",
        int $expires = 0,
        string $path = "",
        string $domain = "",
        bool $secure = false,
        bool $httponly = false
    ){
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        $handle -> cookie($name,$value,$expires,$httponly,$domain == '',$path == '');
    }

    function read_body():string{
        /**
         * @var \MomentCore\HttpHandle
         */
        $handle = $GLOBALS['__HANDLE__'];
        if(!$handle)
            throw new \Error('Not in a legal Request block');
        $ctxlen = $handle -> client -> header['content-length'];
        if($ctxlen) return '';
        return \Fiber::suspend($handle -> read((int)$ctxlen));
    }

    function fread(\MomentCore\Pipe|\MomentCore\ProcPipe $pipe,int $len = -1):string{
        return \Fiber::suspend($pipe -> read($len));
    }

    function fetch(string $url,array $opt = []):\MomentCore\WebSocket|\MomentCore\Response{
        return \Fiber::suspend(\MomentCore\request($url,$opt));
    }

    function sleep(int $sec){
        \Fiber::suspend(\MomentCore\delay($sec * 1000));
    }

    function usleep(int $us){
        \Fiber::suspend(\MomentCore\delay((int)($us / 1000)));
    }

    function import(string $file){
        if(!file_exists($file) || !is_readable($file))
            throw new \Error('Failed to read file '.$file);
        include 'fs://' . realpath($file);
    }

    /**
     * @param resource $stream
     */
    function freadline($stream){
        $class = stream_get_meta_data($stream)['wrapper_data'];
        if(!($class instanceof \MomentCore\BaselinePipe))
            throw new \Error('Not a MomentAsyncIOPipe');
        return \Fiber::suspend($class -> readline());
    }
}?>