<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Overrides key() with a camelCase key, the likeliest way to miss the kebab-case rule. Its
 * HOOK_SUFFIX is fine; only the key the framework would store in content is not.
 */
class InvalidKeyAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'invalid-key-animation-tester';

    public function key(): string
    {
        return 'fadeUp';
    }

    public function label(): string
    {
        return 'Invalid Key Animation Tester';
    }
}
