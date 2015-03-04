<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Priority
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:flags :errors);
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

        # Priority frames MUST be associated with a stream
        if ($frame_ref->stream == 0) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if ($length != 5) {
            $con->error(Constants::FRAME_SIZE_ERROR);
            return null;
        }

        $_ = unpack('Nid/Cw', substr($$buf_ref, $buf_offset, 5));
        $stream_dep = $_['id'];
        $weight = $_['w'];

        $exclusive = $stream_dep >> 31;
        $stream_dep &= 0x7FFFFFFF;
        $weight++;

        $con->stream_weight($frame_ref->stream, $weight);
        $con->stream_reprio($frame_ref->stream, $exclusive, $stream_dep);

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
        $stream_dep = $data_ref[0];
        $weight = $data_ref[1] - 1;
        return pack('NC', $stream_dep, $weight);
    }

}