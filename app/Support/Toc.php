<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Builds an "On this page" list from the <h2> headings of rendered article HTML,
 * adding stable ids so the links work without JavaScript.
 */
class Toc
{
    /** @return array{html: string, items: array<int, array{id: string, text: string}>} */
    public static function inject(string $html): array
    {
        $items = [];

        $html = (string) preg_replace_callback('/<h2(\s[^>]*)?>(.*?)<\/h2>/s', function (array $m) use (&$items): string {
            $text = trim(html_entity_decode(strip_tags($m[2])));
            $id = Str::slug($text) ?: 'section';
            $suffix = 1;
            $base = $id;
            while (in_array($id, array_column($items, 'id'), true)) {
                $id = $base.'-'.(++$suffix);
            }
            $items[] = ['id' => $id, 'text' => $text];

            return '<h2 id="'.$id.'"'.$m[1].'>'.$m[2].'</h2>';
        }, $html);

        return ['html' => $html, 'items' => $items];
    }
}
