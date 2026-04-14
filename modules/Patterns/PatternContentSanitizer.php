<?php

namespace Sitchco\Parent\Modules\Patterns;

/**
 * Pattern Content Sanitizer
 *
 * Replaces real text with Lorem Ipsum, images with placeholders,
 * and videos with placeholders in pattern content.
 */
class PatternContentSanitizer
{
    private const PLACEHOLDER_IMAGE = 'https://cdn.sitch.co/rtc/placeholder-image.png';
    private const PLACEHOLDER_VIDEO = 'https://cdn.sitch.co/rtc/placeholder-video.mp4';

    private const LOREM_POOL = [
        'lorem',
        'ipsum',
        'dolor',
        'sit',
        'amet',
        'consectetur',
        'adipiscing',
        'elit',
        'sed',
        'do',
        'eiusmod',
        'tempor',
        'incididunt',
        'ut',
        'labore',
        'et',
        'dolore',
        'magna',
        'aliqua',
        'enim',
        'ad',
        'minim',
        'veniam',
        'quis',
        'nostrud',
        'exercitation',
        'ullamco',
        'laboris',
        'nisi',
        'aliquip',
        'ex',
        'ea',
        'commodo',
        'consequat',
        'duis',
        'aute',
        'irure',
        'in',
        'reprehenderit',
        'voluptate',
        'velit',
        'esse',
        'cillum',
        'fugiat',
        'nulla',
        'pariatur',
        'excepteur',
        'sint',
        'occaecat',
        'cupidatat',
        'non',
        'proident',
        'sunt',
        'culpa',
        'qui',
        'officia',
        'deserunt',
        'mollit',
        'anim',
        'id',
        'est',
        'laborum',
        'cras',
    ];

    /**
     * Sanitize pattern content by replacing text with Lorem Ipsum,
     * images with placeholder, and videos with placeholder.
     */
    public function sanitizePatternContent(string $content): string
    {
        // Step 1: Replace bgImg URLs in block JSON comments
        $content = preg_replace('/"bgImg":"[^"]*"/', '"bgImg":"' . self::PLACEHOLDER_IMAGE . '"', $content);
        $content = preg_replace('/"bgImgID":\d+/', '"bgImgID":0', $content);

        // Step 2: Replace video URLs in block JSON comments
        $content = preg_replace('/"local":"[^"]*"/', '"local":"' . self::PLACEHOLDER_VIDEO . '"', $content);
        $content = preg_replace('/"localID":"[^"]*"/', '"localID":""', $content);
        $content = preg_replace('/"youTube":"[^"]*"/', '"youTube":""', $content);
        $content = preg_replace('/"vimeo":"[^"]*"/', '"vimeo":""', $content);

        // Step 3: Replace <img src="..."> with placeholder image
        $content = preg_replace('/(<img\b[^>]*\bsrc=")[^"]*(")/i', '$1' . self::PLACEHOLDER_IMAGE . '$2', $content);

        // Step 4: Clear alt text on images
        $content = preg_replace('/(<img\b[^>]*\balt=")[^"]*(")/i', '$1$2', $content);

        // Step 5: Replace real href URLs (http/https) with #
        $content = preg_replace('/(<a\b[^>]*\bhref=")https?:\/\/[^"]*(")/i', '$1#$2', $content);

        // Step 6: Remove id attributes from heading tags
        $content = preg_replace('/(<h[1-6]\b[^>]*)\s+id="[^"]*"/i', '$1', $content);

        // Step 7: Remove target and rel attributes from <a> tags
        $content = preg_replace('/(<a\b[^>]*)\s+target="[^"]*"/i', '$1', $content);
        $content = preg_replace('/(<a\b[^>]*)\s+rel="[^"]*"/i', '$1', $content);

        // Step 8: Replace text content in p, li, h1-h6, figcaption
        $content = preg_replace_callback(
            '/(<(?:p|li|h[1-6]|figcaption)\b[^>]*>)(.*?)(<\/(?:p|li|h[1-6]|figcaption)>)/is',
            function ($matches) {
                $openTag = $matches[1];
                $inner = $matches[2];
                $closeTag = $matches[3];

                $wordCount = $this->countWords($inner);
                if ($wordCount === 0) {
                    return $openTag . $inner . $closeTag;
                }

                return $openTag . $this->generateLoremIpsum($wordCount) . $closeTag;
            },
            $content,
        );

        // Step 9: Replace button text in <a class="wp-block-button__link...">
        $content = preg_replace_callback(
            '/(<a\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(<\/a>)/is',
            function ($matches) {
                $openTag = $matches[1];
                $inner = $matches[2];
                $closeTag = $matches[3];

                $wordCount = $this->countWords($inner);
                if ($wordCount === 0) {
                    return $openTag . $inner . $closeTag;
                }

                return $openTag . $this->generateLoremIpsum($wordCount) . $closeTag;
            },
            $content,
        );

        return $content;
    }

    /**
     * Generate deterministic Lorem Ipsum text of the given word count.
     */
    public function generateLoremIpsum(int $wordCount): string
    {
        if ($wordCount <= 0) {
            return '';
        }

        $pool = self::LOREM_POOL;
        $poolSize = count($pool);
        $words = [];

        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = $pool[$i % $poolSize];
        }

        $words[0] = ucfirst($words[0]);

        return implode(' ', $words);
    }

    /**
     * Count words in HTML content, decoding entities and stripping inline tags.
     */
    public function countWords(string $text): int
    {
        // Decode HTML entities first (e.g. &amp; → &)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip inline HTML tags (br, strong, em, span, a, etc.)
        $text = strip_tags($text);

        // Count words
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return str_word_count($text);
    }
}
