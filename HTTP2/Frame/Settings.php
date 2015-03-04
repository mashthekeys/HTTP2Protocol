<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;
use HTTP2Protocol\HTTP2\tracer;

class Settings
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(Constants::const_name :flags :errors :limits :settings);
//use Protocol::HTTP2::Trace qw(tracer);

    static $s_check;

    static function __init() {
        self::$s_check = [
            Constants::SETTINGS_MAX_FRAME_SIZE => function ($_) {
                    return $_ <= Constants::MAX_PAYLOAD_SIZE && $_ >= Constants::DEFAULT_MAX_FRAME_SIZE;
                },
        ];
    }

    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $length
     * @return mixed
     */
    static function decode($con, &$buf_ref, $buf_offset, $length) {
        $frame_ref = $con->decode_context()['frame'];

        if ($frame_ref->stream != 0) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if ($frame_ref->flags & Constants::ACK) {

            # just ack for our previous settings
            if ($length != 0) {
                tracer::error(
                    "ACK settings frame have non-zero ($length) payload\n");
                $con->error(Constants::FRAME_SIZE_ERROR);
                return null;
            }

        }

        if ($length == 0) return 0;

        if ($length % 6 != 0) {
            tracer::error("Settings frame payload is broken (lenght $length)\n");
            $con->error(Constants::FRAME_SIZE_ERROR);
            return null;
        }

        $settings = str_split(substr($buf_ref, $buf_offset, $length), 6);
        foreach($settings as $settingBytes) {
            $_ = unpack('nkey/Nvalue', $settingBytes);
            $key = $_['key'];
            $value = $_['value'];

            if ($con->enc_setting($key) === null) {
                tracer::debug("\tUnknown setting $key\n");

                # ignore unknown setting
                continue;
            }
            elseif (isset(self::$s_check[$key])
            && !( $s_check[$key]($value) ) )
            {
                tracer::debug("\tInvalid value of setting "
                    . Constants::const_name("settings", $key) . ": "
                    . $value);
                $con->error(Constants::PROTOCOL_ERROR);
                return null;
            }

            tracer::debug(
                "\tSettings " . Constants::const_name("settings", $key) . " = $value\n");
            $con->enc_setting($key, $value);
        }

        $con->accept_settings();
        return $length;
    }

    /**
     * @param Connection $con
     * @param $flags_ref
     * @param $stream_id
     * @param $data_ref
     * @return mixed
     */
    static function encode($con, &$flags_ref, $stream_id, &$data_ref) {
        $payload = '';
        $keys = array_keys($data_ref);
        sort($keys);
        foreach ($keys as $key) {
            tracer::debug("\tSettings "
                . Constants::const_name("settings", $key)
                . " = {$data_ref[$key]}\n");
            $payload .= pack('nN', $key, $data_ref[$key]);
        }
        return $payload;
    }
}

Settings::__init();
