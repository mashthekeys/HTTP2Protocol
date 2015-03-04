<?php
namespace HTTP2Protocol\HTTP2;
class StaticTable
{
//package Protocol::HTTP2::StaticTable;
//use strict;
//use warnings;
//require Exporter;
//our @ISA = qw(Exporter);
//our ( @stable, %rstable );
//our @EXPORT = qw(@stable %rstable);

    static $stable = [
        [":authority", ""],
        [":method", "GET"],
        [":method", "POST"],
        [":path", "/"],
        [":path", "/index.html"],
        [":scheme", "http"],
        [":scheme", "https"],
        [":status", "200"],
        [":status", "204"],
        [":status", "206"],
        [":status", "304"],
        [":status", "400"],
        [":status", "404"],
        [":status", "500"],
        ["accept-charset", ""],
        ["accept-encoding", "gzip, deflate"],
        ["accept-language", ""],
        ["accept-ranges", ""],
        ["accept", ""],
        ["access-control-allow-origin", ""],
        ["age", ""],
        ["allow", ""],
        ["authorization", ""],
        ["cache-control", ""],
        ["content-disposition", ""],
        ["content-encoding", ""],
        ["content-language", ""],
        ["content-length", ""],
        ["content-location", ""],
        ["content-range", ""],
        ["content-type", ""],
        ["cookie", ""],
        ["date", ""],
        ["etag", ""],
        ["expect", ""],
        ["expires", ""],
        ["from", ""],
        ["host", ""],
        ["if-match", ""],
        ["if-modified-since", ""],
        ["if-none-match", ""],
        ["if-range", ""],
        ["if-unmodified-since", ""],
        ["last-modified", ""],
        ["link", ""],
        ["location", ""],
        ["max-forwards", ""],
        ["proxy-authenticate", ""],
        ["proxy-authorization", ""],
        ["range", ""],
        ["referer", ""],
        ["refresh", ""],
        ["retry-after", ""],
        ["server", ""],
        ["set-cookie", ""],
        ["strict-transport-security", ""],
        ["transfer-encoding", ""],
        ["user-agent", ""],
        ["vary", ""],
        ["via", ""],
        ["www-authenticate", ""],
    ];

    static $rstable = [];

    static function __init() {
        foreach (self::$stable as $k => $v) {
            $key = implode(' ', $v);
            self::$rstable[$key] = $k + 1;

            $key2 = "$v[0] ";
            if ($v[1] != '' && !isset(self::$rstable[$key2])) self::$rstable[$key2] = $k + 1;
        }
    }
}

StaticTable::__init();

