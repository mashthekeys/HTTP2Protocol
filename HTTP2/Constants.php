<?php
namespace HTTP2Protocol\HTTP2;
class Constants
{
//package Protocol::HTTP2::Constants;
//use strict;
//use warnings;
//use constant {

    # Header Compression
    const MAX_INT_SIZE = 4;
//    const MAX_PAYLOAD_SIZE = ( 1 << 23 ) - 1;
    const MAX_PAYLOAD_SIZE = 0x7fffff;
    # Frame
    const FRAME_HEADER_SIZE = 9;
    # Flow control
//    const MAX_FCW_SIZE = ( 1 << 31 ) - 1;
    const MAX_FCW_SIZE = 0x7fffffff;
    # Settings defaults
    const DEFAULT_HEADER_TABLE_SIZE = 4096;
    const DEFAULT_ENABLE_PUSH = 1;
    const DEFAULT_MAX_CONCURRENT_STREAMS = 100;
    const DEFAULT_INITIAL_WINDOW_SIZE = 65535;
    const DEFAULT_MAX_FRAME_SIZE = 16384;
    const DEFAULT_MAX_HEADER_LIST_SIZE = 65536;
    # Priority
    const DEFAULT_WEIGHT = 16;
    # Stream states
    const IDLE = 1;
    const RESERVED = 2;
    const OPEN = 3;
    const HALF_CLOSED = 4;
    const CLOSED = 5;
    # Endpoint types
    const CLIENT = 1;
    const SERVER = 2;
    # Preface string
    const PREFACE = "PRI * HTTP/2.0\x0d\x0a\x0d\x0aSM\x0d\x0a\x0d\x0a";
    # Frame types
    const DATA = 0;
    const HEADERS = 1;
    const PRIORITY = 2;
    const RST_STREAM = 3;
    const SETTINGS = 4;
    const PUSH_PROMISE = 5;
    const PING = 6;
    const GOAWAY = 7;
    const WINDOW_UPDATE = 8;
    const CONTINUATION = 9;
    # Flags
    const ACK = 0x1;
    const END_STREAM = 0x1;
    const END_HEADERS = 0x4;
    const PADDED = 0x8;
    const PRIORITY_FLAG = 0x20;
    # Errors
    const NO_ERROR = 0;
    const PROTOCOL_ERROR = 1;
    const INTERNAL_ERROR = 2;
    const FLOW_CONTROL_ERROR = 3;
    const SETTINGS_TIMEOUT = 4;
    const STREAM_CLOSED = 5;
    const FRAME_SIZE_ERROR = 6;
    const REFUSED_STREAM = 7;
    const CANCEL = 8;
    const COMPRESSION_ERROR = 9;
    const CONNECT_ERROR = 10;
    const ENHANCE_YOUR_CALM = 11;
    const INADEQUATE_SECURITY = 12;
    const HTTP_1_1_REQUIRED = 13;
    # SETTINGS
    const SETTINGS_HEADER_TABLE_SIZE = 1;
    const SETTINGS_ENABLE_PUSH = 2;
    const SETTINGS_MAX_CONCURRENT_STREAMS = 3;
    const SETTINGS_INITIAL_WINDOW_SIZE = 4;
    const SETTINGS_MAX_FRAME_SIZE = 5;
    const SETTINGS_MAX_HEADER_LIST_SIZE = 6;
//};

//require Exporter;
    static $EXPORT_TAGS = [
        'frame_types' => [
            'DATA', 'HEADERS', 'PRIORITY', 'RST_STREAM', 'SETTINGS', 'PUSH_PROMISE',
            'PING', 'GOAWAY', 'WINDOW_UPDATE', 'CONTINUATION'
        ],
        'errors' => [
            'NO_ERROR', 'PROTOCOL_ERROR', 'INTERNAL_ERROR', 'FLOW_CONTROL_ERROR',
            'SETTINGS_TIMEOUT', 'STREAM_CLOSED', 'FRAME_SIZE_ERROR', 'REFUSED_STREAM', 'CANCEL',
            'COMPRESSION_ERROR', 'CONNECT_ERROR', 'ENHANCE_YOUR_CALM', 'INADEQUATE_SECURITY',
            'HTTP_1_1_REQUIRED'
        ],
        'preface' => ['PREFACE'],
        'flags' => ['ACK', 'END_STREAM', 'END_HEADERS', 'PADDED', 'PRIORITY_FLAG'],
        'settings' => [
            'SETTINGS_HEADER_TABLE_SIZE', 'SETTINGS_ENABLE_PUSH',
            'SETTINGS_MAX_CONCURRENT_STREAMS', 'SETTINGS_INITIAL_WINDOW_SIZE',
            'SETTINGS_MAX_FRAME_SIZE', 'SETTINGS_MAX_HEADER_LIST_SIZE',
        ],
        'limits' => [
            'MAX_INT_SIZE', 'MAX_PAYLOAD_SIZE', 'MAX_FCW_SIZE', 'DEFAULT_WEIGHT',
            'DEFAULT_HEADER_TABLE_SIZE',
            'DEFAULT_MAX_CONCURRENT_STREAMS',
            'DEFAULT_ENABLE_PUSH',
            'DEFAULT_INITIAL_WINDOW_SIZE',
            'DEFAULT_MAX_FRAME_SIZE',
            'DEFAULT_MAX_HEADER_LIST_SIZE',
            'FRAME_HEADER_SIZE'
        ],
        'states' => ['IDLE', 'RESERVED', 'OPEN', 'HALF_CLOSED', 'CLOSED'],
        'endpoints' => ['CLIENT', 'SERVER']
    ];

    static $reverse = [];

    static function __init() {
//        no strict 'refs';
        foreach (self::$EXPORT_TAGS as $k => $tags) {
            foreach ($tags as $v) {
                self::$reverse[$k][constant('HTTP2Protocol\HTTP2\Constants'.$v)] = $v;
            }
        }
    }


    static function const_name($tag, $value) {
        return (isset(self::$reverse[$tag]))
            ? (self::$reverse[$tag][$value] || '')
            : '';
    }

//$EXPORT_OK = ( 'Constants::const_name', map { @$_ } values %EXPORT_TAGS );

}