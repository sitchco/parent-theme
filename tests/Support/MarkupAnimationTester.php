<?php

namespace Sitchco\Parent\Tests\Support;

use Sitchco\Parent\Modules\Animation\AnimationModule;

/**
 * An animation that brings markup of its own, hosted by an inner element, as the letter
 * animation's glyph is. The other fakes declare none, which covers the default.
 */
class MarkupAnimationTester extends AnimationModule
{
    public const HOOK_SUFFIX = 'markup-tester';

    public const MARKUP = '<span class="glyph" aria-hidden="true"></span>';

    public function label(): string
    {
        return 'Markup Tester';
    }

    public function markup(): ?string
    {
        return self::MARKUP;
    }

    public function markupHosts(): array
    {
        return ['inner', 'wrap'];
    }
}
