<?php
namespace HTTP2Protocol\HTTP2;
require_once __DIR__.'/Stream.php';

class Connection
extends Stream//, Frame, Upgrade
{
//package Protocol::HTTP2::Connection;
//use strict;
//use warnings;
//use Protocol::HTTP2::Constants
//  qw(Constants::const_name :frame_types :errors :settings :flags :states
//  :limits :endpoints);
//use Protocol::HTTP2::HeaderCompression qw(headers_encode);
//use Protocol::HTTP2::Frame;
//use Protocol::HTTP2::Stream;
//use Protocol::HTTP2::Upgrade;
//use Protocol::HTTP2::Trace qw(tracer);
//
//# Mixin
//our @ISA =
//  qw(Protocol::HTTP2::Frame Protocol::HTTP2::Stream Protocol::HTTP2::Upgrade);

    # Mixin Frame
    function frame_decode($frame_ref, $offset) { return Frame::frame_decode($this, $frame_ref, $offset); }
    function frame_encode($type, $flags, $stream_id, $data_ref) { return Frame::frame_encode($this, $type, $flags, $stream_id, $data_ref); }
    function frame_header_decode($frame_ref, $offset) { return Frame::frame_header_decode($this, $frame_ref, $offset); }
    function preface_decode($frame_ref, $offset) { return Frame::preface_decode($this, $frame_ref, $offset); }
    function preface_encode() { return Frame::preface_encode(); }
    # Mixin Upgrade
    function upgrade_request($h) { return Upgrade::upgrade_request($this, $h); }
    function upgrade_response() { return Upgrade::upgrade_response(); }
    function decode_upgrade_request(&$buf_ref, $buf_offset, &$headers_ref) { return Upgrade::decode_upgrade_request($this, $buf_ref, $buf_offset, $headers_ref); }
    function decode_upgrade_response(&$buf_ref, $buf_offset) { return Upgrade::decode_upgrade_response($this, $buf_ref, $buf_offset); }

    # Default settings
    static $default_settings = [
        Constants::SETTINGS_HEADER_TABLE_SIZE => Constants::DEFAULT_HEADER_TABLE_SIZE,
        Constants::SETTINGS_ENABLE_PUSH => Constants::DEFAULT_ENABLE_PUSH,
        Constants::SETTINGS_MAX_CONCURRENT_STREAMS => Constants::DEFAULT_MAX_CONCURRENT_STREAMS,
        Constants::SETTINGS_INITIAL_WINDOW_SIZE => Constants::DEFAULT_INITIAL_WINDOW_SIZE,
        Constants::SETTINGS_MAX_FRAME_SIZE => Constants::DEFAULT_MAX_FRAME_SIZE,
        Constants::SETTINGS_MAX_HEADER_LIST_SIZE => Constants::DEFAULT_MAX_HEADER_LIST_SIZE,
    ];
    public $encode_ctx;
    public $decode_ctx;
    public $error;
    public $queue;
    public $shutdown;
    public $preface;
    public $upgrade;
    public $fcw_send;
    public $fcw_recv;

    function __construct($type, $opts) {
        $this->type = $type;

        $this->streams = [];

        $this->last_stream = $type == Constants::CLIENT ? 1 : 2;
        $this->last_peer_stream = 0;

        $this->encode_ctx = [

            # HPACK. Header Table
            'header_table' => [],

            # HPACK. Header Table size
            'ht_size' => 0,

            'settings' => self::$default_settings,

        ];

        $this->decode_ctx = [

            # HPACK. Header Table
            'header_table' => [],

            # HPACK. Header Table size
            'ht_size' => 0,

            # HPACK. Emitted headers
            'emitted_headers' => [],

            # last frame
            'frame' => [],

            'settings' => self::$default_settings,
        ];

        # Current error
        $this->error = 0;

        # Output frames queue
        $this->queue = [];

        # Connection must be shutdown
        $this->shutdown = 0;

        # issued GOAWAY: no new streams on this connection
        $this->goaway = 0;

        # get preface
        $this->preface = 0;

        # perform upgrade
        $this->upgrade = 0;

        # flow control
        $this->fcw_send = Constants::DEFAULT_INITIAL_WINDOW_SIZE;
        $this->fcw_recv = Constants::DEFAULT_INITIAL_WINDOW_SIZE;

        foreach (['on_change_state', 'on_new_peer_stream', 'on_error', 'upgrade'] as $_) {
            if (isset($opts[$_])) $this->$_ = $opts[$_];
        }

        if (isset($opts['settings'])) {
            foreach ($opts['settings'] as $k => $v) {
                $this->decode_ctx['settings'][$k] = $v;
            }
        }
    }

    function decode_context() {
        return $this->decode_ctx;
    }

    function encode_context() {
        return $this->encode_ctx;
    }

    function dequeue() {
        return array_shift($this->queue);
    }

    function process_state(&$frame_ref) {
        list($length, $type, $flags, $stream_id) =
            $this->frame_header_decode($frame_ref, 0);

        # Sended frame may change state of stream
        if ($type != Constants::SETTINGS && $type != Constants::GOAWAY && $stream_id != 0)
            $this->state_machine('send', $type, $flags, $stream_id);
    }

    function enqueue() {
        $frames = func_get_args();
        foreach ($frames as $_) {
            array_push($this->queue, $_);

            if (!$this->upgrade && $this->preface) $this->process_state($_);
        }
    }

    function enqueue_first($frames) {
        $i = 0;
        for ($_ = 0; $_ < count($this->queue); ++$_) {
            $H = $this->frame_header_decode($this->queue[$_], 0);
            if ($H[1] != Constants::CONTINUATION) break;
            $i++;
        }
        foreach ($frames as $_) {
            array_splice($this->queue, $i++, 0, $_);
            $this->process_state($_);
        }
    }

    function finish() {
        if (!$this->shutdown) $this->enqueue(
            $this->frame_encode(Constants::GOAWAY, 0, 0,
                [$this->last_peer_stream, $this->error]
            )
        );
        $this->shutdown(1);
    }

    function shutdown($set = null) {
        if ($set !== null) $this->shutdown = $set;
        return $this->shutdown;
    }

    function goaway($set = null) {
        if ($set !== null) $this->goaway = $set;
        return $this->goaway;
    }

    function preface($set = null) {
        if ($set !== null) $this->preface = $set;
        return $this->preface;
    }

    function upgrade($set = null) {
        if ($set !== null) $this->upgrade = $set;
        return $this->upgrade;
    }

    function state_machine($act, $type, $flags, $stream_id) {
        $promised_sid = $this->stream_promised_sid($stream_id);

        $prev_state = $this->streams[$promised_sid || $stream_id]->state;

        # Direction server->client
        $srv2cln = ($this->type == Constants::SERVER && $act === 'send')
            || ($this->type == Constants::CLIENT && $act === 'recv');

        # Direction client->server
        $cln2srv = ($this->type == Constants::SERVER && $act === 'recv')
            || ($this->type == Constants::CLIENT && $act === 'send');

        # Do we expect CONTINUATION after this frame?
        $pending = ($type == Constants::HEADERS || $type == Constants::PUSH_PROMISE)
            && !($flags & Constants::END_HEADERS);

        #tracer::debug(
        #    sprintf "\e[0;31mStream state: frame %s is %s%s on %s stream %i\e[m\n",
        #    Constants::const_name( "frame_types", $type ),
        #    $act,
        #    $pending ? "*" : "",
        #    Constants::const_name( "states", $prev_state ),
        #    $promised_sid || $stream_id,
        #    $stream_id,
        #);

        # Wait until all CONTINUATION frames arrive
        if ($ps = $this->stream_pending_state($stream_id)) {
            if ($type != Constants::CONTINUATION) {
                tracer::error(
                    sprintf("invalid frame type %s. Expected CONTINUATION frame\n",
                        Constants::const_name("frame_types", $type)
                    )
                );
                $this->error(Constants::PROTOCOL_ERROR);
            } elseif ($flags & Constants::END_HEADERS) {
                if ($promised_sid) $this->stream_promised_sid($stream_id, null);
                $this->stream_pending_state($promised_sid || $stream_id, null);
                $this->stream_state($promised_sid || $stream_id, $ps);
            }
        }

        # State machine
        # IDLE
        elseif ($prev_state == Constants::IDLE) {
            if ($type == Constants::HEADERS && $cln2srv) {
                $this->stream_state($stream_id,
                    ($flags & Constants::END_STREAM) ? Constants::HALF_CLOSED : Constants::OPEN, $pending);
            } elseif ($type == Constants::PUSH_PROMISE && $srv2cln) {
                $this->stream_state($promised_sid, Constants::RESERVED, $pending);
                if ($flags & Constants::END_HEADERS) $this->stream_promised_sid($stream_id, null);
            } elseif ($type != Constants::PRIORITY) {
                tracer::error(
                    sprintf(
                        "invalid frame type %s for current stream state %s\n",
                        Constants::const_name("frame_types", $type),
                        Constants::const_name("states", $prev_state)
                    )
                );
                $this->error(Constants::PROTOCOL_ERROR);
            }
        } # OPEN
        elseif ($prev_state == Constants::OPEN) {
            if (($flags & Constants::END_STREAM)
                && ($type == Constants::DATA || $type == Constants::HEADERS)
            ) {
                $this->stream_state($stream_id, Constants::HALF_CLOSED, $pending);
            } elseif ($type == Constants::RST_STREAM) {
                $this->stream_state($stream_id, Constants::CLOSED);
            }
        } # RESERVED (local/remote)
        elseif ($prev_state == Constants::RESERVED) {
            if ($type == Constants::RST_STREAM) {
                $this->stream_state($stream_id, Constants::CLOSED);
            } elseif ($type == Constants::HEADERS && $srv2cln) {
                $this->stream_state($stream_id,
                    ($flags & Constants::END_STREAM) ? Constants::CLOSED : Constants::HALF_CLOSED, $pending);
            } elseif ($type != Constants::PRIORITY && $cln2srv) {
                tracer::error("invalid frame $type for state RESERVED");
                $this->error(Constants::PROTOCOL_ERROR);
            }
        } # HALF_CLOSED (local/remote)
        elseif ($prev_state == Constants::HALF_CLOSED) {
            if (($type == Constants::RST_STREAM)
                || (($flags & Constants::END_STREAM) && $srv2cln)
            ) {
                $this->stream_state($stream_id, Constants::CLOSED, $pending);
            } elseif (in_array($type, [Constants::WINDOW_UPDATE, Constants::PRIORITY])
                && $cln2srv
            ) {
                tracer::error(sprintf(
                        "invalid frame %s for state HALF CLOSED\n",
                        Constants::const_name("frame_types", $type)
                    )
                );
                $this->error(Constants::PROTOCOL_ERROR);
            }
        } # CLOSED
        elseif ($prev_state == Constants::CLOSED) {
            if ($type != Constants::PRIORITY && ($type != Constants::WINDOW_UPDATE && $cln2srv)) {

                tracer::error("stream is closed\n");
                $this->error(Constants::STREAM_CLOSED);
            }
        } else {
            tracer::error("oops!\n");
            $this->error(Constants::INTERNAL_ERROR);
        }
    }

    # TODO: move this to some other module
    function send_headers($stream_id, $headers, $end) {
        $max_size = $this->enc_setting(Constants::SETTINGS_MAX_FRAME_SIZE);

        tracer::debug("HEADERS: ".var_export($headers,1));
        tracer::debug("CONTEXT: ".var_export($this->encode_context(),1));

        $header_block = HeaderCompression::headers_encode($this->encode_context(), $headers);

        tracer::debug("HEADER BLOCK (".strlen($header_block)." bytes) ".addcslashes($header_block,"\x00..\x1F\x7F..\xFF"));

        $flags = $end ? Constants::END_STREAM : 0;
        if (strlen($header_block) <= $max_size) $flags |= Constants::END_HEADERS;

        $this->enqueue(
            $this->frame_encode(Constants::HEADERS, $flags, $stream_id,
                ['hblock' => perl_substr4($header_block, 0, $max_size, '')]
            )
        );

        while (strlen($header_block) > 0) {
            $flags = strlen($header_block) <= $max_size ? 0 : Constants::END_HEADERS;
            $this->enqueue(
                $this->frame_encode(Constants::CONTINUATION, $flags,
                    $stream_id, perl_substr4($header_block, 0, $max_size, '')
                )
            );
        }
    }

    function send_pp_headers($stream_id, $promised_id, $headers) {
        $max_size = $this->enc_setting(Constants::SETTINGS_MAX_FRAME_SIZE);

        $header_block = HeaderCompression::headers_encode($this->encode_context(), $headers);

        $flags = strlen($header_block) <= $max_size ? Constants::END_HEADERS : 0;

        $this->enqueue(
            $this->frame_encode(Constants::PUSH_PROMISE, $flags, $stream_id,
                [$promised_id, perl_substr4($header_block, 0, $max_size - 4, '')]
            )
        );

        while (strlen($header_block) > 0) {
            $flags = strlen($header_block) <= $max_size ? 0 : Constants::END_HEADERS;
            $this->enqueue(
                $this->frame_encode(Constants::CONTINUATION, $flags,
                    $stream_id, perl_substr4($header_block, 0, $max_size, '')
                )
            );
        }
    }

    function send_data($stream_id, $data) {
        $blocked_data =& $this->stream_blocked_data($stream_id);
        if (isset($blocked_data)) {
            $data = $blocked_data . $data;
            $blocked_data = null;
        }
        while (1) {
            $l = strlen($data);
            $size = $this->enc_setting(Constants::SETTINGS_MAX_FRAME_SIZE);
            foreach ([$l, $this->fcw_send, $this->stream_fcw_send($stream_id)] as $_) {
                if ($size > $_) $size = $_;
            }
            $flags = $l == $size ? Constants::END_STREAM : 0;

            # Flow control
            if ($l != 0 && $size == 0) {
                $this->stream_blocked_data($stream_id, $data);
                break;
            }
            $this->fcw_send(-$size);
            $this->stream_fcw_send($stream_id, -$size);

            $this->enqueue(
                $this->frame_encode(Constants::DATA, $flags,
                    $stream_id, perl_substr4($data, 0, $size, '')
                )
            );
            if ($flags & Constants::END_STREAM) break;
        }
    }

    function send_blocked() {
        foreach (array_keys($this->streams) as $stream_id) {
            $this->stream_send_blocked($stream_id);
        }
    }

    function error($error = null) {
        if (func_num_args() && !$this->shutdown) {
            $this->error = $error;
            if (isset($this->on_error)) call_user_func($this->on_error, $this->error);
            $this->finish();
        }
        return $this->error;
    }

    function setting() {
//        require Carp;
//        Carp::confess("setting is deprecated\n");
        throw new \RuntimeException("setting is deprecated\n");
    }

    protected function _setting($ctx, $setting, $set = false, $value = null) {
        $s = $this->{$ctx}['settings'];

        if ($set && isset($s[$setting])) {
            $s[$setting] = $value;
        }

        return $s[$setting];
    }

    function enc_setting($setting, $value = null) {
        return $this->_setting('encode_ctx', $setting, func_num_args() > 1, $value);
    }

    function dec_setting($setting, $value = null) {
        return $this->_setting('decode_ctx', $setting, func_num_args() > 1, $value);
    }

    function accept_settings() {
        $this->enqueue($this->frame_encode(Constants::SETTINGS, Constants::ACK, 0, []));
    }

    # Flow control windown of connection
    function fcw_send($amount = 0) {
        return $this->_fcw('send', $amount);
    }

    function fcw_recv($amount = 0) {
        return $this->_fcw('recv', $amount);
    }

    protected function _fcw($dir, $amount = 0) {
        if ($amount) {
            $this->{'fcw_' . $dir} += $amount;
            tracer::debug("fcw_$dir now is " . $this->{'fcw_' . $dir} . "\n");
        }
        return $this->{'fcw_' . $dir};
    }

    function fcw_update() {
        # TODO: check size of data in memory
        tracer::debug("update fcw recv of connection\n");
        $this->fcw_recv(Constants::DEFAULT_INITIAL_WINDOW_SIZE);
        $this->enqueue(
            $this->frame_encode(Constants::WINDOW_UPDATE, 0, 0, Constants::DEFAULT_INITIAL_WINDOW_SIZE)
        );
    }

    function ack_ping($payload_ref) {
        $this->enqueue_first($this->frame_encode(Constants::PING, Constants::ACK, 0, $payload_ref));
    }

}
