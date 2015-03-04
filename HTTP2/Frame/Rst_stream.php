<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;
use HTTP2Protocol\HTTP2\tracer;

class Rst_stream
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(Constants::const_name :flags :errors);
//use Protocol::HTTP2::Trace qw(tracer);

    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $length
     * @return mixed
     */
    static function decode($con, &$buf_ref, $buf_offset, $length) {
        $frame_ref = $con->decode_context()['frame'];

        # RST_STREAM associated with stream
        if ($frame_ref->stream == 0) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        $_ = unpack('Ncode', substr($buf_ref, $buf_offset, 4));
        $code = $_['code'];

        tracer::debug("Receive reset stream with error code "
            . Constants::const_name("errors", $code)
            . "\n");

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
        return pack('N', $data);
    }

}