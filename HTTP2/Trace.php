<?php
namespace HTTP2Protocol\HTTP2;
class Trace
{
//use strict;
//use warnings;
//use Time::HiRes qw(time);
//
//use Exporter qw(import);
//our @EXPORT_OK = qw(tracer bin2hex);

    static $levels = [
        'debug'     => 0,
        'info'      => 1,
        'notice'    => 2,
        'warning'   => 3,
        'error'     => 4,
        'critical'  => 5,
        'alert'     => 6,
        'emergency' => 7,
    ];
    function debug($message) { $this->_trace(__FUNCTION__,$message); }
    function info($message) { $this->_trace(__FUNCTION__,$message); }
    function notice($message) { $this->_trace(__FUNCTION__,$message); }
    function warning($message) { $this->_trace(__FUNCTION__,$message); }
    function error($message) { $this->_trace(__FUNCTION__,$message); }
    function critical($message) { $this->_trace(__FUNCTION__,$message); }
    function alert($message) { $this->_trace(__FUNCTION__,$message); }
    function emergency($message) { $this->_trace(__FUNCTION__,$message); }

//$tracer_sngl = Protocol::HTTP2::Trace->_new(
//    min_level =>
//      ( exists $ENV{HTTP2_DEBUG} && exists $levels{ $ENV{HTTP2_DEBUG} } )
//    ? $levels{ $ENV{HTTP2_DEBUG} }
//    : $levels{error}
//);
    protected $start_time = 0;

    protected $min_level = 0;

//function tracer {
//    $tracer_sngl;
//}

    function __construct($opts) {
        foreach ($opts as $k => $v) {
            $this->$k = $v;
        }
    }

    protected function _log($levelLabel, $message) {
        $levelLabel = strtoupper($levelLabel);
        $message = preg_replace('/\n$/D','',$message);
        $now = microtime(true);
        if ( $now - $this->start_time < 60 ) {
            $message = preg_replace('/\n/',"\n           ",$message);
            printf("[%05.3f] %s %s\n", $now - $this->start_time, $levelLabel, $message);
        }
        else {
            $tLabel = date('Y-m-d H:i:s');
            $message = preg_replace('/\n/',"\n                      ",$message);
            printf("[%s] %s %s\n", $tLabel, $levelLabel, $message);
            $this->start_time = $now;
        }
    }

    protected function _trace($label, $message) {
        $level = self::$levels[$label];
        if ($level >= $this->min_level ) {
            $this->_log( $label, $message );
        }

    }
}

//function bin2hex {
//    $bin = shift;
//    $c   = 0;
//    $s;
//
//    join "", map {
//        $c++;
//        $s = !( $c % 16 ) ? "\n" : ( $c % 2 ) ? "" : " ";
//        $_ . $s
//    } unpack( "(H2)*", $bin );
//}