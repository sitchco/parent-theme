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
     *
     * init() is called because ModuleRegistry would have: it is what opens memoization, and every
     * case but the too-early one wants the coordinator in the state the site runs it in.
     */
    private function frameworkWithConfig(
        ConfigRegistry $configRegistry,
        string ...$moduleClassnames,
    ): AnimationFrameworkModule {
        $framework = new AnimationFrameworkModule($this->registryFor(...$moduleClassnames), $configRegistry);
        $framework->init();

        return $framework;
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

    public function testKeyDefaultsToTheHookSuffix(): void
    {
        $this->assertSame(AnimationTester::HOOK_SUFFIX, $this->container->get(AnimationTester::class)->key());
    }

    public function testKeyCanBeOverriddenAwayFromTheHookSuffix(): void
    {
        $animation = $this->container->get(SecondAnimationTester::class);

        $this->assertSame('second-tester', $animation->key());
        $this->assertNotSame(SecondAnimationTester::HOOK_SUFFIX, $animation->key());
    }

    public function testDiscoversActiveAnimationsKeyedByAnimationKey(): void
    {
        $animations = $this->frameworkFor(AnimationTester::class)->getAnimations();

        $this->assertSame(['animation-tester'], array_keys($animations));
        $this->assertInstanceOf(AnimationTester::class, $animations['animation-tester']);
    }

    public function testIgnoresModulesThatAreNotAnimations(): void
    {
        $registry = $this->registryFor(AnimationTester::class, ModuleTester::class);
        $framework = new AnimationFrameworkModule($registry, new ConfigRegistryTester());
        $framework->init();
        $animations = $framework->getAnimations();

        $this->assertArrayHasKey(ModuleTester::class, $registry->getActiveModules());
        $this->assertSame(['animation-tester'], array_keys($animations));
    }

    public function testDiscoversMultipleAnimations(): void
    {
        $animations = $this->frameworkFor(AnimationTester::class, SecondAnimationTester::class)->getAnimations();

        $this->assertInstanceOf(AnimationTester::class, $animations['animation-tester']);
        $this->assertInstanceOf(SecondAnimationTester::class, $animations['second-tester']);
    }

    public function testGetAnimationLooksUpByKey(): void
    {
        $framework = $this->frameworkFor(AnimationTester::class);

        $this->assertInstanceOf(AnimationTester::class, $framework->getAnimation('animation-tester'));
        $this->assertNull($framework->getAnimation('no-such-animation'));
    }

    public function testDuplicateKeyKeepsTheFirstAnimationAndLogsAnError(): void
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

    public function testEmptyKeyIsDroppedWithAnErrorWithoutLosingOtherAnimations(): void
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

    public function testActivatingAnAnimationPullsInTheFramework(): void
    {
        $active = $this->registryFor(AnimationTester::class)->getActiveModules();

        $this->assertArrayHasKey(AnimationFrameworkModule::class, $active);
    }

    public function testNoActiveAnimationsDiscoversNothing(): void
    {
        $this->assertSame([], $this->frameworkFor(ModuleTester::class)->getAnimations());
    }

    public function testDiscoveryIsMemoized(): void
    {
        $registry = $this->registryFor(AnimationTester::class);
        $framework = new AnimationFrameworkModule($registry, new ConfigRegistryTester());
        $framework->init();
        $first = $framework->getAnimations();

        // Activating another animation after the first lookup must not change what was resolved:
        // the whole point of memoizing is that the list cannot shift mid-request.
        $registry->activateModules([SecondAnimationTester::class => true]);

        $this->assertSame($first, $framework->getAnimations());
    }

    public function testDiscoveryBeforeInitAnswersWithoutFreezingAPartialList(): void
    {
        /* ModuleRegistry builds a module before adding it to the active list, so a coordinator asked
           from a constructor sees a list that is still filling. Memoizing that would drop an
           animation for the whole request and say nothing, so nothing is kept until init(). */
        $registry = $this->registryFor(AnimationTester::class);
        $framework = new AnimationFrameworkModule($registry, new ConfigRegistryTester());

        $this->assertSame(['animation-tester'], array_keys($framework->getAnimations()));

        $registry->activateModules([SecondAnimationTester::class => true]);
        $framework->init();

        $this->assertSame(['animation-tester', 'second-tester'], array_keys($framework->getAnimations()));
    }

    public function testBareListEnablesEveryListedAnimationInConfigOrder(): void
    {
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/bare-list');

        $this->assertSame(['animation-tester', 'second-tester'], array_keys($entries));
    }

    public function testAnEntryCarriesTheKeyLabelAndEmptyOverrides(): void
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

    public function testAnEmptyOverrideArrayEnablesRatherThanRemoves(): void
    {
        // The distinction truthiness filtering would get wrong: [] is falsy but means "no overrides".
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/empty-overrides');

        $this->assertArrayHasKey('animation-tester', $entries);
    }

    public function testAllowedRestrictsAnOptionsValues(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/overrides');

        $this->assertSame(['color' => ['purple', 'green', 'red']], $entry['allowed']);
    }

    public function testDefaultsKeepTheTypesTheyWereAuthoredWith(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/overrides');

        // Note `reverse`: a literal false default, not the removal idiom, which applies one level up.
        $this->assertSame(['opacity' => '30', 'speed' => 25, 'reverse' => false], $entry['defaults']);
    }

    public function testNumericAllowedValuesSurviveNormalizationAsStrings(): void
    {
        // Normalization turns permitted values into array keys, which casts numeric strings to ints.
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/numeric-allowed');

        $this->assertSame(['opacity' => ['10', '30', '50']], $entry['allowed']);
    }

    public function testAScalarPermittedValueIsReadAsAOneItemList(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/allowed-scalar');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function testAnUnconfiguredBlockGetsNoAnimations(): void
    {
        $this->assertSame([], $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/never-mentioned'));
    }

    public function testAnimationsForBlockIsAViewOfTheResolvedMap(): void
    {
        $framework = $this->frameworkForFixtures('parent');

        $this->assertSame(
            $framework->getBlockAnimations()['test/enabled-true'],
            $framework->getAnimationsForBlock('test/enabled-true'),
        );
    }

    public function testResolutionIsMemoized(): void
    {
        /* Asserted through the warning rather than the returned map: re-resolving would produce an
           identical map, so comparing results passes with or without the memoization. The parent
           fixture always logs its combined warning, and a second resolution could not help logging
           it again. */
        $framework = $this->frameworkForFixtures('parent');

        $this->assertNotNull($this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations()));
        $this->assertNull($this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations()));
    }

    public function testChildThemeAddsABlockTheParentNeverMentioned(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock('test/child-adds-block');

        $this->assertSame(['second-tester'], array_keys($entries));
    }

    public function testChildThemeAppendsAnAnimationAfterTheParents(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock('test/child-appends');

        $this->assertSame(['animation-tester', 'second-tester'], array_keys($entries));
    }

    public function testChildThemeRemovesOneAnimationLeavingItsSiblings(): void
    {
        $entries = $this->frameworkForFixtures('parent', 'child')->getAnimationsForBlock(
            'test/child-removes-animation',
        );

        $this->assertSame(['animation-tester'], array_keys($entries));
    }

    public function testChildThemeNarrowsAnInheritedAllowedList(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-narrows-allowed');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function testChildThemeOverridesOneDefaultAndTheParentsSiblingsSurvive(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-overrides-default');

        $this->assertSame(['opacity' => '50', 'speed' => 25], $entry['defaults']);
    }

    /**
     * @dataProvider clobberedOverrideProvider
     */
    public function testReStatingAnInheritedAnimationAsTrueDiscardsItsOverrides(string $blockName): void
    {
        /* Documents the trap rather than endorsing it, for BOTH forms: normalizeData() rewrites the
           bare list into `animation-tester => true`, so the two are the same scalar by the time
           mergeRecursiveDistinct replaces the parent's override array with it. The keyed form is not
           the way out — see the next test for what is. */
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), $blockName);

        $this->assertSame([], $entry['defaults']);
    }

    public static function clobberedOverrideProvider(): array
    {
        return [
            'bare list' => ['test/child-clobbers-overrides'],
            'keyed true' => ['test/child-clobbers-overrides-keyed'],
        ];
    }

    public function testChildThemeKeepsAnInheritedOverrideByReStatingItAsAnEmptyArray(): void
    {
        // Two arrays merge, so `=> []` is how a child re-states an animation without losing anything.
        $entry = $this->entryFor($this->frameworkForFixtures('parent', 'child'), 'test/child-keeps-overrides');

        $this->assertSame(['opacity' => '30'], $entry['defaults']);
    }

    /**
     * @dataProvider droppedBlockProvider
     */
    public function testBlockIsDroppedFromTheResolvedMap(string $blockName): void
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

    public function testAnAnimationNoModuleProvidesIsDroppedWithoutLosingItsSiblings(): void
    {
        $entries = $this->frameworkForFixtures('parent')->getAnimationsForBlock('test/unknown-animation');

        $this->assertSame(['animation-tester'], array_keys($entries));
    }

    public function testOverrideArrayWithNeitherReservedKeyStaysEnabledWithNothingApplied(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/no-reserved-keys');

        $this->assertSame('animation-tester', $entry['key']);
        $this->assertSame([], $entry['allowed']);
        $this->assertSame([], $entry['defaults']);
    }

    public function testAStrayOverrideKeyDoesNotDiscardTheRecognizedOne(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/stray-key');

        $this->assertSame(['color' => ['purple']], $entry['allowed']);
    }

    public function testAnArrayDefaultIsDroppedWithoutLosingItsSiblings(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/mangled-default');

        $this->assertSame(['opacity' => '30'], $entry['defaults']);
    }

    public function testAnAbsentAnimationsSectionResolvesToNothingAndSaysNothing(): void
    {
        $framework = $this->frameworkFor(AnimationTester::class);
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });

        $this->assertSame([], $blocks);
        $this->assertNull($entry);
    }

    public function testAValidConfigResolvesToAMapAndSaysNothing(): void
    {
        /* The counterweight to every other fixture case: the parent fixture is mostly malformed, so
         without this nothing would notice the resolver starting to flag valid entries. */
        $framework = $this->frameworkForFixtures('clean');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });

        $this->assertSame(['test/clean-bare', 'test/clean-overrides'], array_keys($blocks));
        $this->assertSame(
            ['color' => ['purple', 'green']],
            $blocks['test/clean-overrides']['animation-tester']['allowed'],
        );
        $this->assertSame(
            ['color' => 'purple', 'opacity' => '30'],
            $blocks['test/clean-overrides']['animation-tester']['defaults'],
        );
        $this->assertNull($entry);
    }

    public function testAFalseAnimationsSectionDropsEveryBlockWithoutAWord(): void
    {
        /* The `=> false` removal one level up. FileRegistry::load() finds a non-array at the key and
         returns its default, so the parent fixture's malformed entries are never even read. */
        $framework = $this->frameworkForFixtures('parent', 'disabled');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });

        $this->assertSame([], $blocks);
        $this->assertNull($entry);
    }

    public function testADefaultItsOwnAllowedListForbidsIsFlagged(): void
    {
        /* The child narrows the palette and leaves the parent's default outside it, so the editor
         would open on a value its control cannot offer. Nothing else compares the two halves. */
        $framework = $this->frameworkForFixtures('parent', 'child');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());
        $problems = array_values(
            array_filter(
                $entry['value']['problems'],
                fn(string $problem) => str_contains($problem, 'child-orphans-default'),
            ),
        );

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('defaults / color', $problems[0]);
        $this->assertStringContainsString('green', $problems[0]);
    }

    public function testAPermittedDefaultOfAnotherTypeIsNotFlagged(): void
    {
        // `allowed` values are strings by the time they arrive; a default keeps the type it was
        // authored with, so an int 25 must still match the permitted '25'.
        $framework = $this->frameworkForFixtures('parent');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());

        $this->assertStringNotContainsString('test/typed-default', $entry['message']);
        $this->assertSame(['speed' => 25], $this->entryFor($framework, 'test/typed-default')['defaults']);
    }

    public function testEveryConfigProblemIsReportedInASingleWarning(): void
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

        /* Counted as well as named: substrings alone would not notice the resolver growing a
         false positive on one of the fixture's many valid entries. */
        $this->assertCount(7, $entry['value']['problems']);
    }
}
