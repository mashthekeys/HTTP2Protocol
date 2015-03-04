<?php
namespace HTTP2Protocol\HTTP2;
class Frame
{
//package Protocol::HTTP2::Frame;
//use strict;
//use warnings;
//use Protocol::HTTP2::Trace qw(tracer);
//use Protocol::HTTP2::Constants
//  qw(Constants::const_name :frame_types :errors :preface :states :flags :limits :settings);
//use Protocol::HTTP2::Frame::Data;
//use Protocol::HTTP2::Frame::Headers;
//use Protocol::HTTP2::Frame::Priority;
//use Protocol::HTTP2::Frame::Rst_stream;
//use Protocol::HTTP2::Frame::Settings;
//use Protocol::HTTP2::Frame::Push_promise;
//use Protocol::HTTP2::Frame::Ping;
//use Protocol::HTTP2::Frame::Goaway;
//use Protocol::HTTP2::Frame::Window_update;
//use Protocol::HTTP2::Frame::Continuation;

    # Table of payload decoders
    static $frame_class = [
        Constants::DATA          => 'Data',
        Constants::HEADERS       => 'Headers',
        Constants::PRIORITY      => 'Priority',
        Constants::RST_STREAM    => 'Rst_stream',
        Constants::SETTINGS      => 'Settings',
        Constants::PUSH_PROMISE  => 'Push_promise',
        Constants::PING          => 'Ping',
        Constants::GOAWAY        => 'Goaway',
        Constants::WINDOW_UPDATE => 'Window_update',
        Constants::CONTINUATION  => 'Continuation',
    ];

    static $decoder;
    static $encoder;

    static function __init() {
        self::$decoder = array_map(function($_){return '\HTTP2Protocol\HTTP2\Frame\\'.$_.'::decode';}, self::$frame_class);
        self::$encoder = array_map(function($_){return '\HTTP2Protocol\HTTP2\Frame\\'.$_.'::encode';}, self::$frame_class);
    }

    static function frame_encode($con, $type, $flags, $stream_id, $data_ref) {
        $payload = call_user_func_array(self::$encoder[$type], [$con, &$flags, $stream_id, &$data_ref]);
        $l = strlen($payload);

        return pack( 'CnC2N', ( $l >> 16 ), ( $l & 0xFFFF ), $type, $flags, $stream_id )
          . $payload;
    }

    static function preface_decode($con, &$buf_ref, $buf_offset) {
        if (strlen($buf_ref) - $buf_offset < strlen(Constants::PREFACE)) return 0;
        return
          strpos( $buf_ref, Constants::PREFACE, $buf_offset ) === false ? null : strlen(Constants::PREFACE);
    }

    static function preface_encode() {
        return Constants::PREFACE;
    }

    static function frame_header_decode( $con, &$buf_ref, $buf_offset ) {
        $_ = unpack( 'Chl/nll/Ctype/Cflags/Nid', substr( $buf_ref, $buf_offset, Constants::FRAME_HEADER_SIZE ) );

        $length = ( $_['hl'] << 16 ) + $_['ll'];
        $stream_id = $_['id'] & 0x7FFFFFFF;

        return [$length, $_['type'], $_['flags'], $stream_id];
    }

    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @return int|null
     */
    static function frame_decode($con, &$buf_ref, $buf_offset) {
        if (strlen($buf_ref) - $buf_offset < Constants::FRAME_HEADER_SIZE) return 0;

        list( $length, $type, $flags, $stream_id ) =
          $con->frame_header_decode( $buf_ref, $buf_offset );

        if ( $length > $con->dec_setting(Constants::SETTINGS_MAX_FRAME_SIZE) ) {
            tracer::debug("Frame is too large: $length\n");
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if (strlen($buf_ref) - $buf_offset - Constants::FRAME_HEADER_SIZE - $length < 0) return 0;

        # Unknown type of frame
        if ( !isset(self::$frame_class[$type])) {
            tracer::debug("Unknown type of frame: $type\n");

            # ignore it
            return Constants::FRAME_HEADER_SIZE + $length;
        }

        tracer::debug(
            sprintf("TYPE = %s(%i), FLAGS = %08b, STREAM_ID = %i, "
              . "LENGTH = %i\n",
                Constants::const_name( "frame_types", $type ),
                $type,
                $flags,
                $stream_id,
                $length
            )
        );

        $con->decode_ctx['frame'] = [
            'type'   => $type,
            'flags'  => $flags,
            'length' => $length,
            'stream' => $stream_id,
        ];

        # Create new stream structure
        # Error when stream_id is invalid
        if (   $stream_id
            && !$con->stream($stream_id)
            && !$con->new_peer_stream($stream_id) )
        {
            tracer::debug("Peer send invalid stream id: $stream_id\n");
            $con->error(Constants::PROTOCOL_ERROR);
            return null;
        }

        if (null === call_user_func(self::$decoder[$type], $con, $buf_ref, $buf_offset + Constants::FRAME_HEADER_SIZE, $length )) {
            return null;
        }

        # Arrived frame may change state of stream
        if ($type != Constants::SETTINGS && $type != Constants::GOAWAY && $stream_id != 0)
            $con->state_machine( 'recv', $type, $flags, $stream_id );

        return Constants::FRAME_HEADER_SIZE + $length;
    }
/*
=pod

=head1 NOTES

=head2 Frame Types vs Flags and Stream ID

    Table represent possible combination of frame types and flags.
    Last column -- Stream ID of frame types (x -- sid >= 1, 0 -- sid = 0)


                        +-END_STREAM 0x1
                        |   +-ACK 0x1
                        |   |   +-END_HEADERS 0x4
                        |   |   |   +-PADDED 0x8
                        |   |   |   |   +-PRIORITY 0x20
                        |   |   |   |   |        +-stream id (value)
                        |   |   |   |   |        |
    | frame type\flag | V | V | V | V | V |   |  V  |
    | --------------- |:-:|:-:|:-:|:-:|:-:| - |:---:|
    | DATA            | x |   |   | x |   |   |  x  |
    | HEADERS         | x |   | x | x | x |   |  x  |
    | PRIORITY        |   |   |   |   |   |   |  x  |
    | RST_STREAM      |   |   |   |   |   |   |  x  |
    | SETTINGS        |   | x |   |   |   |   |  0  |
    | PUSH_PROMISE    |   |   | x | x |   |   |  x  |
    | PING            |   | x |   |   |   |   |  0  |
    | GOAWAY          |   |   |   |   |   |   |  0  |
    | WINDOW_UPDATE   |   |   |   |   |   |   | 0/x |
    | CONTINUATION    |   |   | x | x |   |   |  x  |

=cut
*/
}

Frame::__init();