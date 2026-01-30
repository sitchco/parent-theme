<?php

namespace Sitchco\Parent\Modules\SyncedPatterns;

/**
 * Parses pattern files and extracts metadata and content.
 */
class PatternParser
{
    /**
     * Default headers to look for in pattern files.
     */
    private const HEADERS = [
        'title' => 'Title',
        'slug' => 'Slug',
        'description' => 'Description',
        'categories' => 'Categories',
        'keywords' => 'Keywords',
        'blockTypes' => 'Block Types',
        'postTypes' => 'Post Types',
        'inserter' => 'Inserter',
        'synced' => 'Synced',
        'syncStrategy' => 'Sync-Strategy',
    ];

    /**
     * Parse a pattern file and return its metadata and content.
     */
    public function parse(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $headers = $this->parseHeaders($filePath);
        $blockContent = $this->extractBlockContent($content);

        return [
            'file' => $filePath,
            'headers' => $headers,
            'content' => $blockContent,
            'hash' => $this->generateHash($blockContent),
        ];
    }

    /**
     * Parse file headers using WordPress's get_file_data function.
     */
    private function parseHeaders(string $filePath): array
    {
        $headers = get_file_data($filePath, self::HEADERS);

        // Parse comma-separated values into arrays
        if (!empty($headers['categories'])) {
            $headers['categories'] = array_map('trim', explode(',', $headers['categories']));
        } else {
            $headers['categories'] = [];
        }

        if (!empty($headers['keywords'])) {
            $headers['keywords'] = array_map('trim', explode(',', $headers['keywords']));
        } else {
            $headers['keywords'] = [];
        }

        if (!empty($headers['blockTypes'])) {
            $headers['blockTypes'] = array_map('trim', explode(',', $headers['blockTypes']));
        } else {
            $headers['blockTypes'] = [];
        }

        if (!empty($headers['postTypes'])) {
            $headers['postTypes'] = array_map('trim', explode(',', $headers['postTypes']));
        } else {
            $headers['postTypes'] = [];
        }

        // Parse boolean values
        $headers['synced'] = $this->parseBoolean($headers['synced'] ?? '');
        $headers['inserter'] = $headers['inserter'] === '' ? true : $this->parseBoolean($headers['inserter']);

        // Default sync strategy
        if (empty($headers['syncStrategy'])) {
            $headers['syncStrategy'] = 'overwrite';
        }

        return $headers;
    }

    /**
     * Extract block content from the pattern file (everything after the PHP closing tag).
     */
    private function extractBlockContent(string $fileContent): string
    {
        // Find the closing PHP tag and get everything after it
        $closingTagPos = strpos($fileContent, '?>');
        if ($closingTagPos === false) {
            return '';
        }

        $content = substr($fileContent, $closingTagPos + 2);
        return trim($content);
    }

    /**
     * Generate a hash of the content for change detection.
     */
    private function generateHash(string $content): string
    {
        return md5($content);
    }

    /**
     * Parse a string as a boolean value.
     */
    private function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));
        return in_array($value, ['true', '1', 'yes'], true);
    }

    /**
     * Check if a pattern should be synced based on its headers.
     */
    public function isSyncedPattern(array $pattern): bool
    {
        return !empty($pattern['headers']['synced']);
    }
}
