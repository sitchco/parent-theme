<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Parent\Modules\Animation\AnimationFrameworkModule;
use Sitchco\Parent\Tests\Support\AnimationTester;
use Sitchco\Parent\Tests\Support\ConfigRegistryTester;
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
        return $this->frameworkWithConfig(new ConfigRegistryTester(), ...$moduleClassnames);
    }

    /**
     * The same, reading a specific config instead of nothing. A ConfigRegistryTester with no fixture
     * directories finds no files at all, so `animations` resolves to an empty section and the
     * discovery cases above are unaffected by config.
     */
    private function frameworkWithConfig(
        ConfigRegistry $configRegistry,
        string ...$moduleClassnames,
    ): AnimationFrameworkModule {
        return new AnimationFrameworkModule($this->registryFor(...$moduleClassnames), $configRegistry);
    }

    /**
     * A coordinator with both test animations active, reading the named fixture layers in order —
     * 'parent' alone for a single-layer config, 'parent', 'child' for an override chain.
     */
    private function frameworkForFixtures(string ...$layers): AnimationFrameworkModule
    {
        $dirs = array_map(fn(string $layer) => __DIR__ . "/fixtures/animations/{$layer}", $layers);

        return $this->frameworkWithConfig(
            new ConfigRegistryTester(...$dirs),
            AnimationTester::class,
            SecondAnimationTester::class,
        );
    }

    /** One animation's resolved entry on one block. */
    private function entryFor(
        AnimationFrameworkModule $framework,
        string $blockName,
        string $key = 'animation-tester',
    ): array {
        return $framework->getAnimationsForBlock($blockName)[$key] ?? [];
    }

    private function registryFor(string ...$moduleClassnames): ModuleRegistry
    {
        $registry = new ModuleRegistry($this->container);
        $registry->activateModules(array_fill_keys($moduleClassnames, true));

        return $registry;
    }

    /**
     * Run $fn with the Logger threshold lowered to $level so entries below the bootstrap's pinned
     * SITCHCO_LOG_LEVEL of ERROR are recorded for assertion, with error_log noise silenced and the
     * last-entry state cleared of leakage from earlier tests.
     */
    private function captureLogsAt(LogLevel $level, callable $fn): ?array
    {
        $resolved = new \ReflectionProperty(Logger::class, 'resolvedLevel');
        $lastEntry = new \ReflectionProperty(Logger::class, 'lastEntry');
        $previous = $resolved->getValue();
        $resolved->setValue(null, $level);
        $lastEntry->setValue(null, null);
        Logger::silenceErrorLog(true);
        try {
            $fn();
        } finally {
            Logger::silenceErrorLog(false);
            $resolved->setValue(null, $previous);
        }

        return Logger::getLastEntry();
    }

    /** Discovery failures log at ERROR, which the bootstrap threshold already admits. */
    private function captureLogs(callable $fn): ?array
    {
        return $this->captureLogsAt(LogLevel::ERROR, $fn);
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
        $animations = (new AnimationFrameworkModule($registry, new ConfigRegistryTester()))->getAnimations();

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
        $framework = new AnimationFrameworkModule($registry, new ConfigRegistryTester());
        $first = $framework->getAnimations();

        // Activating another animation after the first lookup must not change what was resolved:
        // the whole point of memoizing is that the list cannot shift mid-request.
        $registry->activateModules([SecondAnimationTester::class => true]);

        $this->assertSame($first, $framework->getAnimations());
    }

    public function test_bare_list_enables_every_listed_animation_in_config_order(): void
    {
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/bare-list');

        $this->assertSame(['animation-tester', 'second-tester'], array_keys($entries));
    }

    public function test_an_entry_carries_the_key_label_and_empty_overrides(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/enabled-true');

        $this->assertSame(
            [
                'key' => 'animation-tester',
                'label' => 'Animation Tester',
                'allowed' => [],
                'defaults' => [],
            ],
            $entry,
        );
    }

    public function test_an_empty_override_array_enables_rather_than_removes(): void
    {
        // The distinction truthiness filtering would get wrong: [] is falsy but means "no overrides".
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/empty-overrides');

        $this->assertArrayHasKey('animation-tester', $entries);
    }

    public function test_allowed_restricts_an_options_values(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/overrides');

        $this->assertSame(['color' => ['purple', 'green', 'red']], $entry['allowed']);
    }

    public function test_defaults_keep_the_types_they_were_authored_with(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/overrides');

        // Note `reverse`: a literal false default, not the removal idiom, which applies one level up.
        $this->assertSame(['opacity' => '30', 'speed' => 25, 'reverse' => false], $entry['defaults']);
    }

    public function test_numeric_allowed_values_survive_normalization_as_strings(): void
    {
        // Normalization turns permitted values into array keys, which casts numeric strings to ints.
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/numeric-allowed');

        $this->assertSame(['opacity' => ['10', '30', '50']], $entry['allowed']);
    }

    public function test_a_scalar_permitted_value_is_read_as_a_one_item_list(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/allowed-scalar');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function test_an_unconfigured_block_gets_no_animations(): void
    {
        $this->assertSame([], $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/never-mentioned'));
    }

    public function test_animations_for_block_is_a_view_of_the_resolved_map(): void
    {
        $framework = $this->frameworkForFixtures('parent');

        $this->assertSame(
            $framework->getBlockAnimations()['test/enabled-true'],
            $framework->getAnimationsForBlock('test/enabled-true'),
        );
    }

    public function test_resolution_is_memoized(): void
    {
        $registry = $this->registryFor(AnimationTester::class);
        $framework = new AnimationFrameworkModule(
            $registry,
            new ConfigRegistryTester(__DIR__ . '/fixtures/animations/parent'),
        );
        $first = $framework->getBlockAnimations();

        // Activating a second animation after the first lookup must not change what was resolved.
        $registry->activateModules([SecondAnimationTester::class => true]);

        $this->assertSame($first, $framework->getBlockAnimations());
    }

    public function test_child_theme_adds_a_block_the_parent_never_mentioned(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock('test/child-adds-block');

        $this->assertSame(['second-tester'], array_keys($entries));
    }

    public function test_child_theme_appends_an_animation_after_the_parents(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock('test/child-appends');

        $this->assertSame(['animation-tester', 'second-tester'], array_keys($entries));
    }

    public function test_child_theme_removes_one_animation_leaving_its_siblings(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock(
            'test/child-removes-animation',
        );

        $this->assertSame(['animation-tester'], array_keys($entries));
    }

    public function test_child_theme_narrows_an_inherited_allowed_list(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-narrows-allowed');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function test_child_theme_overrides_one_default_and_the_parents_siblings_survive(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-overrides-default');

        $this->assertSame(['opacity' => '50', 'speed' => 25], $entry['defaults']);
    }

    public function test_child_theme_bare_list_form_discards_an_inherited_override(): void
    {
        /* Documents the trap rather than endorsing it: mergeRecursiveDistinct replaces an array with
           a scalar, so the bare-list form wipes out the overrides an ancestor set on that animation
           instead of adding to them. A child theme wanting both uses the keyed form. */
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-clobbers-overrides');

        $this->assertSame([], $entry['defaults']);
    }

    /**
     * @dataProvider droppedBlockProvider
     */
    public function test_block_is_dropped_from_the_resolved_map(string $blockName): void
    {
        $blocks = $this->frameworkForFixtures('parent', 'child')->getBlockAnimations();

        $this->assertArrayNotHasKey($blockName, $blocks);
    }

    public static function droppedBlockProvider(): array
    {
        return [
            'listed with no animations' => ['test/bare-block'],
            'mapped to a scalar' => ['test/scalar-block'],
            'removed at the block level' => ['test/child-removes-block'],
            'left with no animation after a removal' => ['test/child-removes-last-animation'],
        ];
    }

    public function test_an_animation_no_module_provides_is_dropped_without_losing_its_siblings(): void
    {
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/unknown-animation');

        $this->assertSame(['animation-tester'], array_keys($entries));
    }

    public function test_override_array_with_neither_reserved_key_stays_enabled_with_nothing_applied(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/no-reserved-keys');

        $this->assertSame('animation-tester', $entry['key']);
        $this->assertSame([], $entry['allowed']);
        $this->assertSame([], $entry['defaults']);
    }

    public function test_a_stray_override_key_does_not_discard_the_recognized_one(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/stray-key');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function test_an_array_default_is_dropped_without_losing_its_siblings(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/mangled-default');

        $this->assertSame(['opacity' => '30'], $entry['defaults']);
    }

    public function test_an_absent_animations_section_resolves_to_nothing_and_says_nothing(): void
    {
        $framework = $this->frameworkFor(AnimationTester::class);
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });

        $this->assertSame([], $blocks);
        $this->assertNull($entry);
    }

    public function test_every_config_problem_is_reported_in_a_single_warning(): void
    {
        $framework = $this->frameworkForFixtures('parent');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());

        $this->assertSame(LogLevel::WARNING, $entry['level']);

        /* One entry naming every offender, because Logger keeps only its last entry — separate calls
         would hide each other, and a reader fixing the config wants the whole list at once. */
        foreach (
            [
                'test/bare-block',
                'test/scalar-block',
                'no-such-animation',
                'test/no-reserved-keys',
                'test/stray-key',
                'test/mangled-default',
                'test/allowed-empty',
            ]
            as $offender
        ) {
            $this->assertStringContainsString($offender, $entry['message']);
        }
    }
}
