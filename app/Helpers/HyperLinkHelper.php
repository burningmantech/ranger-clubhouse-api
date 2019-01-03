<?php

namespace App\Helpers;

use Carbon\Carbon;

class HyperLinkHelper
{
    public static function text($text): string
    {
        // convert urls to links
        $text = preg_replace("/\b(https?\:\/\/[^\s]+)\b/", '<a href="\1" target="_blank">\1</a>', $text);

        // convert email addresses to mailto links
        $text = preg_replace('/\b([a-z0-9\._%+-]+@[a-z0-9\.-]+\.[a-z]{2,})\b/i', '<a href="mailto:\1">\1</a>', $text);
        return $text;
    }
}
