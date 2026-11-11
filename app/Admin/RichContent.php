<?php

namespace App\Admin;

use Symfony\Component\HtmlSanitizer\{HtmlSanitizer, HtmlSanitizerConfig};

class RichContent
{
    /** Preserve editor alignment without allowing arbitrary CSS or active HTML. */
    public static function sanitize(?string $html): string
    {
        $config = (new HtmlSanitizerConfig())->allowSafeElements()->allowRelativeLinks()->allowRelativeMedias()
            ->allowAttribute('style', ['p', 'h2', 'h3'])->allowElement('sub')->allowElement('sup');
        $safe = (new HtmlSanitizer($config))->sanitize($html ?? '');
        if ($safe === '') return '';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8"><html><body>'.$safe.'</body></html>', LIBXML_NONET);
            foreach ((new \DOMXPath($dom))->query('//*[@style]') as $node) {
                $style = $node->getAttribute('style');
                $node->removeAttribute('style');
                if (preg_match('/(?:^|;)\s*text-align\s*:\s*(left|center|right|start|end|justify)\s*(?:;|$)/i', $style, $match)) $node->setAttribute('style', 'text-align: '.strtolower($match[1]));
            }
            $result = '';
            foreach ($dom->getElementsByTagName('body')->item(0)->childNodes as $child) $result .= $dom->saveHTML($child);
            return $result;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    }
}
