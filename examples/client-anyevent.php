<?php
use \HTTP2Protocol\HTTP2\Client;
use \HTTP2Protocol\HTTP2\Constants;
use \HTTP2Protocol\HTTP2\tracer;

require_once __DIR__ . '/__init.php';

$hostname = '127.0.0.1';
$port = 8000;

if (preg_match('/^\d+\.\d+\.\d+\.\d+$/D',$hostname)) {
    $host = $hostname;
} else {
    $host = gethostbyname($hostname);
}

//function socket_read_nonblock($socket, $chunk_size = 4096) {
//    $E_WOULD_BLOCK = 11;
//    $E_IN_PROGRESS = 115;
//
//    $buf = '';
//    $done = false;
//    do {
//        $chunk = socket_read($socket, $chunk_size);
//        if ($chunk === false) {
//            $error = socket_last_error($socket);
//            if ($error != $E_WOULD_BLOCK && $error != $E_IN_PROGRESS) {
//                die("Socket error $error: " . socket_strerror($error));
////                $done = true;
//            }
//            break;
//        } elseif ($chunk == '') {
//            $done = true;
//        } else {
//            $buf .= $chunk;
//        }
//    } while (!$done);
//
//    return $buf;
//}
//

$client = new Client([
    'on_change_state' => function($stream_id, $previous_state, $current_state) {
        printf("Stream %i changed state from %s to %s\n",
          $stream_id, Constants::const_name( "states", $previous_state ),
          Constants::const_name( "states", $current_state ));
    },
    'on_error' => function($error) {
        printf("Error occured: %s\n", Constants::const_name( "errors", $error ));
    },

    # Perform HTTP/1.1 Upgrade
    'upgrade' => 1,
]);

# Prepare http/2 request
$client->request([
    ':scheme'    => "http",
    ':authority' => $host . ":" . $port,
    ':path'      => "/",
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

//$w = AnyEvent::condvar();

//$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
//if ($socket === false) die("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
//
//$result = socket_connect($socket, $host, $port);
//if ($result === false) die("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n");
//
$socket_url = 'tcp://' . $host . ':' . $port;
$stream = stream_socket_client($socket_url, $error, $errorString, 2,
    STREAM_CLIENT_ASYNC_CONNECT);

if ($stream === false) die("stream_socket_client() failed for $socket_url.\nReason: ($error) $errorString\n");


//tcp_connect($host, $port, function($fh)use($w,$client) {
//    if (!$fh) die("connection failed: $!");

//    $handle = new AnyEvent\Handle([
//        'fh'       => $fh,
//        'autocork' => 1,
//        'on_error' => function($handle,$w) {
//            $handle->destroy();
//            print "connection error\n";
//            $w->send();
//        },
//        'on_eof' => function()use(&$handle,$w) {
//            $handle->destroy();
//            $w->send();
//        }
//    ]);

    # First write preface to peer
function print_safe($frame, $keep_newlines = true) {
    if ($keep_newlines) {
        return addcslashes($frame, "\x00..\x09\x11\x12\x14..\x1F\x7F..\xFF");
    } else {
        return addcslashes($frame, "\x00..\x1F\x7F..\xFF");
    }
}

while ( $frame = $client->next_frame() ) {
        tracer::notice("Sending client preface (".strlen($frame)." bytes)");
        tracer::debug("FRAME: [". print_safe($frame) .']');
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

            while ( $frame = $client->next_frame() ) {
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
//});

//$w->recv();

