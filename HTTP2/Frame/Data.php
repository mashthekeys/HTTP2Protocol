<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Data
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:flags :errors :settings :limits);
//use Protocol::HTTP2::Trace qw(tracer);
//
    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $length
     * @return mixed
     */
    static function decode($con, &$buf_ref, $buf_offset, $length) {
        $pad = 0; $offset = 0;
        $frame_ref = $con->decode_context()['frame'];

        # Protocol errors
        if (
            # DATA frames MUST be associated with a stream
            $frame_ref->stream == 0
        ) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if ($frame_ref->flags & Constants::PADDED) {
            $_ = unpack('Cpad', substr($buf_ref, $buf_offset));
            $pad = $_['pad'];
            $offset += 1;
        }

        $dblock_size = $length - $offset - $pad;
        if ($dblock_size < 0) {
            tracer::error("Not enough space for data block\n");
            $con->error(Constants::FRAME_SIZE_ERROR);
            return null;
        }

        $fcw = $con->fcw_recv(-$length);
        $stream_fcw = $con->stream_fcw_recv($frame_ref->stream, -$length);
        if ($fcw < 0 || $stream_fcw < 0) {
            tracer::debug(
                "received data overflow flow control window: $fcw|$stream_fcw\n");
            $con->stream_error($frame_ref->stream, Constants::FLOW_CONTROL_ERROR);
            return $length;
        }
        if ($fcw < $con->dec_setting(Constants::SETTINGS_MAX_FRAME_SIZE)) $con->fcw_update();
        if ($stream_fcw < $con->dec_setting(Constants::SETTINGS_MAX_FRAME_SIZE)
                && !($frame_ref->flags & Constants::END_STREAM)) {
            $con->stream_fcw_update($frame_ref->stream);
        }

        if (!$dblock_size) return $length;

        $data = substr($buf_ref, $buf_offset + $offset, $dblock_size);

        # Update stream data container
        $con->stream_data($frame_ref->stream, $data);

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