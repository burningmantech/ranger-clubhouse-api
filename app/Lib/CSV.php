<?php

namespace App\Lib;

class CSV
{
    /**
     * Write out a CSV row, quoting string columns
     * (Why the f**k doesn't fputcsv() do this!?!?)
     *
     * @param $fh
     * @param array $columns
     */

    public static function write($fh, array $columns): void
    {
        $quoted = array_map(function ($c) {
            if (!is_numeric($c) && empty($c)) {
                return '';
            }
            return is_string($c) ? '"' . str_replace('"', '""', $c) . '"' : $c;
        }, $columns);
        fwrite($fh, implode(',', $quoted) . PHP_EOL);
    }
}