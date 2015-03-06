<?php
namespace HTTP2Protocol\HTTP2;


class PerlCompat {
    public static function substr4(&$str, $offset, $len, $replacement) {
        $removed = substr($str, $offset, $len);
        $str = substr($str, 0, $offset) . $replacement . substr($str, $offset + $len);
        return $removed;
    }

    public static function encode_base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function decode_base64url($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

} 