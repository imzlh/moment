<?php
    namespace MonentDaemon{
    define('__MAIN__',__FILE__);
    include_once "./moment.php";

    use MomentCore\HttpHandle;

    use function MomentCore\__main;
    use function MomentCore\interval;
    use function MomentCore\log;
    use function MomentCore\popen;

    class UnitStruct extends \stdClass{
        readonly array $args;
        readonly bool $watch;
        readonly string $workdir;
        readonly array $env;
        readonly bool $autoStart;
        /**
         * @var string 名称
         */
        public $name;
        /**
         * @var string 源文件
         */
        public $source;
        /**
         * @var \MomentCore\ProcPipe 源文件
         */
        public $pipe;
        /**
         * @var bool 是否有错误
         */
        public $error;

        /**
         * @var array 所有Unit
         */
        static $units = [];

        static function feedAll(){
            foreach (self::$units as $unit) {
                self::start($unit);
            }
        }

        /**
         * 启动服务
         * @param UnitStruct 服务信息
         * @param bool 错误是否输出到终端
         */
        static function start(object $unit,bool $toLog = true){
            if(@$unit -> pipe && $unit -> pipe -> status() -> alive)
                return false;
            try{
                $unit -> pipe = popen($unit -> args,[
                    'read' => true,
                    'write' => false,
                    'working_dir' => @$unit -> workdir,
                    'env' => @$unit -> env
                ]);
                $unit -> pipe -> on('close',fn() => self::start($unit,$toLog));
                log("{color_green}I{/} (re)start Unit {$unit -> name} succeed.");
                return true;
            }catch(\Throwable $e){
                if($toLog){
                    $msg = $e -> getMessage();
                    $unit -> error = true;
                    log("{color_yellow}W{/} Unit {$unit -> name} cannot restart(with ERROR $msg)");
                }else throw $e;
            }
        }
    }

    // 加载Unit
    foreach (glob('*.task.json') as $file) {
        /**
         * @var UnitStruct Unit配置
         */
        $json = json_decode(file_get_contents($file));
        $json -> source = __DIR__ . '/' . $file;
        if(!is_array(@$json -> args))
            log("{color_red}E{/} illegal unitFile [$file]");
        else{
            UnitStruct::$units[] = $json;
            if(@$json -> autoStart)
                UnitStruct::start($json);
            else
                log("{color_green}I{/} New Service found: $file");
        }
    }

    // 加载其余PHP文件
    foreach (glob('*.task.php') as $file)
        include_once $file;

    // 载入管理文件
    HttpHandle::$alias['/@taskmgr/'] = ':' . __DIR__ . '/manager.php';

    // 启动服务器
    __main();
}
?>