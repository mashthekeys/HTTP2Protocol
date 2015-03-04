<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Push_promise
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:flags :errors :settings);
//use Protocol::HTTP2::Trace qw(tracer);

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
            # PP frames MUST be associated with a stream
            $frame_ref->stream == 0

            # PP frames MUST be allowed
            || !$con->dec_setting(Constants::SETTINGS_ENABLE_PUSH)
        ) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if ($frame_ref->flags & Constants::PADDED) {
            $_ = unpack('Cpad', substr($buf_ref, $buf_offset));
            $pad = $_['pad'];
            $offset += 1;
        }

        $_ = unpack('Nid', substr($buf_ref, $buf_offset + $offset, 4));
        $promised_sid = $_['id'];
        $promised_sid &= 0x7FFFFFFF;
        $offset += 4;

        $hblock_size = $length - $offset - $pad;
        if ($hblock_size < 0) {
            tracer::error("Not enough space for header block\n");
            $con->error(Constants::FRAME_SIZE_ERROR);
            return null;
        }

        if (!$con->new_peer_stream($promised_sid)) return null;
        $con->stream_promised_sid($frame_ref->stream, $promised_sid);

        $con->stream_header_block($frame_ref->stream,
            substr($$buf_ref, $buf_offset + $offset, $hblock_size));

        # PP header block complete
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
        $promised_id = $data_ref[0];
        $hblock_ref = $data_ref[1];

        return pack('N', $promised_id) . $hblock_ref;
    }

}