<?php

declare(strict_types=1);

namespace App\Support\Discovery;

use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * A small CSS selector subset compiled to XPath, enough to point the crawler
 * at a field on a directory page:
 *
 *   tag  .class  #id  [attr]  [attr=value]  [attr*=value]  [attr^=value]  [attr$=value]
 *   descendant ("a b") and child ("a > b") combinators, "a, b" alternatives,
 *   and a trailing "@attr" to read an attribute instead of the text.
 *
 * Example: "div.contact a[href^=tel]@href"
 */
final class Selector
{
    /**
     * @return list<string> matched values (text or attribute), trimmed and non-empty
     */
    public static function values(DOMXPath $xpath, string $selector, int $limit = 10): array
    {
        [$css, $attribute] = self::split($selector);
        $query = self::toXPath($css);
        if ($query === null) {
            return [];
        }

        $nodes = @$xpath->query($query);
        if ($nodes === false) {
            return [];
        }

        $out = [];
        foreach ($nodes as $node) {
            $value = $attribute !== null
                ? ($node instanceof DOMElement ? $node->getAttribute($attribute) : '')
                : self::text($node);
            $value = trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $value));
            if ($value !== '') {
                $out[] = $value;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Visible text of a node with block elements and <br> turned into line breaks. */
    public static function text(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'noscript', 'template'], true)) {
                    continue;
                }
                $inner = self::text($child);
                $text .= in_array($tag, ['br', 'p', 'div', 'li', 'tr', 'h1', 'h2', 'h3', 'h4', 'dd', 'dt'], true) ? "\n".$inner."\n" : $inner;
            } else {
                $text .= $child->textContent;
            }
        }
        if (! $node->hasChildNodes()) {
            $text = $node->textContent;
        }

        return trim((string) preg_replace("/\s*\n\s*/u", "\n", $text));
    }

    /** @return array{0: string, 1: ?string} [css, attribute] */
    private static function split(string $selector): array
    {
        $selector = trim($selector);
        if (preg_match('/^(.*?)@([a-zA-Z_:][\w:.-]*)$/', $selector, $m) && ! str_ends_with(trim($m[1]), '[')) {
            return [trim($m[1]), $m[2]];
        }

        return [$selector, null];
    }

    public static function toXPath(string $css): ?string
    {
        $alternatives = [];
        foreach (self::splitOutside($css, ',') as $part) {
            $xpath = self::compileOne(trim($part));
            if ($xpath === null) {
                return null;
            }
            $alternatives[] = $xpath;
        }

        return $alternatives ? implode(' | ', $alternatives) : null;
    }

    private static function compileOne(string $css): ?string
    {
        if ($css === '') {
            return null;
        }
        $css = (string) preg_replace('/\s*>\s*/', ' > ', $css);
        $tokens = self::splitOutside($css, ' ');

        $xpath = '';
        $axis = '//';
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if ($token === '>') {
                $axis = '/';

                continue;
            }
            $step = self::compound($token);
            if ($step === null) {
                return null;
            }
            $xpath .= $axis.$step;
            $axis = '//';
        }

        return $xpath === '' ? null : $xpath;
    }

    private static function compound(string $token): ?string
    {
        if (! preg_match('/^([a-zA-Z][a-zA-Z0-9-]*|\*)?((?:[#.][\w-]+|\[[^\]]+\])*)$/u', $token, $m)) {
            return null;
        }
        $tag = $m[1] !== '' ? strtolower($m[1]) : '*';
        $conditions = [];

        preg_match_all('/([#.])([\w-]+)|\[\s*([\w:-]+)\s*(?:([*^$]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]]*?)))?\s*\]/u', $m[2], $parts, PREG_SET_ORDER);
        foreach ($parts as $p) {
            if (($p[1] ?? '') === '#') {
                $conditions[] = '@id='.self::literal($p[2]);
            } elseif (($p[1] ?? '') === '.') {
                $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ".self::literal(' '.$p[2].' ').')';
            } elseif (! empty($p[3])) {
                $attr = '@'.$p[3];
                $op = $p[4] ?? '';
                $value = ($p[5] ?? '') !== '' ? $p[5] : (($p[6] ?? '') !== '' ? $p[6] : trim($p[7] ?? ''));
                $lit = self::literal($value);
                $conditions[] = match ($op) {
                    '' => $attr,
                    '=' => $attr.'='.$lit,
                    '*=' => "contains($attr, $lit)",
                    '^=' => "starts-with($attr, $lit)",
                    '$=' => "substring($attr, string-length($attr) - string-length($lit) + 1) = $lit",
                    default => $attr,
                };
            }
        }

        return $tag.implode('', array_map(fn ($c) => '['.$c.']', $conditions));
    }

    private static function literal(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }
        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        return "concat('".str_replace("'", "', \"'\", '", $value)."')";
    }

    /** @return list<string> */
    private static function splitOutside(string $text, string $separator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        foreach (mb_str_split($text) as $char) {
            if ($quote !== null) {
                $quote = $char === $quote ? null : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }
}
