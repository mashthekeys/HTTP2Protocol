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

        $packed = implode('',array_map(function($_){return (int)"0b$_";},str_split($bin,8)));

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
        $bin = implode('', array_map(function($_) { return str_pad(decbin(ord($_)),8,'0',STR_PAD_LEFT); }, str_split($s)));

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

}
