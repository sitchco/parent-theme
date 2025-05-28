<?php

namespace Sitchco\Parent\Support;

use JsonException;

/**
 * Class JsonManifestnamespace SitchcoParentModulesSiteHeader;
 */
class JsonManifest
{
    private mixed $manifest;

    /**
     * JsonManifest constructor.
     *
     * @param string $manifest_path The path to the JSON manifest file.
     * @throws JsonException
     */
    public function __construct(string $manifest_path)
    {
        if (file_exists($manifest_path)) {
            $this->manifest = json_decode(file_get_contents($manifest_path), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $this->manifest = [];
        }
    }

    /**
     * Get the entire manifest data.
     *
     * @return array The manifest data as an associative array.
     */
    public function get(): array
    {
        return $this->manifest;
    }

    /**
     * Get a specific value from the manifest by key.
     *
     * @param string $key The key to retrieve from the manifest. Can be a dot-separated string for nested keys.
     * @param mixed $default The default value to return if the key is not found.
     *
     * @return mixed The value associated with the key, or the default value if the key is not found.
     */
    public function getPath(string $key = '', mixed $default = null): mixed
    {
        $collection = $this->manifest;

        if (empty($key)) {
            return $collection;
        }

        if (isset($collection[$key])) {
            return $collection[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!isset($collection[$segment])) {
                return $default;
            }
            $collection = $collection[$segment];
        }

        return $collection;
    }
}