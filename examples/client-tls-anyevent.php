<?php
use \HTTP2Protocol\HTTP2;
use \HTTP2Protocol\HTTP2\Client;
use \HTTP2Protocol\HTTP2\Constants;
use \HTTP2Protocol\HTTP2\tracer;

require_once __DIR__ . '/__init.php';

$hostname = 'http2.golang.org';
$port = 443;

if (preg_match('/^\d+\.\d+\.\d+\.\d+$/D',$hostname)) {
    $host = $hostname;
} else {
    $host = gethostbyname($hostname);
}

$client = new Client([
    'on_change_state' => function($stream_id, $previous_state, $current_state) {
        printf("Stream %i changed state from %s to %s\n",
          $stream_id, Constants::const_name( "states", $previous_state ),
          Constants::const_name( "states", $current_state ));
    },
    'on_push' => function($push_headers) {
        # If we accept PUSH_PROMISE
        # return callback to receive promised data
        # return undef otherwise
        print "Server want to push some resource to us\n";

        return function($headers, $data) {
            print "Received promised resource\n";
        };
    },
    'on_error' => function ($error) {
        printf("Error occured: %s\n", Constants::const_name( "errors", $error ));
    }
]);

# Prepare http/2 request
$client->request([
    ':scheme'    => "https",
    ':authority' => $hostname . ":" . $port,
    ':path'      => "/reqinfo",
    ':method'    => "GET",
    'headers'      => [
        'accept'     => '*/*',
        'user-agent' => 'PHP-Protocol-HTTP2/0.01',
    ],
    'on_done' => function($headers, $data) {
        printf("Get headers. Count: %i\n", count($headers) / 2);
        printf("Get data.   Length: %i\n", strlen($data));
        print $data;
    },
]);

//$w = AnyEvent->condvar;

//$host = 'gateway.sandbox.push.apple.com';
//$port = 2195;
//$apnsCert = 'apns-dev.pem';
$streamContext = stream_context_create();
//stream_context_set_option($streamContext, 'ssl', 'local_cert', $apnsCert);

stream_context_set_option($streamContext, 'ssl', 'peer_name', $hostname);
stream_context_set_option($streamContext, 'ssl', 'ciphers', 'DEFAULT');
stream_context_set_option($streamContext, 'ssl', 'disable_compression', true);
stream_context_set_option($streamContext, 'ssl', 'SNI_enabled', true);

tracer::notice("Connecting to $socket_url");

$socket_url = 'ssl://' . $hostname . ':' . $port;
$stream = stream_socket_client($socket_url, $error, $errorString, 2,
    STREAM_CLIENT_ASYNC_CONNECT, $streamContext);

if ($stream === false) die("stream_socket_client() failed for $socket_url.\nReason: ($error) $errorString\n");

//tcp_connect $host, $port, sub {
//    my ($fh) = @_ or do {
//        print "connection failed: $!\n";
//        $w->send;
//        return;
//    };

//    my $tls;
//    eval {
//        my $ctx = Net::SSLeay::CTX_tlsv1_new() or die $!;
//        Net::SSLeay::CTX_set_options( $ctx, &Net::SSLeay::OP_ALL );
//
//        # NPN  (Net-SSLeay > 1.45, openssl >= 1.0.1)
//        Net::SSLeay::CTX_set_next_proto_select_cb( $ctx,
//            [Protocol::HTTP2::ident_tls] );
//
//        # ALPN (Net-SSLeay > 1.55, openssl >= 1.0.2)
//        #Net::SSLeay::CTX_set_alpn_protos( $ctx,
//        #    [ Protocol::HTTP2::ident_tls ] );
//        $tls = AnyEvent::TLS->new_from_ssleay($ctx);
//    };
//    if ($@) {
//        TODO print "Some problem with SSL CTX: $@\n";
//        $w->send;
//        return;
//    }

//    my $handle;
//    $handle = AnyEvent::Handle->new(
//        fh       => $fh,
//        tls      => "connect",
//        tls_ctx  => $tls,
//        autocork => 1,
//        on_error => sub {
//            $_[0]->destroy;
//            print "connection error\n";
//            $w->send;
//        },
//        on_eof => sub {
//            $handle->destroy;
//            $w->send;
//        }
//    );

    tracer::notice("Sending client preface ...");

    # First write preface to peer
    while ( $frame = $client->next_frame() ) {
        tracer::notice("Sending client preface (".strlen($frame)." bytes)");
        tracer::debug("FRAME: [".addcslashes($frame, "\x00..\x1F\x7F..\xFF").']');
        fwrite($stream, $frame);
    }

    fflush($stream);

    stream_set_blocking($stream, 0);

    $open = true;
    $waiting = false;

    while ($open) {
        $frame = fread($stream, 102400);

        if (strlen($frame)) {
            tracer::notice("Received server frame (".strlen($frame)." bytes)");
            tracer::debug("FRAME: [".addcslashes($frame, "\x00..\x1F\x7F..\xFF").']');
            $waiting = false;
            $client->feed($frame);

            while ($frame = $client->next_frame()) {
                tracer::notice("Sending client frame (".strlen($frame)." bytes)");
                tracer::debug("FRAME: [".addcslashes($frame, "\x00..\x1F\x7F..\xFF").']');
                fwrite($stream, $frame);
            }
            if ($client->shutdown()) {
                tracer::debug("Closing stream.\n");
                fclose($stream);
                $open = false;
            }
        }

        if (!$waiting) {
            tracer::debug("Waiting...\n");
            $waiting = true;
        }

        // 100ms sleep   1..m..u..ns
        time_nanosleep(0, 100000000);
    }
//};
//
//$w->recv;
