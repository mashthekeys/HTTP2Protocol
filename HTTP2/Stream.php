<?php
namespace HTTP2Protocol\HTTP2;
class Stream
{
//package Protocol::HTTP2::Stream;
//use strict;
//use warnings;
//use Protocol::HTTP2::Constants qw(:states :endpoints :settings :frame_types
//  :limits);
//use Protocol::HTTP2::HeaderCompression qw( headers_decode );
//use Protocol::HTTP2::Trace qw(tracer);

# Streams related part of Protocol::HTTP2::Connection

    /** @var int */ public $goaway;
    /** @var array[] */ public $streams;
    /** @var int stream ID */ public $last_stream;
    /** @var int client / server */ public $type;
    /** @var int stream ID */ public $last_peer_stream;
    /** @var array */ public $decode_ctx;

    function new_stream() {
        if ($this->goaway) return null;

        if (isset($this->streams[$this->type == Constants::CLIENT ? 1 : 2]))
            $this->last_stream += 2;

        $this->streams[$this->last_stream] = [
            'state' => Constants::IDLE,
            'weight' => Constants::DEFAULT_WEIGHT,
            'stream_dep' => 0,
            'fcw_recv' => $this->enc_setting(Constants::SETTINGS_INITIAL_WINDOW_SIZE),
            'fcw_send' => $this->enc_setting(Constants::SETTINGS_INITIAL_WINDOW_SIZE),
        ];
        return $this->last_stream;
    }

    function new_peer_stream($stream_id) {
        if ($stream_id < $this->last_peer_stream
        || ($stream_id % 2) == ($this->type == Constants::CLIENT) ? 1 : 0
            || $this->goaway
        ) {
            return null;
        }
        $this->last_peer_stream = $stream_id;
        $this->streams[$stream_id] = [
            'state' => Constants::IDLE,
            'weight' => Constants::DEFAULT_WEIGHT,
            'stream_dep' => 0,
            'fcw_recv' => $this->dec_setting(Constants::SETTINGS_INITIAL_WINDOW_SIZE),
            'fcw_send' => $this->dec_setting(Constants::SETTINGS_INITIAL_WINDOW_SIZE),
        ];
        if (isset($this->on_new_peer_stream)) call_user_func($this->on_new_peer_stream, $stream_id);

        return $this->last_peer_stream;
    }

    function stream($stream_id) {
        if (!isset($this->streams[$stream_id])) return null;

        return $this->streams[$stream_id];
    }

    # stream_state ( $this, $stream_id, $new_state?, $pending? )

    function stream_state($stream_id, $new_state = null, $pending = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) {

            if ($pending) {
                $this->stream_pending_state($stream_id, $new_state);
            } else {
                if (isset($this->on_change_state)) call_user_func($this->on_change_state, $stream_id, $s['state'], $new_state);

                $s['state'] = $new_state;

                # Exec callbacks for new state
                if (isset($s['cb']) && isset($s['cb'][$new_state])) {
                    foreach ($s['cb'][$new_state] as $cb) {
                        call_user_func($cb);
                    }
                }

                # Cleanup
                if ($new_state == Constants::CLOSED) {
                    foreach (array_keys($s) as $key) {
                        if (in_array($key, ['state', 'weight', 'stream_dep', 'fcw_recv', 'fcw_send'])) continue;
                        unset($s[$key]);
                    }
                }
            }
        }

        return $s['state'];
    }

    function stream_pending_state($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) $s['pending_state'] = $value;

        return $s['pending_state'];
    }

    function stream_promised_sid($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) $s['promised_sid'] = $value;

        return $s['promised_sid'];
    }

    function stream_cb($stream_id, $state, $cb) {
        if (!isset($this->streams[$stream_id])) return null;

        $this->streams[$stream_id]['cb'][$state][] = $cb;
    }

    function stream_data($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) $s['data'] = $value;

        return $s['data'];
    }

    # Header Block -- The entire set of encoded header field representations
    function stream_header_block($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) $s['header_block'] = $value;

        return $s['header_block'];
    }

    function stream_headers($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) $s['headers'] = $value;

        return $s['headers'];
    }

    function stream_pp_headers($stream_id) {
        if (!isset($this->streams[$stream_id])) return null;
        return $this->streams[$stream_id]['pp_headers'];
    }

    function stream_headers_done($stream_id) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        $res =
            HeaderCompression::headers_decode($this, $s['header_block'], 0,
                strlen($s['header_block']));

        tracer::debug("Headers done for stream $stream_id\n");

        if (!isset($res)) return null;

        # Clear header_block
        $s['header_block'] = '';

        $eh = $this->decode_context['emitted_headers'];

        if ($s['promised_sid']) {
            $this->streams[$s['promised_sid']]['pp_headers'] = $eh;
        } else {
            $s['headers'] = $eh;
        }

        # Clear emitted headers
        $this->decode_context['emitted_headers'] = [];

        return 1;
    }

    # RST_STREAM for stream errors
    function stream_error($stream_id, $error) {
        $this->enqueue(
            $this->frame_encode(Constants::RST_STREAM, 0, $stream_id, $error, ''));
    }

# Flow control windown of stream
    function stream_fcw_send($stream_id, $value = null) {
        if (func_num_args() > 1) {
            return $this->_stream_fcw('send', $stream_id, $value);
        } else {
            return $this->_stream_fcw('send', $stream_id);
        }
    }

    function stream_fcw_recv($stream_id, $value = null) {
        if (func_num_args() > 1) {
            return $this->_stream_fcw('recv', $stream_id, $value);
        } else {
            return $this->_stream_fcw('recv', $stream_id);
        }
    }

    protected function _stream_fcw($dir, $stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 2) {
            $s['fcw_' . $dir] += $value;
            tracer::debug("Stream $stream_id fcw_$dir now is "
                . $s['fcw_' . $dir]
                . "\n");
        }
        return $s['fcw_' . $dir];
    }

    function stream_fcw_update($stream_id) {
        # TODO: check size of data of stream  in memory
        tracer::debug("update fcw recv of stream $stream_id\n");
        $this->stream_fcw_recv($stream_id, Constants::DEFAULT_INITIAL_WINDOW_SIZE);
        $this->enqueue(
            $this->frame_encode(Constants::WINDOW_UPDATE, 0, $stream_id,
                Constants::DEFAULT_INITIAL_WINDOW_SIZE
            )
        );
    }

    function stream_blocked_data($stream_id, $value = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (func_num_args() > 1) {
            $s['blocked_data'] .= $value;
        }
        return $s['blocked_data'];
    }

    function stream_send_blocked($stream_id) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (strlen($s['blocked_data'])
            && $this->stream_fcw_send($stream_id) != 0
        ) {
            $this->send_data($stream_id, '');
        }
    }

    function stream_weight($stream_id, $weight = null) {
        if (!isset($this->streams[$stream_id])) return null;
        $s =& $this->streams[$stream_id];

        if (isset($weight)) $s['weight'] = $weight;

        return $s['weight'];
    }

    function stream_reprio($stream_id, $exclusive, $stream_dep) {
        if (!isset($this->streams[$stream_id])) return null;

        if ($this->streams[$stream_id]['stream_dep'] != $stream_dep) {

            # check if new stream_dep is stream child
            if ($stream_dep != 0) {
                $sid = $stream_dep;
                while ($sid = $this->streams[$sid]['stream_dep']) {
                    if ($sid != $stream_id) continue;

                    # Child take stream dep
                    $this->streams[$stream_dep]['stream_dep'] =
                        $this->streams[$stream_id]['stream_dep'];

                    break;
                }
            }

            # Set new stream dep
            $this->streams[$stream_id]['stream_dep'] = $stream_dep;
        }

        if ($exclusive) {

            # move all siblings to childs
            foreach (array_keys($this->streams) as $sid) {
                if ($this->streams[$sid]['stream_dep'] != $stream_dep
                    || $sid == $stream_id
                ) continue;

                $this->streams[$sid]['stream_dep'] = $stream_id;
            }
        }

        return 1;
    }

}
