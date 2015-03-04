<?php
namespace HTTP2Protocol\HTTP2;
class Server {
//package Protocol::HTTP2::Server;
//use strict;
//use warnings;
//use Protocol::HTTP2::Connection;
//use Protocol::HTTP2::Constants qw(:frame_types :flags :states :endpoints
//  :settings :limits Constants::const_name);
//use Protocol::HTTP2::Trace qw(tracer);
//use Carp;
//
//=encoding utf-8
//
//=head1 NAME
//
//Protocol::HTTP2::Server - HTTP/2 server
//
//=head1 SYNOPSIS
//
//    use Protocol::HTTP2::Server;
//
//    # You must create tcp server yourself
//    use AnyEvent;
//    use AnyEvent::Socket;
//    use AnyEvent::Handle;
//
//    $w = AnyEvent->condvar;
//
//    /** @var \HTTP2Protocol\HTTP2\Connection  */
//    public $con;
//    public $input;
//    public $settings;
//
//
//    # Plain-text HTTP/2 connection
//    static function tcp_server($fh, $peer_host='localhost', $peer_port=8000) {
//        $handle = AnyEvent::Handle____new([
//            'fh'       => $fh,
//            'autocork' => 1,
//            'on_error' => function($h){
//                $h->destroy;
//                print "connection error\n";
//            },
//            'on_eof' => function()use(&$handle){
//                $handle->destroy;
//            },
//        ]);
//
//        # Create Protocol::HTTP2::Server object
//        $server = new Server([
//            'on_request' => function($stream_id, $headers, $data)use(&$server) {
//                $message = "hello, world!";
//
//                # Response to client
//                $server->response([
//                    ':status' => 200,
//                    'stream_id' => $stream_id,
//
//                    # HTTP/1.1 Headers
//                    'headers'   => [
//                        'server'         => 'perl-Protocol-HTTP2/0.13',
//                        'content-length' => strlen($message),
//                        'cache-control'  => 'max-age=3600',
//                        'date'           => 'Fri, 18 Apr 2014 07:27:11 GMT',
//                        'last-modified'  => 'Thu, 27 Feb 2014 10:30:37 GMT',
//                    ],
//
//                    # Content
//                    'data' => $message,
//                ]);
//            },
//        ]);
//
//        # First send settings to peer
////        while ( $frame = $server->next_frame ) {
//        while ( $frame = $server->next_frame() ) {
//            $handle->push_write($frame);
//        }
//
//        # Receive clients frames
//        # Reply to client
//        $handle->on_read(
//            function($handle)use($server) {
//
//                $server->feed( $handle->{rbuf} );
//
//                $handle->{rbuf} = undef;
//                while ( $frame = $server->next_frame() ) {
//                    $handle->push_write($frame);
//                }
//                if ($server->shutdown) $handle->push_shutdown();
//            }
//        );
//    }
//
////    $w->recv;



//=head1 DESCRIPTION
//
//Protocol::HTTP2::Server is HTTP/2 server library. It's intended to make
//http2-server implementations on top of your favorite event loop.
//
//See also L<Shuvgey|https://github.com/vlet/Shuvgey> - AnyEvent HTTP/2 Server
//for PSGI based on L<Protocol::HTTP2::Server>.
//
//=head2 METHODS
//
//=head3 new
//
//Initialize new server object
//
//    $server = Procotol::HTTP2::Client->new( %options );
//
//Availiable options:
//
//=over
//
//=item on_request => function {...}
//
//Callback invoked when receiving client's requests
//
//    on_request => function {
//        # Stream ID, headers array reference and body of request
//        ( $stream_id, $headers, $data ) = @_;
//
//        $message = "hello, world!";
//        $server->response([
//            ':status' => 200,
//            'stream_id' => $stream_id,
//            'headers'   => [
//                'server'         => 'perl-Protocol-HTTP2/0.13',
//                'content-length' => strlen($message),
//            ],
//            'data' => $message,
//        ]);
//        ...
//    },

    /*
=item upgrade => 0|1

Use HTTP/1.1 Upgrade to upgrade protocol from HTTP/1.1 to HTTP/2. Upgrade
possible only on plain (non-tls) connection.

See
L<Starting HTTP/2 for "http" URIs|http://tools.ietf.org/html/draft-ietf-httpbis-http2-17#section-3.2>

=item on_error => function {...}

Callback invoked on protocol errors

    on_error => function {
        $error = shift;
        ...
    },

=item on_change_state => function {...}

Callback invoked every time when http/2 streams change their state.
See
L<Stream States|http://tools.ietf.org/html/draft-ietf-httpbis-http2-17#section-5.1>

    on_change_state => function {
        ( $stream_id, $previous_state, $current_state ) = @_;
        ...
    },

=back

=cut
*/
    /** @var  Connection */
    public $con;
    public $input;
    public $settings;

    function __construct( $opts ) {
        $this->con      = null;
        $this->input    = '';
        $this->settings = [
            Constants::SETTINGS_MAX_CONCURRENT_STREAMS => Constants::DEFAULT_MAX_CONCURRENT_STREAMS,
        ];
        if (isset($opts['settings'])) {
            $this->settings += $opts['settings'];

            unset($opts['settings']);
        }

        if ( isset($opts['on_request']) ) {
            $this->cb = $opts['on_request'];
            unset($opts['on_request']);

            $this_ = $this;

            $opts['on_new_peer_stream'] = function($stream_id)use($this_) {
                $this_->con->stream_cb(
                    $stream_id,
                    Constants::HALF_CLOSED,
                    function()use($this_,$stream_id) {
                        call_user_func($this_->cb,
                            $stream_id,
                            $this_->con->stream_headers($stream_id),
                            $this_->con->stream_data($stream_id)
                        );
                    }
                );
              };
        }

        $this->con =
            new Connection( Constants::SERVER, $opts );
    //    $this->con =
    //      Protocol::HTTP2::Connection->new( SERVER, %opts,
    //        settings => $this->{settings} );
        if (!$this->con->upgrade()) $this->con->enqueue(
            $this->con->frame_encode( Constants::SETTINGS, 0, 0, $this->settings ) );

    }
/*
=head3 response

Prepare response

    $message = "hello, world!";
    $server->response(

        # HTTP/2 status
        ':status' => 200,

        # Stream ID
        stream_id => $stream_id,

        # HTTP/1.1 headers
        headers   => [
            'server'         => 'perl-Protocol-HTTP2/0.01',
            'content-length' => length($message),
        ],

        # Body of response
        data => $message,
    );

=cut

@must = (qw(:status));
*/
    function response($h) {
        $must = [':status'];
        $miss = array_diff($must,array_keys($h));
        if (count($miss)) throw new \RuntimeException("Missing headers in response: ".implode(',',$miss));

        $con = $this->con;

        $con->send_headers(
            $h['stream_id'],
            array_map(function($_)use($h){ return $h[$_]; }, $must )
                + (is_array($h['headers'] ? $h['headers'] : [])),
            isset($h['data']) ? 0 : 1
        );
        if (isset($h['data'])) $con->send_data( $h['stream_id'], $h['data'] );

        return $this;
    }
/*
=head3 push

Prepare Push Promise. See
L<Server Push|http://tools.ietf.org/html/draft-ietf-httpbis-http2-17#section-8.2>

    # Example of push inside of on_request callback
    on_request => function {
        ( $stream_id, $headers, $data ) = @_;
        %h = (@$headers);

        # Push promise (must be before response)
        if ( $h{':path'} eq '/index.html' ) {

            # index.html contain styles.css resource, so server can push
            # "/style.css" to client before it request it to increase speed
            # of loading of whole page
            $server->push(
                ':authority' => 'locahost:8000',
                ':method'    => 'GET',
                ':path'      => '/style.css',
                ':scheme'    => 'http',
                stream_id    => $stream_id,
            );
        }

        $server->response(...);
        ...
    }

=cut

@must_pp = (qw(:authority :method :path :scheme));
*/
    function push($h) {
        $must_pp = [':authority',':method',':path',':scheme'];

        $con = $this->con;
        $miss = array_diff($must_pp,array_keys($h));
        if (count($miss)) throw new \RuntimeException("Missing headers in push promise: ".implode(',',$miss));

        if ($h['stream_id'] % 2 == 0)
            throw new \RuntimeException("Can't push on own stream. "
                . "Seems like a recursion in request callback.");

        $promised_sid = $con->new_stream();
        $con->stream_promised_sid( $h['stream_id'], $promised_sid );

        $headers = array_map(function($_)use($h){ return $h[$_]; }, array_combine($must_pp,$must_pp));

        $con->send_pp_headers( $h['stream_id'], $promised_sid, $headers);

        # send promised response after current stream is closed
        $this__ = $this;
        $con->stream_cb(
            $h['stream_id'],
            Constants::CLOSED,
            function()use($this__,$promised_sid,$headers) {
                call_user_func($this__->cb, $promised_sid, $headers );
            }
        );

        return $this;
    }
/*
=head3 shutdown

Get connection status:

=over

=item 0 - active

=item 1 - closed (you can terminate connection)

=back

=cut
*/
    function shutdown() {
        return $this->con->shutdown();
    }
/*
=head3 next_frame

get next frame to send over connection to client.
Returns:

=over

=item undef - on error

=item 0 - nothing to send

=item binary string - encoded frame

=back

    # Example
    while ( $frame = $server->next_frame ) {
        syswrite $fh, $frame;
    }

=cut
*/
    function next_frame() {
        $frame = $this->con->dequeue();
        if ($frame) {
            list( $length, $type, $flags, $stream_id ) =
              $this->con->frame_header_decode( $frame, 0 );
            tracer::debug(
                sprintf("Send one frame to a wire:"
                  . " type(%s), length(%i), flags(%08b), sid(%i)\n",
                Constants::const_name( 'frame_types', $type ), $length, $flags, $stream_id
            ));
        }
        return $frame;
    }
/*
=head3 feed

Feed decoder with chunks of client's request

    sysread $fh, $binary_data, 4096;
    $server->feed($binary_data);

=cut
*/
    function feed($chunk) {
        $this->input .= $chunk;
        $offset = 0;

        $con = $this->con;
        tracer::debug( "got " . strlen($chunk) . " bytes on a wire\n" );

        if ( $con->upgrade() ) {
            $len =
              $con->decode_upgrade_request( $this->input, $offset, $headers );
            if (!isset($len)) $con->shutdown(1);
            if (!$len) return;

            perl_substr4( $this->input, $offset, $len, '');

            $con->enqueue(
                $con->upgrade_response(),
                $con->frame_encode( Constants::SETTINGS, 0, 0,
                    [
                        Constants::SETTINGS_MAX_CONCURRENT_STREAMS =>
                            Constants::DEFAULT_MAX_CONCURRENT_STREAMS
                    ]
                  )

            );
            $con->upgrade(0);

            # The HTTP/1.1 request that is sent prior to upgrade is assigned stream
            # identifier 1 and is assigned default priority values (Section 5.3.5).
            # Stream 1 is implicitly half closed from the client toward the server,
            # since the request is completed as an HTTP/1.1 request.  After
            # commencing the HTTP/2 connection, stream 1 is used for the response.

            $con->new_peer_stream(1);
            $con->stream_headers( 1, $headers );
            $con->stream_state( 1, Constants::HALF_CLOSED );
        }

        if ( !$con->preface() ) {
            if (!($len = $con->preface_decode( $this->input, $offset ))) return;
            tracer::debug("got preface\n");
            $offset += $len;
            $con->preface(1);
        }

        while ( $len = $con->frame_decode( $this->input, $offset ) ) {
            tracer::debug("decoded frame at $offset, length $len\n");
            $offset += $len;
        }
        if ($offset) perl_substr4( $this->input, 0, $offset, '');
    }

}
