<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Ping
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

        # PING associated with connection
        if (
            $frame_ref->stream != 0
            ||

            # payload is 8 octets
            $length != 8
        ) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if (!$frame_ref->flags & Constants::ACK)
            $con->ack_ping(substr($buf_ref, $buf_offset, $length));

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
        if (strlen($data_ref) != 8) {
            $con->error(Constants::INTERNAL_ERROR);
            return null;
        }
        return $data_ref;
    }

}