<?php
namespace HTTP2Protocol\HTTP2;
use HTTP2Protocol\HTTP2;

class Upgrade
{
//package Protocol::HTTP2::Upgrade;
//use strict;
//use warnings;
//use Protocol::HTTP2;
//use Protocol::HTTP2::Constants qw(:frame_types :errors :states);
//use Protocol::HTTP2::Trace qw(tracer);
//use MIME::Base64 qw(encode_base64url decode_base64url);
//
//#use re 'debug';
    static $end_headers_re = '(?s:.+?\x0d?\x0a\x0d?\x0a)';
    static $header_re = '(?:[ \t]*(.+?)[ \t]*\:[ \t]*(.+?)[ \t]*\x0d?\x0a)';

    /**
     * @param Connection $con
     * @param $h
     * @return string
     */
    static function upgrade_request($con, $h) {
        $request = sprintf("%s %s HTTP/1.1\x0d\x0aHost: %s\x0d\x0a",
            $h[':method'], $h[':path'],
            $h[':authority']
        );

        $headers = $h['headers'];
        if (count($headers) && !isset($headers[0])) {
            $headerList = $headers;
        } else {
            $headerList = [];
            while (list($k, $v) = array_splice($headers, 0, 2)) {
                $headerList[$k] = $v;
            }
        }

        foreach ($headerList as $header => $value) {
            if (in_array(strtolower($header), ['connection', 'upgrade', 'http2-settings'])) continue;
            $request .= $header . ': ' . $value . "\x0d\x0a";
        }

        return $request . implode("\x0d\x0a", [
            'Connection: Upgrade, HTTP2-Settings',
            'Upgrade: ' . HTTP2::ident_plain(),
            'HTTP2-Settings: '
            . PerlCompat::encode_base64url($con->frame_encode(Constants::SETTINGS, 0, 0, [])),
            '', ''
        ]);
    }

    static function upgrade_response() {
        return implode("\x0d\x0a", [
            "HTTP/1.1 101 Switching Protocols",
            "Connection: Upgrade",
            "Upgrade: " . HTTP2::ident_plain(),
            "", ""
        ]);
    }

    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $headers_ref
     * @return int|null
     */
    static function decode_upgrade_request($con, &$buf_ref, $buf_offset, &$headers_ref) {
//        pos($buf_ref) = $buf_offset;
        $buf_contents = substr($buf_ref, $buf_offset);

        # Search end of headers
        if (!preg_match('/^' . self::$end_headers_re . '/', $buf_contents, $match)) return 0;
        $end_headers_pos = strlen($match[0]);

//        pos($buf_ref) = $buf_offset;

        # Request
//        return undef if $$buf_ref !~ m#\G(\w+) ([^ ]+) HTTP/1\.1\x0d?\x0a#g;
        if (!preg_match('#^(\w+) ([^ ]+) HTTP/1\.1\x0d?\x0a#', $buf_contents, $match)) return null;
        list($method, $uri) = [$match[1], $match[2]];
        $buf_contents = substr($buf_contents, strlen($match[0]));

        # TODO: remove after http2 -> http/1.1 headers conversion implemented
        array_push($headers_ref, ":method", $method);
        array_push($headers_ref, ":path", $uri);
        array_push($headers_ref, ":scheme", 'http');

        $success = 0;

        # Parse headers
        while ($success != 0b111 && preg_match('/^' . self::$header_re . '/', $buf_contents, $match)) {
            list($header, $value) = [strtolower($match[1]), $match[2]];
            $buf_contents = substr($buf_contents, strlen($match[0]));

            if ($header == "connection") {
                $h = array_fill_keys(preg_split('/\s*,\s*/', strtolower($value)), 1, PREG_SPLIT_NO_EMPTY);
                if (isset($h['upgrade']) && isset($h['http2-settings'])) $success |= 0b001;
            } elseif (
                $header == "upgrade"
                && in_array(preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY), HTTP2::ident_plain())
            ) {
                $success |= 0b010;
            } elseif ($header == "http2-settings"
                && null != $con->frame_decode(PerlCompat::decode_base64url($value), 0)
            ) {
                $success |= 0b100;
            } else {
                array_push($headers_ref, $header, $value);
            }
        }

        if ($success != 0b111) return null;

        # TODO: method POST also can contain data...

        return $end_headers_pos;
    }

    static function decode_upgrade_response($con, $buf_ref, $buf_offset) {
//        pos($$buf_ref) = $buf_offset;
        $buf_contents = substr($buf_ref, $buf_offset);

        # Search end of headers
        if (!preg_match('/^' . self::$end_headers_re . '/', $buf_contents, $match)) return 0;
        $end_headers_pos = strlen($match[0]);

//        pos($$buf_ref) = $buf_offset;

        # Switch Protocols failed
        if (!preg_match('#^HTTP/1\.1 101 .+?\x0d?\x0a#', $buf_contents, $match)) return null;
        $buf_contents = substr($buf_contents, strlen($match[0]));

        $success = 0;

        # Parse headers
        while ($success != 0b11 && preg_match('/^' . self::$header_re . '/', $buf_contents, $match)) {
            list($header, $value) = [strtolower($match[1]), $match[2]];
            $buf_contents = substr($buf_contents, strlen($match[0]));

            if ($header == "connection" && strtolower($value) == "upgrade") {
                $success |= 0b01;
            } elseif ($header == "upgrade" && $value == HTTP2::ident_plain()) {
                $success |= 0b10;
            }
        }

        if ($success != 0b11) return null;

        return $end_headers_pos;
    }

}
