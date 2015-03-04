<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;
use HTTP2Protocol\HTTP2\tracer;

class Goaway
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(Constants::const_name :flags :errors);
//use Protocol::HTTP2::Trace qw(tracer bin2hex);

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

        $_ = unpack('Nid/Nerr', substr($buf_ref, $buf_offset, 8));
        $last_stream_id = $_['id'];
        $error_code = $_['err'];

        $last_stream_id &= 0x7FFFFFFF;

        tracer::debug("GOAWAY with error code "
            . Constants::const_name('errors', $error_code)
            . " last stream is $last_stream_id\n");

        if ($length - 8 > 0) tracer::debug("additional debug data: "
            . bin2hex(substr($$buf_ref, $buf_offset + 8)));


        $con->goaway(1);

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

        $con->goaway(1);

        $payload = pack('N2', $data_ref[0], $data_ref[1]);
        tracer::debug("\tGOAWAY: last stream = $data_ref[0], error = "
            . Constants::const_name("errors", $data_ref[1])
            . "\n");
        if (count($data_ref) > 2) $payload .= $data_ref[2];
        return $payload;
    }

}
