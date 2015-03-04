<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Continuation
{
//package Protocol::HTTP2::Frame::Continuation;
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

        # Protocol errors
        if (
            # CONTINUATION frames MUST be associated with a stream
            $frame_ref->stream == 0
        ) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        $con->stream_header_block($frame_ref->stream,
            substr($$buf_ref, $buf_offset, $length));

        # Stream header block complete
        if ($frame_ref->flags & Constants::END_HEADERS) {
            if (!$con->stream_headers_done($frame_ref->stream)) {
                return null;
            }
        }
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
        return $data_ref;
    }

}
