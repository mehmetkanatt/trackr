<?php

namespace App\util;

class StringUtil
{
    public static function getDecimalHashtags($text) : array
    {
        preg_match_all('/\B#\K([0-9]+)\b/', $text, $matches);
        return $matches[0];
    }

    public static function getAlphaNumericHashtags($text) : array
    {
        preg_match_all('/\B[#]\K[a-zA-Z0-9]+/', $text, $matches);
        return $matches[0];
    }
}