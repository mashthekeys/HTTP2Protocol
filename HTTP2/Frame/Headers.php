<?php
namespace HTTP2Protocol\HTTP2\Frame;
use HTTP2Protocol\HTTP2\Connection;
use HTTP2Protocol\HTTP2\Constants;

class Headers
{

//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:flags :errors :states :limits);
//use Protocol::HTTP2::Trace qw(tracer);

# 6.2 HEADERS
    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $length
     * @return mixed
     */
    static function decode($con, &$buf_ref, $buf_offset, $length) {
//        ($pad, $offset, $weight, $exclusive, $stream_dep ) = (0, 0 );
        $pad = 0; $offset = 0;
        $frame_ref = $con->decode_context()['frame'];

        # Protocol errors
        if (
            # HEADERS frames MUST be associated with a stream
            $frame_ref->stream == 0
        ) {
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if ($frame_ref->flags & Constants::PADDED) {
            $_ = unpack('Cpad', substr($buf_ref, $buf_offset, 1));
            $pad = $_['pad'];
            $offset += 1;
        }

        if ($frame_ref->flags & Constants::PRIORITY_FLAG) {
            $_ = unpack('Nid/Cw', substr($$buf_ref, $buf_offset + $offset, 5));
            $stream_dep = $_['id'];
            $weight = $_['w'];

            $exclusive = $stream_dep >> 31;
            $stream_dep &= 0x7FFFFFFF;
            $weight++;

            $con->stream_weight($frame_ref->stream, $weight);
            $con->stream_reprio($frame_ref->stream, $exclusive, $stream_dep);

            $offset += 5;
        }

        # Not enough space for header block
        $hblock_size = $length - $offset - $pad;
        if ($hblock_size < 0) {
            $con->error(Constants::FRAME_SIZE_ERROR);
            return null;
        }

        $con->stream_header_block($frame_ref->stream,
            substr($$buf_ref, $buf_offset + $offset, $hblock_size));

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
        $res = '';

        if (isset($data_ref->padding)) {
            $flags_ref |= Constants::PADDED;
            $res .= pack('C', $data_ref->padding);
        }

        if (isset($data_ref->stream_dep) || isset($data_ref->weight) ) {
            $flags_ref |= Constants::PRIORITY_FLAG;
            $weight = ($data_ref->weight || Constants::DEFAULT_WEIGHT) - 1;
            $stream_dep = $data_ref->stream_dep || 0;
            if ($data_ref->exclusive) $stream_dep |= (1 << 31);
            $res .= pack('NC', $stream_dep, $weight);
        }

        return $res . $data_ref->hblock;
//        return $res . ${$data_ref->hblock};
    }

}
