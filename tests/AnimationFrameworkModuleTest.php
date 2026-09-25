<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Framework\ModuleRegistry;
use Sitchco\Parent\Modules\Animation\AnimationFrameworkModule;
use Sitchco\Parent\Tests\Support\AnimationTester;
use Sitchco\Parent\Tests\Support\DuplicateAnimationTester;
use Sitchco\Parent\Tests\Support\EmptyKeyAnimationTester;
use Sitchco\Parent\Tests\Support\ModuleTester;
use Sitchco\Parent\Tests\Support\SecondAnimationTester;
use Sitchco\Tests\TestCase;
use Sitchco\Utils\LogLevel;
use Sitchco\Utils\Logger;

class AnimationFrameworkModuleTest extends TestCase
{
    /**
     * Activate exactly these modules on a registry of their own and return a coordinator reading it.
     *
     * Deliberately not the bootstrapped registry: each case needs to control which animations are
     * active, and the site-wide one holds whatever sitchco.config.php activated. The container is
     * still the shared one, so module instances are the same singletons the site uses.
     */
    private function frameworkFor(string ...$moduleClassnames): AnimationFrameworkModule
    {
        return new AnimationFrameworkModule($this->registryFor(...$moduleClassnames));
    }

    private function registryFor(string ...$moduleClassnames): ModuleRegistry
    {
        $registry = new ModuleRegistry($this->container);
        $registry->activateModules(array_fill_keys($moduleClassnames, true));

        return $registry;
    }

    /**
     * Run $fn with error_log silenced and the Logger's last-entry state cleared of leakage from
     * earlier tests, then hand back whatever it logged. The test bootstrap pins SITCHCO_LOG_LEVEL to
     * ERROR, which is the level discovery failures log at, so the threshold needs no adjusting.
     */
    private function captureLogs(callable $fn): ?array
    {
        $lastEntry = new \ReflectionProperty(Logger::class, 'lastEntry');
        $lastEntry->setValue(null, null);
        Logger::silenceErrorLog(true);
        try {
            $fn();
        } finally {
            Logger::silenceErrorLog(false);
        }

        return Logger::getLastEntry();
    }

    public function test_key_defaults_to_the_hook_suffix(): void
    {
        $this->assertSame(AnimationTester::HOOK_SUFFIX, $this->container->get(AnimationTester::class)->key());
    }

    public function test_key_can_be_overridden_away_from_the_hook_suffix(): void
    {
        $animation = $this->container->get(SecondAnimationTester::class);

        $this->assertSame('second-tester', $animation->key());
        $this->assertNotSame(SecondAnimationTester::HOOK_SUFFIX, $animation->key());
    }

    public function test_discovers_active_animations_keyed_by_animation_key(): void
    {
        $animations = $this->frameworkFor(AnimationTester::class)->getAnimations();

        $this->assertSame(['animation-tester'], array_keys($animations));
        $this->assertInstanceOf(AnimationTester::class, $animations['animation-tester']);
    }

    public function test_ignores_modules_that_are_not_animations(): void
    {
        $registry = $this->registryFor(AnimationTester::class, ModuleTester::class);
        $animations = (new AnimationFrameworkModule($registry))->getAnimations();

        $this->assertArrayHasKey(ModuleTester::class, $registry->getActiveModules());
        $this->assertSame(['animation-tester'], array_keys($animations));
    }

    public function test_discovers_multiple_animations(): void
    {
        $animations = $this->frameworkFor(AnimationTester::class, SecondAnimationTester::class)->getAnimations();

        $this->assertInstanceOf(AnimationTester::class, $animations['animation-tester']);
        $this->assertInstanceOf(SecondAnimationTester::class, $animations['second-tester']);
    }

    public function test_get_animation_looks_up_by_key(): void
    {
        $framework = $this->frameworkFor(AnimationTester::class);

        $this->assertInstanceOf(AnimationTester::class, $framework->getAnimation('animation-tester'));
        $this->assertNull($framework->getAnimation('no-such-animation'));
    }

    public function test_duplicate_key_keeps_the_first_animation_and_logs_an_error(): void
    {
        $framework = $this->frameworkFor(AnimationTester::class, DuplicateAnimationTester::class);
        $animations = null;

        $entry = $this->captureLogs(function () use ($framework, &$animations) {
            $animations = $framework->getAnimations();
        });

        $this->assertSame(['animation-tester'], array_keys($animations));
        $this->assertInstanceOf(AnimationTester::class, $animations['animation-tester']);

        $this->assertSame(LogLevel::ERROR, $entry['level']);
        $this->assertStringContainsString('animation-tester', $entry['value']);
        $this->assertStringContainsString(DuplicateAnimationTester::class, $entry['value']);
        $this->assertStringContainsString(AnimationTester::class, $entry['value']);
    }

    public function test_empty_key_is_dropped_with_an_error_without_losing_other_animations(): void
    {
        $framework = $this->frameworkFor(EmptyKeyAnimationTester::class, AnimationTester::class);
        $animations = null;

        $entry = $this->captureLogs(function () use ($framework, &$animations) {
            $animations = $framework->getAnimations();
        });

        $this->assertSame(['animation-tester'], array_keys($animations));
        $this->assertSame(LogLevel::ERROR, $entry['level']);
        $this->assertStringContainsString(EmptyKeyAnimationTester::class, $entry['value']);
    }

    public function test_activating_an_animation_pulls_in_the_framework(): void
    {
        $active = $this->registryFor(AnimationTester::class)->getActiveModules();

        $this->assertArrayHasKey(AnimationFrameworkModule::class, $active);
    }

    public function test_no_active_animations_discovers_nothing(): void
    {
        $this->assertSame([], $this->frameworkFor(ModuleTester::class)->getAnimations());
    }

    public function test_discovery_is_memoized(): void
    {
        $registry = $this->registryFor(AnimationTester::class);
        $framework = new AnimationFrameworkModule($registry);
        $first = $framework->getAnimations();

        // Activating another animation after the first lookup must not change what was resolved:
        // the whole point of memoizing is that the list cannot shift mid-request.
        $registry->activateModules([SecondAnimationTester::class => true]);

        $this->assertSame($first, $framework->getAnimations());
    }
}
