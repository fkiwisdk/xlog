<?php

namespace Xlog;

// require_once __DIR__ . '/../vendor/autoload.php';

use \Analog;

class XLogKit
{
    private static $ins       = array();
    private static $traceid   = "";
    private static $out_level = Analog\Analog::DEBUG;
    private static $app       = null;
    private static $sysErrDir = "_sys_error";
    private static $appErrDir = "_app_error";

    public static function logger($tag)
    {
        if (!empty(self::$ins[$tag])) {
            return self::$ins[$tag];//为每个tag创建一个logger实例
        }
        if (empty(self::get_traceid())) {
            self::init_traceid();
        }

        self::$ins[$tag] = new XLogKit($tag);

        return self::$ins[$tag];
    }
    public static function set_traceid($traceid)
    {
        self::$traceid = $traceid;
    }
    public static function get_traceid()
    {
        return self::$traceid;
    }
    public static function init_traceid()
    {
        if (self::$traceid) {
            return;
        }
        if (!empty($_SERVER["HTTP_TRACEID"])) {
            self::$traceid = $_SERVER["HTTP_TRACEID"];
            return;
        }
        self::$traceid = uniqid();//为每个请求创建一个唯一的traceid（限于项目内使用，后期可引入服务之间调用的全局traceid）
    }
    public static function set_app($app)
    {
        self::$app = $app;
    }
    public static function get_app()
    {
        return self::$app;
    }
    public static function out_level($level)
    {
        self::$out_level = $level; //设置日志输出级别，默认为debug
    }

    private function __construct($tag)
    {
        if (empty(self::get_app())) {
            throw new \Exception("must set app by method set_app()");
        }

        $this->tag    = $tag;
        $this->logger = new Analog\Logger;
    }

    public function __call($method, $params)
    {
        $app     = self::get_app();
        $tag     = $this->tag;
        $traceid = self::get_traceid();

        $logFunction = array(
            "emergency","alert","critical","error","warning","notice","info","debug"
        );

        if ($method == "warn") {
            $method = "warning";
        }

        if ( $this->logger->convert_log_level($method) > self::$out_level) {
            return;
        }

        if (in_array($method, $logFunction)) {
            $params[0] = "[{$method}] [{$traceid}] " . $params[0];  //追加traceid
        }

        $this->logger->handler(Analog\Handler\Syslog::init ("$app/_all", LOG_LOCAL6));//记录_all日志，如果确实觉得记录_all日志影响性能，可以注释掉（建议保留）
        call_user_func_array(array($this->logger, $method), $params);

        $this->logger->handler(Analog\Handler\Syslog::init ("$app/$tag", LOG_LOCAL6));

        call_user_func_array(array($this->logger, $method), $params);
    }

    /**
        * @brief 记录框架捕获的error信息
        *
        * @param $msg 自定义错误信息
        * @param $e   异常
        *
        * @return
     */
    public static function sysError($msg = "", \Exception $e = null)
    {
        $exceptionMsg = "系统错误";
        if (is_object($e)) {
            $exceptionMsg = $exceptionMsg . $e->getMessage();
        }

        $errorMsg = "*{$exceptionMsg}* " . $msg;

        self::logger(self::$sysErrDir)->error($errorMsg);
    }

    /**
        * @brief 记录业务error日志
        *
        * @param $biz  业务名|模块名
        * @param $msg  详细错误信息
        *
        * @return  null
     */
    public static function appError($biz, $msg)
    {
        $bizErrorMsg = "{$biz}错误";

        $errorMsg = "*{$bizErrorMsg}* " . $msg;  //追加traceid

        self::logger(self::$appErrDir)->error($errorMsg);
    }
}

