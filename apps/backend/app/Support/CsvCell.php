<?php

namespace App\Support;

class CsvCell
{
    public static function text($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^(\s*[=+\-@]|[\t\r\n])/u', $value) ? "'".$value : $value;
    }
}
