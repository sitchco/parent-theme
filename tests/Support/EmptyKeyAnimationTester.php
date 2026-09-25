<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * Overrides key() to nothing. HOOK_SUFFIX cannot be empty — ModuleRegistry rejects that outright —
 * so an override is the only way an animation reaches discovery without a key, and the framework
 * still has to turn it away out loud.
 */
class EmptyKeyAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'empty-key-animation-tester';

    public function key(): string
    {
        return '';
    }

    public function label(): string
    {
        return 'Empty Key Animation Tester';
    }
}
