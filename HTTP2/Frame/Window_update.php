<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Window_update
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:flags :errors :limits);
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

        $_ = unpack('Nadd', substr($buf_ref, $buf_offset, 4));
        $fcw_add = $_['add'];
        $fcw_add &= 0x7FFFFFFF;

        if ($frame_ref->stream == 0) {
            if ($con->fcw_send($fcw_add) > Constants::MAX_FCW_SIZE) {
                $con->error(Constants::FLOW_CONTROL_ERROR);
            } else {
                $con->send_blocked();
            }
        } else {
            $fcw = $con->stream_fcw_send($frame_ref->stream, $fcw_add);
            if (isset($fcw) && $fcw > Constants::MAX_FCW_SIZE ) {
                $con->stream_error($frame_ref->stream, Constants::FLOW_CONTROL_ERROR);
            }
            elseif (isset($fcw) ) {
                $con->stream_send_blocked($frame_ref->stream);
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
        return pack('N', $data_ref);
    }

}