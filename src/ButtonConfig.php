<?php

namespace Sitchco\Parent;

use Sitchco\Framework\Module;

/**
 * Button Configuration
 *
 * Manages core/button block style variations.
 *
 * By default, both outline and fill variations are REMOVED.
 * Enable specific variations in theme config to preserve them.
 *
 * @package Sitchco\Parent
 */
class ButtonConfig extends Module
{
    /**
     * Flags to track which variations should be preserved.
     */
    private bool $keepOutline = false;
    private bool $keepFill = false;

    public function init(): void
    {
        // Filter button style variations before block registration
        add_filter('block_type_metadata', [$this, 'filterButtonStyleVariations']);
    }

    /**
     * Feature method: Preserve the outline button variation.
     *
     * Enable in config: ButtonConfig::class => ['outline' => true]
     */
    public function outline(): void
    {
        $this->keepOutline = true;
    }

    /**
     * Feature method: Preserve the fill button variation.
     *
     * Enable in config: ButtonConfig::class => ['fill' => true]
     */
    public function fill(): void
    {
        $this->keepFill = true;
    }

    /**
     * Filter core/button style variations.
     *
     * Removes variations unless their corresponding feature method was called.
     *
     * @param array $metadata Block metadata
     * @return array Modified metadata
     */
    public function filterButtonStyleVariations(array $metadata): array
    {
        if (!isset($metadata['name']) || 'core/button' !== $metadata['name']) {
            return $metadata;
        }

        if (empty($metadata['styles']) || !is_array($metadata['styles'])) {
            return $metadata;
        }

        $metadata['styles'] = array_values(
            array_filter($metadata['styles'], function ($style) {
                if (!is_array($style) || !isset($style['name'])) {
                    return true;
                }

                // Remove outline unless feature was enabled
                if ('outline' === $style['name']) {
                    return $this->keepOutline;
                }

                // Remove fill unless feature was enabled
                if ('fill' === $style['name']) {
                    return $this->keepFill;
                }

                return true;
            }),
        );

        return $metadata;
    }
}
