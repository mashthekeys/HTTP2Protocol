<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 27/02/2015
 * Time: 23:04
 */

namespace HTTP2Protocol\HTTP2;


class tracer {
    /** @var Trace */
    private static $tracer_sngl;

    static function __init() {
        $min_level = isset($_ENV['HTTP2_DEBUG']) && isset(Trace::$levels[$_ENV['HTTP2_DEBUG']])
            ? Trace::$levels[$_ENV['HTTP2_DEBUG']]
            : Trace::$levels['error'];

        self::$tracer_sngl = new Trace([
            'min_level' =>
                $min_level
        ]);

    }
    static function debug($message) { self::$tracer_sngl->debug($message); }
    static function info($message) { self::$tracer_sngl->info($message); }
    static function notice($message) { self::$tracer_sngl->notice($message); }
    static function warning($message) { self::$tracer_sngl->warning($message); }
    static function error($message) { self::$tracer_sngl->error($message); }
    static function critical($message) { self::$tracer_sngl->critical($message); }
    static function alert($message) { self::$tracer_sngl->alert($message); }
    static function emergency($message) { self::$tracer_sngl->emergency($message); }
}

tracer::__init();


