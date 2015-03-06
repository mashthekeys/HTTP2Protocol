<?php
namespace HTTP2Protocol\HTTP2;
class Huffman
{
//package Protocol::HTTP2::Huffman;
//use strict;
//use warnings;
//use Protocol::HTTP2::HuffmanCodes;
//use Protocol::HTTP2::Trace qw(tracer);
//our ( %hcodes, %rhcodes, $hre );
//require Exporter;
//our @ISA    = qw(Exporter);
//our @EXPORT = qw(huffman_encode huffman_decode);

# Memory inefficient algorithm (well suited for short strings)

    static function huffman_encode($s) {
        tracer::debug("\$s: ".var_export($s,1));


        $ret = $bin = '';
        for ($i = 0; $i < strlen($s); ++$i) {
            $bin .= HuffmanCodes::$hcodes[ord($s{$i})];
        }
        if (strlen($bin) % 8) $bin .= substr(HuffmanCodes::$hcodes{256}, 0, 8 - strlen($bin) % 8);

        $packed = PerlCompat::_perl_pack_bytes($bin);

        return $ret . $packed;
    }

    /**
     * Use ^ in $regex in place of Perl's \G
     * @param $regex
     * @param $str
     * @param null $matches
     * @param int $flags
     * @param int $offset
     * @return int
     */
    static function preg_match_global($regex, $str, &$matches = null, $flags = PREG_PATTERN_ORDER, $offset = 0) {
        $SET_ORDER = $flags & PREG_SET_ORDER;
        $matches = [];

        if ($offset) {
            $str = substr($str, $offset);
        }

        while (preg_match($regex, $str, $match, $flags)) {
            $matches[] = $match;
            $len = strlen($match[0]);
            if (!$len) break;
            $str = substr($str, $len);
        }

        $count = count($match);

        if ($SET_ORDER) {
            $pattern_order = $matches;
            $matches = [];
            foreach ($pattern_order as $match) {
                $n = count($matches);
                foreach ($match as $k => $v) {
                    $matches[$k][$n] = $v;
                }
            }
        }

        return $count;
    }

    static function huffman_decode($s) {
//        $bin = unpack('B*', $s);
        $bin = self::perl_unpack_bytes($s);

        $c = 0;

        $matches = [[]];
//        self::preg_match_global('/^' . HuffmanCodes::$hre . '/', $bin, $matches);
        preg_match_all('/' . HuffmanCodes::$hre . '/', $bin, $matches);

        $s = pack('C*', array_map(function ($_) use (&$c) {
            $c += strlen($_);
            return HuffmanCodes::$rhcodes[$_];
        }, $matches[0]));

        if (strlen($bin) - $c > 8) tracer::warning(
            sprintf(
                "malformed data in string at position %i, " . " length: %i",
                $c, strlen($bin)
            )
        );
        if (!preg_match('/^(?:' . HuffmanCodes::$hcodes[256] . ')+$/', substr($bin, $c))) tracer::warning(
            sprintf(
                "no huffman code 256 at the end of encoded string '%s': %s\n",
                substr($s, 0, 30),
                substr($bin, $c)
            )
        );

        return $s;
    }

    /**
     * Parses an ASCII bit string into a binary string.
     *
     * The input must be a multiple of 8 bytes in length,
     * and composed of '0' and '1' characters.
     *
     * The output will be 1/8 the length, and contain the
     * binary string represented by the 0's and 1's.
     *
     * It is equivalent to the following Perl:
     * <code>my $bytes = pack( 'B*', $bits );</code>
     *
     * @param $bits
     * @return string the binary string represented by $bits
     */
    public static function perl_pack_bytes($bits) {
        $remainder = strlen($bits) % 8;
        if ($remainder) $bits .= str_repeat('0',$remainder);
        return self::_perl_pack_bytes($bits);
    }
    /**
     * self::perl_pack_bytes without string length checks, for efficiency
     * in this class, where such checks are already carried out.
     *
     * @param $bits
     * @return string the binary string represented by $bits
     */
    private static function _perl_pack_bytes($bits) {
        $packed = implode('', array_map(function ($_) {
            return (int)"0b$_";
        }, str_split($bits, 8)));
        return $packed;
    }

    private static function _perl_pack_bytes__STATIC($bits) {
        static $TO_BYTES;
        if (!isset($TO_BYTES)) $TO_BYTES = function ($_) {
            return (int)"0b$_";
        };
        $packed = implode('', array_map($TO_BYTES, str_split($bits, 8)));
        return $packed;
    }

    public static function byte2bits($_) {
        return (int)"0b$_";
    }
    private static function _perl_pack_bytes__SELF_METHOD($bits) {
        $packed = implode('', array_map('self::byte2bits', str_split($bits, 8)));
        return $packed;
    }
    private static function _perl_pack_bytes__REF_METHOD($bits) {
        $packed = implode('', array_map('HTTP2Protocol\HTTP2\Huffman::byte2bits', str_split($bits, 8)));
        return $packed;
    }
    public static function _test_speeds($reps = 10000) {
        $binaryData = openssl_random_pseudo_bytes(1024);
        $bits = self::perl_unpack_bytes($binaryData);

        $__binaryData = strlen($binaryData);
        $__bits = strlen($bits);

        echo "<pre>\n";
        echo "Data     $__binaryData bytes\n";
        echo "Unpacked $__bits bits\n";
        echo "--------------------------------\n";

        $METHODS = [
            '_perl_pack_bytes',
            '_perl_pack_bytes__STATIC',
            '_perl_pack_bytes__SELF_METHOD',
            '_perl_pack_bytes__REF_METHOD',
        ];

        foreach ($METHODS as $METHOD) {
            $decoded[$METHOD] = call_user_func(array('HTTP2Protocol\HTTP2\Huffman', $METHOD), $bits);
        }

        $first = reset($decoded);
        if (count(array_filter($decoded,function($_)use($first) {return $_ != $first;}))) {
            echo "Match broken!\n";
            foreach ($decoded as $METHOD => $output) {
                echo "$METHOD: ", strlen($output), "\t\t", md5($output), "\n";
            }
            echo "--------------------------------\n";
        } else {
            echo "All methods match results.\n";
        }

        echo "Timings:\n";
        foreach (array_merge($METHODS,$METHODS) as $METHOD) {
            $start = microtime(true);

            for ($n = 0; $n < $reps; ++$n) {
                $bytes = call_user_func(array('HTTP2Protocol\HTTP2\Huffman', $METHOD), $bits);
                unset($bytes);
            }

            $finish = microtime(true);

            $time = $finish - $start;
            echo "$METHOD: ", number_format($time, 3), "\n";
        }
        echo "--------------------------------\n";
        echo "</pre>\n";

    }

    /**
     * Parses a binary string into an ASCII bit string.
     *
     * The output will be 8 times the length of input,
     * and contain a bit string representing the bits
     * of the binary string.
     *
     * It is equivalent to the following Perl:
     * <code>my $bits = unpack( 'B*', $bytes );</code>
     * @param $bytes
     * @return string the bit string representing the bits of $bytes
     */
    public static function perl_unpack_bytes($bytes) {
        $bits = implode('', array_map(function ($_) {
            return str_pad(decbin(ord($_)), 8, '0', STR_PAD_LEFT);
        }, str_split($bytes)));
        return $bits;
    }

}
