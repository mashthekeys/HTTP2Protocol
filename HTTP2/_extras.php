<?php
/**
 * Created by IntelliJ IDEA.
 * User: Andy
 * Date: 27/02/2015
 * Time: 18:07
 */

function perl_substr4(&$str, $offset, $len, $replacement) {
    $removed = substr($str, $offset, $len);
    $str = substr($str, 0, $offset) . $replacement . substr($str, $offset + $len);
    return $removed;
}

function encode_base64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function decode_base64url($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
