<?php
namespace HTTP2Protocol\HTTP2;

class HeaderCompression
{
//package Protocol::HTTP2::HeaderCompression;
//use strict;
//use warnings;
//use Protocol::HTTP2::Huffman;
//use Protocol::HTTP2::StaticTable;
//use Protocol::HTTP2::Constants qw(:errors :settings :limits);
//use Protocol::HTTP2::Trace qw(tracer bin2hex);
//use Exporter qw(import);
//our @EXPORT_OK = qw(int_encode int_decode str_encode self::str_decode headers_decode
//  headers_encode);

    static $draft = "12";

    static function int_encode($int, $N) {
        $N = $N || 7;
        $ff = ( 1 << $N ) - 1;

        if ( $int < $ff ) {
            return pack('C', $int);
        }

        $res = pack('C', $ff);
        $int -= $ff;

        while ( $int >= 0x80 ) {
            $res .= pack( 'C', ( $int & 0x7f ) | 0x80 );
            $int >>= 7;
        }

        return $res . pack( 'C', $int );
    }

    # int_decode()
    #
    # arguments:
    #   buf_ref    - ref to buffer with encoded data
    #   buf_offset - offset in buffer
    #   int_ref    - ref to scalar where result will be stored
    #   N          - bits in first byte
    #
    # returns: count of readed bytes of encoded integer
    #          or undef on error (malformed data)

    static function int_decode( &$buf_ref, $buf_offset, &$int_ref, $N ) {
        if (strlen($buf_ref) - $buf_offset <= 0) return null;
        $N = $N || 7;
        $ff = ( 1 << $N ) - 1;

        $int_ref = $ff & ord( $buf_ref{$buf_offset} );
        if ($int_ref < $ff) return 1;

        $l = strlen($buf_ref) - $buf_offset - 1;

        for ($i = 1; $i <= $l; ++$i) {
            if ($i > Constants::MAX_INT_SIZE) return null;

            $s = ord( $buf_ref{ $i + $buf_offset } );
            $int_ref += ( $s & 0x7f ) << ( $i - 1 ) * 7;
            if ($s < 0x80) return $i + 1;
        }

        return null;
    }

    static function str_encode($str) {
        $huff_str = Huffman::huffman_encode($str);
        if ( strlen($huff_str) < strlen($str) ) {
            $pack = self::int_encode( strlen($huff_str), 7 );

//            vec( $pack, 7, 1 ) = 1;
            $n = strlen($pack) - 1;
            $pack{$n} = chr(ord($pack{$n})|0x01);

            $pack .= $huff_str;
        }
        else {
            $pack = self::int_encode( strlen($str), 7 );
            $pack .= $str;
        }
        return $pack;
    }

    # self::str_decode()
    # arguments:
    #   buf_ref    - ref to buffer with encoded data
    #   buf_offset - offset in buffer
    #   str_ref    - ref to scalar where result will be stored
    # returns: count of readed bytes of encoded data

    static function str_decode(&$buf_ref, $buf_offset, &$str_ref ) {
        $offset = self::int_decode( $buf_ref, $buf_offset, $l, 7 );

        if (!isset($offset)
          && strlen($buf_ref) - $buf_offset - $offset >= $l)
            return null;

        $str_ref = substr($buf_ref, $offset + $buf_offset, $l);
        if ((ord($buf_ref{$buf_offset}) & 0x01) == 1) {
            $str_ref = Huffman::huffman_decode($str_ref);
        }
        return $offset + $l;
    }

    static function evict_ht($context, $size) {
        $evicted = [];

        $ht = $context->header_table;

        while ( $context->ht_size + $size >
            $context->settings[Constants::SETTINGS_HEADER_TABLE_SIZE] )
        {
            $n      = count($ht);
            $kv_ref = array_pop($ht);
            $context->ht_size -=
              32 + strlen( $kv_ref[0] ) + strlen( $kv_ref[1] );

            tracer::debug( sprintf("Evicted header [%i] %s = %s\n",
                $n + 1, $kv_ref ));
            array_push($evicted, [ $n, $kv_ref ]);
        }
        return $evicted;
    }

    static function add_to_ht($context, $key, $value) {
        $size = strlen($key) + strlen($value) + 32;
        if ($size > $context->settings[Constants::SETTINGS_HEADER_TABLE_SIZE]) return [];

        $evicted = self::evict_ht( $context, $size );

        $ht = $context->header_table;
        $kv_ref = [ $key, $value ];

        array_unshift($ht, $kv_ref);
        $context->ht_size += $size;
        return $evicted;
    }

    /**
     * @param Connection $con
     * @param $buf_ref
     * @param $buf_offset
     * @param $length
     * @return int|null
     */
    static function headers_decode($con, $buf_ref, $buf_offset, $length) {
        $context = $con->decode_context;

        $ht = $context['header_table'];
        $eh = $context['emitted_headers'];

        $offset = 0;

        while ( $offset < $length ) {

            $f = ord( $buf_ref{$buf_offset + $offset} );
            tracer::debug("\toffset: $offset\n");

            # Indexed Header
            if ( $f & 0x80 ) {
                $size =
                  self::int_decode( $buf_ref, $buf_offset + $offset, $index, 7 );
                if (!$size) return $offset;

                # DECODING ERROR
                if ( $index == 0 ) {
                    tracer::error("Indexed header with zero index\n");
                    $con->error(Constants::COMPRESSION_ERROR);
                    return null;
                }

                tracer::debug("\tINDEXED($index) HEADER\t");

                # Static table or Header Table entry
                if ( $index <= count(StaticTable::$stable) ) {
                    list( $key, $value ) = StaticTable::$stable[ $index - 1 ];
                    array_push($eh, $key, $value);
                    tracer::debug("$key = $value\n");
                }
                elseif ( $index > count(StaticTable::$stable) + count($ht) ) {
                    tracer::error(
                            "Indexed header with index out of header table: "
                          . $index
                          . "\n" );
                    $con->error(Constants::COMPRESSION_ERROR);
                    return null;
                }
                else {
                    $kv_ref = $ht[ $index - count(StaticTable::$stable) - 1 ];

                    array_push($eh, $kv_ref);
                    tracer::debug("$kv_ref->[0] = $kv_ref->[1]\n");
                }

                $offset += $size;
            }

            # Literal Header Field - New Name
            elseif ( $f == 0x40 || $f == 0x00 || $f == 0x10 ) {
                $key_size =
                  self::str_decode( $buf_ref, $buf_offset + $offset + 1, $key );
                if (!$key_size) return $offset;

                $value_size =
                  self::str_decode( $buf_ref, $buf_offset + $offset + 1 + $key_size,
                    $value );
                if (!$value_size) return $offset;

                # Emitting header
                array_push($eh, $key, $value);

                # Add to index
                if ( $f == 0x40 ) {
                    self::add_to_ht( $context, $key, $value );
                }
                tracer::debug( sprintf("\tLITERAL(new) HEADER\t%s: %s\n",
                    $key, substr( $value, 0, 30 ) ) );

                $offset += 1 + $key_size + $value_size;
            }

            # Literal Header Field - Indexed Name
            elseif (( $f & 0xC0 ) == 0x40
                || ( $f & 0xF0 ) == 0x00
                || ( $f & 0xF0 ) == 0x10 )
            {
                $size = self::int_decode( $buf_ref, $buf_offset + $offset,
                    $index, ( $f & 0xC0 ) == 0x40 ? 6 : 4 );
                if (!$size) return $offset;

                $value_size =
                  self::str_decode( $buf_ref, $buf_offset + $offset + $size, $value );
                if (!$value_size) return $offset;

                if ( $index <= count(StaticTable::$stable) ) {
                    $key = StaticTable::$stable[ $index - 1 ][0];
                }
                elseif ( $index > count(StaticTable::$stable) + count($ht) ) {
                    tracer::error(
                            "Literal header with index out of header table: "
                          . $index
                          . "\n" );
                    $con->error(Constants::COMPRESSION_ERROR);
                    return null;
                }
                else {
                    $key = $ht[ $index - count(StaticTable::$stable) - 1 ][0];
                }

                # Emitting header
                array_push($eh, $key, $value);

                # Add to index
                if ( ( $f & 0xC0 ) == 0x40 ) {
                    self::add_to_ht( $context, $key, $value );
                }
                tracer::debug("\tLITERAL($index) HEADER\t$key: $value\n");

                $offset += $size + $value_size;
            }

            # Encoding Context Update - Maximum Header Table Size change
            elseif ( ( $f & 0xE0 ) == 0x20 ) {
                $size =
                  self::int_decode( $buf_ref, $buf_offset + $offset, $ht_size, 5 );
                if (!$size) return $offset;

                # It's not possible to increase size of HEADER_TABLE
                if (
                    $ht_size > $context['settings'][Constants::SETTINGS_HEADER_TABLE_SIZE] )
                {
                    tracer::error( "Peer attempt to increase "
                          . "SETTINGS_HEADER_TABLE_SIZE higher than current size: "
                          . "$ht_size > "
                          . $context['settings'][Constants::SETTINGS_HEADER_TABLE_SIZE] );
                    $con->error(Constants::COMPRESSION_ERROR);
                    return null;
                }
                $context['settings'][Constants::SETTINGS_HEADER_TABLE_SIZE] = $ht_size;
                self::evict_ht( $context, 0 );
                $offset += $size;
            }

            # Encoding Error
            else {
                tracer::error( sprintf( "Unknown header type: %08b", $f ) );
                $con->error(Constants::COMPRESSION_ERROR);
                return null;
            }
        }
        return $offset;
    }

    static function headers_encode($context, $headers) {
        if (count($headers) && !isset($headers[0])) {
            $headerList = $headers;
            $headers = [];
            foreach ($headerList as $k => $v) {
                $headers[] = $k;
                $headers[] = $v;
            }
        }

        $res = '';
        $ht  = $context['header_table'];

//      HLOOP:
        for ($n = 0, $N = (count($headers) / 2); $n <= $N; ++$n) {
            $header = strtolower( $headers[ 2 * $n ] );
            $value  = $headers[ 2 * $n + 1 ];

            tracer::debug("Encoding header: $header = $value\n");

            for ($i = 0, $I = count($ht); $i < $I; ++$i) {
                if (!($ht[$i][0] == $header
                  && $ht[$i][1] == $value)) continue;

                $hdr = self::int_encode( $i + StaticTable::$stable + 1, 7 );
                $hdr{strlen($hdr)-1} = chr(ord($hdr{strlen($hdr)-1}) | 0x1);
                $res .= $hdr;
                tracer::debug(
                    "\talready in header table, index " . ( $i + 1 ) . "\n" );

                continue 2; // next HLOOP
            }

            # 7.1 Indexed header field representation
            if ( isset(StaticTable::$rstable[ $header . ' ' . $value ]) ) {
                $hdr = self::int_encode( StaticTable::$rstable[ $header . ' ' . $value ], 7 );
                $hdr{strlen($hdr)-1} = chr(ord($hdr{strlen($hdr)-1}) | 0x1);
                tracer::debug( "\tIndexed header "
                      . StaticTable::$rstable[ $header . ' ' . $value ]
                      . " from table\n" );
            }

            # 7.2.1 Literal Header Field with Incremental Indexing
            # (Indexed Name)
            elseif ( isset(StaticTable::$rstable[ $header . ' ' ]) ) {
                $hdr = self::int_encode( StaticTable::$rstable[ $header . ' ' ], 6 );
//                vec( $hdr, 3, 2 ) = 1;
                $hdr{strlen($hdr)-1} = chr( (ord($hdr{strlen($hdr)-1}) | 0b00001000) & 0b11101111 );
                $hdr .= self::str_encode($value);
                self::add_to_ht( $context, $header, $value );
                tracer::debug( "\tLiteral header "
                      . StaticTable::$rstable{ $header . ' ' }
                      . " indexed name\n" );
            }

            # 7.2.1 Literal Header Field with Incremental Indexing
            # (New Name)
            else {
                $hdr = pack( 'C', 0x40 );
                $hdr .= self::str_encode($header) . self::str_encode($value);
                self::add_to_ht( $context, $header, $value );
                tracer::debug("\tLiteral header new name\n");
            }

            $res .= $hdr;
        }

        return $res;
    }

}
