<?php
namespace HTTP2Protocol\HTTP2;
class Client
{
//package Protocol::HTTP2::Client;
//use strict;
//use warnings;
//use Protocol::HTTP2::Connection;
//use Protocol::HTTP2::Constants qw(:frame_types :flags :states :endpoints
//  :errors);
//use Protocol::HTTP2::Trace qw(tracer);
//use Carp;
//
//=encoding utf-8
//
//=head1 NAME
//
//Protocol::HTTP2::Client - HTTP/2 client
//
//=head1 SYNOPSIS
//
//    use Protocol::HTTP2::Client;
//
//    # Create client object
//    $client = Protocol::HTTP2::Client->new;
//
//    # Prepare first request
//    $client->request(
//
//        # HTTP/2 headers
//        ':scheme'    => 'http',
//        ':authority' => 'localhost:8000',
//        ':path'      => '/',
//        ':method'    => 'GET',
//
//        # HTTP/1.1 headers
//        headers      => [
//            'accept'     => '*/*',
//            'user-agent' => 'perl-Protocol-HTTP2/0.13',
//        ],
//
//        # Callback when receive server's response
//        on_done => function {
//            ( $headers, $data ) = @_;
//            ...
//        },
//    );
//
//    # Protocol::HTTP2 is just HTTP/2 protocol decoder/encoder
//    # so you must create connection yourself
//
//    use AnyEvent;
//    use AnyEvent::Socket;
//    use AnyEvent::Handle;
//    $w = AnyEvent->condvar;
//
//    # Plain-text HTTP/2 connection
//    tcp_connect 'localhost', 8000, function {
//        ($fh) = @_ or die "connection failed: $!\n";
//
//        $handle;
//        $handle = AnyEvent::Handle->new(
//            fh       => $fh,
//            autocork => 1,
//            on_error => function {
//                $_[0]->destroy;
//                print "connection error\n";
//                $w->send;
//            },
//            on_eof => function {
//                $handle->destroy;
//                $w->send;
//            }
//        );
//
//        # First write preface to peer
//        while ( $frame = $client->next_frame ) {
//            $handle->push_write($frame);
//        }
//
//        # Receive servers frames
//        # Reply to server
//        $handle->on_read(
//            function {
//                $handle = shift;
//
//                $client->feed( $handle->{rbuf} );
//
//                $handle->{rbuf} = undef;
//                while ( $frame = $client->next_frame ) {
//                    $handle->push_write($frame);
//                }
//
//                # Terminate connection if all done
//                $handle->push_shutdown if $client->shutdown;
//            }
//        );
//    };
//
//    $w->recv;
//
//=head1 DESCRIPTION
//
//Protocol::HTTP2::Client is HTTP/2 client library. It's intended to make
//http2-client implementations on top of your favorite event-loop.
//
//=head2 METHODS
//
//=head3 new
//
//Initialize new client object
//
//    $client = Procotol::HTTP2::Client->new( %options );
//
//Availiable options:
//
//=over
//
//=item on_push => function {...}
//
//If server send push promise this callback will be invoked
//
//    on_push => function {
//        # received PUSH PROMISE headers
//        $pp_header = shift;
//        ...
//
//        # if we want reject this push
//        # return undef
//
//        # if we want to accept pushed resource
//        # return callback to receive data
//        return function {
//            ( $headers, $data ) = @_;
//            ...
//        }
//    },
//
//=item upgrade => 0|1
//
//Use HTTP/1.1 Upgrade to upgrade protocol from HTTP/1.1 to HTTP/2. Upgrade
//possible only on plain (non-tls) connection.
//
//See
//L<Starting HTTP/2 for "http" URIs|http://tools.ietf.org/html/draft-ietf-httpbis-http2-17#section-3.2>
//
//=item on_error => function {...}
//
//Callback invoked on protocol errors
//
//    on_error => function {
//        $error = shift;
//        ...
//    },
//
//=item on_change_state => function {...}
//
//Callback invoked every time when http/2 streams change their state.
//See
//L<Stream States|http://tools.ietf.org/html/draft-ietf-httpbis-http2-17#section-5.1>
//
//    on_change_state => function {
//        ( $stream_id, $previous_state, $current_state ) = @_;
//        ...
//    },
//
//=back
//
//=cut

    /** @var Connection */
    public $con;
    public $input;
    public $active_streams;
    public $settings;
    public $sent_upgrade;

    function __construct($opts) {
        $this->con = null;
        $this->input = '';
        $this->active_streams = 0;
        $this->settings = isset($opts['settings']) ? $opts['settings'] : [];

        if (isset($opts['on_push'])) {
            $cb = $opts['on_push'];
            unset($opts['on_push']);

            $this__ = $this;
            $opts['on_new_peer_stream'] = function ($stream_id) use ($this__, $cb) {
                $this__->active_streams(+1);

                $this__->con->stream_cb(
                    $stream_id,
                    Constants::RESERVED,
                    function () use ($this__, $cb, $stream_id) {
                        $res = call_user_func($cb,
                            $this__->con->stream_pp_headers($stream_id));

                        if (is_callable($res)) {
//                        if ( $res && is_callable($cb) ) {
//                        if ( $res && ref $cb == 'CODE' ) {
                            $this__->con->stream_cb(
                                $stream_id,
                                Constants::CLOSED,
                                function () use ($this__, $stream_id, $res) {
                                    call_user_func($res,
                                        $this__->con->stream_headers($stream_id),
                                        $this__->con->stream_data($stream_id)
                                    );
                                    $this->active_streams(-1);
                                }
                            );
                        } else {
                            $this->con
                                ->stream_error($stream_id, Constants::REFUSED_STREAM);
                            $this->active_streams(-1);
                        }
                    }
                );
            };
        }

        $this->con = new Connection(Constants::CLIENT, $opts);
    }

    function active_streams($add = 0) {
        $this->active_streams += $add;
        if (!$this->active_streams > 0) $this->con->finish();
    }

//=head3 request
//
//Prepare HTTP/2 request.
//
//    $client->request(
//
//        # HTTP/2 headers
//        ':scheme'    => 'http',
//        ':authority' => 'localhost:8000',
//        ':path'      => '/',
//        ':method'    => 'GET',
//
//        # HTTP/1.1 headers
//        headers      => [
//            'accept'     => '*/*',
//            'user-agent' => 'perl-Protocol-HTTP2/0.06',
//        ],
//
//        # Callback when receive server's response
//        on_done => function {
//            ( $headers, $data ) = @_;
//            ...
//        },
//    );
//
//You can chaining request one by one:
//
//    $client->request( 1-st request )->request( 2-nd request );
//
//=cut
//
//@must = (qw(:authority :method :path :scheme));

    function request($h) {
        $must = [':authority', ':method', ':path', ':scheme'];
        $miss = array_diff($must, array_keys($h));
        if (count($miss)) throw new \RuntimeException("Missing fields in request: " . implode(',', $miss));

        $this->active_streams(+1);

        $con = $this->con;

        $stream_id = $con->new_stream();

        if ($con->upgrade() && !$this->sent_upgrade) {
            $essential_header = array_map(function ($_) use ($h) {
                return $h[$_];
            }, array_combine($must, $must));
            $essential_header['headers'] = isset($h['headers']) ? $h['headers'] : [];

            $con->enqueue($con->upgrade_request($essential_header));
            $this->sent_upgrade = 1;
            $con->stream_state($stream_id, Constants::HALF_CLOSED);
        } else {
            if (!$con->preface) {
                $con->enqueue($con->preface_encode(),
                    $con->frame_encode(Constants::SETTINGS, 0, 0, $this->settings));
                $con->preface(1);
            }

            $essential_header = array_map(function ($_) use ($h) {
                return $h[$_];
            }, array_combine($must, $must));
            $essential_header['headers'] = isset($h['headers']) ? $h['headers'] : [];

            $con->send_headers(
                $stream_id,
                $essential_header,
                1
            );
        }

        if (isset($h['on_done'])) $con->stream_cb(
            $stream_id,
            Constants::CLOSED,
            function () use ($con, $stream_id, $h) {
                call_user_func($h['on_done'],
                    $con->stream_headers($stream_id),
                    $con->stream_data($stream_id)
                );
                $this->active_streams(-1);
            }
        );

        return $this;
    }

//=head3 shutdown
//
//Get connection status:
//
//=over
//
//=item 0 - active
//
//=item 1 - closed (you can terminate connection)
//
//=back
//
//=cut

    function shutdown() {
        return $this->con->shutdown();
    }

//=head3 next_frame
//
//get next frame to send over connection to server.
//Returns:
//
//=over
//
//=item undef - on error
//
//=item 0 - nothing to send
//
//=item binary string - encoded frame
//
//=back
//
//    # Example
//    while ( $frame = $client->next_frame ) {
//        syswrite $fh, $frame;
//    }
//
//=cut

    function next_frame() {
        $frame = $this->con->dequeue();
        if ($frame) tracer::debug("send one frame to wire\n");
        return $frame;
    }

//=head3 feed
//
//Feed decoder with chunks of server's response
//
//    sysread $fh, $binary_data, 4096;
//    $client->feed($binary_data);
//
//=cut

    function feed($chunk) {
        $this->input .= $chunk;
        $offset = 0;
        $con = $this->con;
        tracer::debug("got " . strlen($chunk) . " bytes on a wire\n");
        if ($con->upgrade()) {
            $len = $con->decode_upgrade_response($this->input, $offset);
            if (!isset($len)) $con->shutdown(1);
            if (!$len) return;
            $offset += $len;
            $con->upgrade(0);
            $con->enqueue($con->preface_encode());
            $con->preface(1);
        }
        while ($len = $con->frame_decode($this->input, $offset)) {
            tracer::debug("decoded frame at $offset, length $len\n");
            $offset += $len;
        }
        if ($offset) perl_substr4($this->input, 0, $offset, '');
    }

}
