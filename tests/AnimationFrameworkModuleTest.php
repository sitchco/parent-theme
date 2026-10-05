<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Framework\ConfigRegistry;
use Sitchco\Framework\ModuleRegistry;
use Sitchco\Parent\Modules\Animation\AnimationFrameworkModule;
use Sitchco\Modules\UIFramework\UIFramework;
use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;
use Sitchco\Parent\Tests\Support\AnimationTester;
use Sitchco\Parent\Tests\Support\ConfigRegistryTester;
use Sitchco\Parent\Tests\Support\DuplicateAnimationTester;
use Sitchco\Parent\Tests\Support\EmptyKeyAnimationTester;
use Sitchco\Parent\Tests\Support\MalformedControlsAnimationTester;
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
     * discovery cases below — the first is testDiscoversActiveAnimationsKeyedByAnimationKey — are
     * unaffected by config.
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
     * Fire the editor-assets hook against one coordinator and return what it queued.
     *
     * Isolated on both sides. The script queue is swapped for a fresh one, so a handle registered
     * by one case is invisible to the next — which is what lets the no-config case assert an
     * absence at all. And the hook itself is emptied first: every case in this file calls init(),
     * each leaving its enqueue closure behind, so without this do_action() would replay every
     * fixture config the file has resolved so far onto the same handle. Both are restored
     * afterwards rather than left cleared, since they are global for the rest of the suite.
     *
     * Follows ModuleAssetsTest::resetWPDependencies(), which does the same for wp_footer.
     */
    private function queuedEditorScripts(callable $buildFramework): \WP_Scripts
    {
        $hook = 'enqueue_block_editor_assets';
        $savedScripts = $GLOBALS['wp_scripts'] ?? null;
        $savedHook = $GLOBALS['wp_filter'][$hook] ?? null;

        unset($GLOBALS['wp_scripts'], $GLOBALS['wp_filter'][$hook]);

        try {
            $buildFramework();
            do_action($hook);

            return wp_scripts();
        } finally {
            $GLOBALS['wp_scripts'] = $savedScripts;
            if ($savedHook) {
                $GLOBALS['wp_filter'][$hook] = $savedHook;
            }
        }
    }

    private function editorHandle(): string
    {
        return AnimationFrameworkModule::hookName('editor-ui');
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

    public function testActivatingTheFrameworkPullsInExtendBlock(): void
    {
        /* The editor control is an ExtendBlock registration, and ModuleRegistry follows
           DEPENDENCIES without consulting config — so this is what guarantees the library is
           present wherever an animation is, without either theme having to list it. */
        $active = $this->registryFor(AnimationFrameworkModule::class)->getActiveModules();

        $this->assertArrayHasKey(ExtendBlockModule::class, $active);
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

    public function testDefaultsTakeTheirControlsTypes(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('parent'), 'test/overrides');

        /* `speed` is a select, whose values are strings the editor matches strictly, so the authored
           25 arrives as '25'. Note `reverse`: a literal false default on a toggle, not the removal
           idiom, which applies one level up. */
        $this->assertSame(['opacity' => '30', 'speed' => '25', 'reverse' => false], $entry['defaults']);
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

    public function testBlockResolutionBeforeInitAnswersWithoutFreezingAPartialMap(): void
    {
        /* getBlockAnimations() needs the same guard as getAnimations() and its own case for it:
           resolution looks every configured animation up in the module list, so a map resolved
           while that list is still filling is short an animation — and memoizing it would keep
           everyone else short for the rest of the request, silently. */
        $registry = $this->registryFor(AnimationTester::class);
        $framework = new AnimationFrameworkModule(
            $registry,
            new ConfigRegistryTester(__DIR__ . '/fixtures/animations/clean'),
        );

        $this->assertSame(['animation-tester'], array_keys($framework->getAnimationsForBlock('test/clean-bare')));

        $registry->activateModules([SecondAnimationTester::class => true]);
        $framework->init();

        $this->assertSame(
            ['animation-tester', 'second-tester'],
            array_keys($framework->getAnimationsForBlock('test/clean-bare')),
        );
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

        $this->assertSame(['opacity' => '50', 'speed' => '25'], $entry['defaults']);
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
        /* The counterweight to every other fixture case. Eight of the parent fixture's entries are
         malformed and every test reading it resolves with the aggregated warning, so without a
         layer where nothing is wrong, nothing would notice the resolver starting to flag valid
         entries. */
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

    public function testTheEditorScriptCarriesTheResolvedMapAndTheLibraryThatConsumesIt(): void
    {
        $framework = null;
        $scripts = $this->queuedEditorScripts(function () use (&$framework) {
            $framework = $this->frameworkForFixtures('clean');
        });
        $handle = $this->editorHandle();

        $this->assertContains($handle, $scripts->queue);

        /* The control is an extendBlock() call against window.sitchco.extendBlock, and it has to
         register during editorReady — so both handles are load-bearing, not incidental. */
        $registered = $scripts->registered[$handle];
        $this->assertContains(ExtendBlockModule::hookName(), $registered->deps);
        $this->assertContains(UIFramework::hookName('editor'), $registered->deps);

        /* The map has to arrive intact under the global the editor script reads. Asserting the
           decoded payload rather than the encoded string is what makes this catch a value that
           resolves in PHP but does not survive wp_json_encode(). */
        $inline = implode('', array_filter((array) ($registered->extra['before'] ?? []), 'is_string'));
        $this->assertStringContainsString('window.sitchco.animations', $inline);
        $this->assertSame(1, preg_match('/window\\.sitchco\\.animations = (.+);$/', $inline, $matches));
        $this->assertSame(
            [
                'blocks' => $framework->getBlockAnimations(),
                'controls' => $framework->getAnimationControls(),
            ],
            json_decode($matches[1], true),
        );
    }

    public function testNoConfiguredBlockMeansNoEditorScriptAtAll(): void
    {
        /* The parent theme ships `'animations' => []`, so this is the shipped state: an active
         animation, no block configured for it, and nothing enqueued to build a control with. */
        $scripts = $this->queuedEditorScripts(fn() => $this->frameworkFor(AnimationTester::class));

        $this->assertArrayNotHasKey($this->editorHandle(), $scripts->registered);
    }

    public function testAFalseAnimationsSectionDropsEveryBlockWithoutAWord(): void
    {
        /* The `=> false` removal one level up. FileRegistry::load() finds a non-array at the key and
         returns its default, so the parent fixture's malformed entries go unlogged — core still
         reads and normalizes the file, but the resolver never sees the section. */
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

        /* The whole chain counted, not just the one problem filtered for above: every other entry
           the child adds is a removal idiom or an additive merge, and none of them may start
           warning. The parent's eight plus this one. */
        $this->assertCount(9, $entry['value']['problems']);
    }

    public function testAPermittedDefaultOfAnotherTypeIsNotFlagged(): void
    {
        // `allowed` values are strings by the time they arrive, and so is a select's default once
        // cast, so an authored int 25 must still match the permitted '25'.
        $framework = $this->frameworkForFixtures('parent');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());

        $this->assertStringNotContainsString('test/typed-default', $entry['message']);
        $this->assertSame(['speed' => '25'], $this->entryFor($framework, 'test/typed-default')['defaults']);
    }

    public function testAChildStatingAnAllowedListWidensTheInheritedOneRatherThanReplacingIt(): void
    {
        /* The counter-intuitive half of the merge, and the reason the authoring docs tell you to
           narrow with `=> false` instead: two arrays merge, so a child's palette is added to the
           parent's rather than put in its place — and nothing logs a word about it. */
        $framework = $this->frameworkForFixtures('parent', 'child');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());

        $this->assertSame(
            ['purple', 'green', 'red'],
            $this->entryFor($framework, 'test/child-widens-allowed')['allowed']['color'],
        );
        $this->assertStringNotContainsString('test/child-widens-allowed', $entry['message']);
    }

    public function testEveryOverrideMistakeIsFlaggedWithItsFallback(): void
    {
        /* Its own fixture layer, because the parent's problem count is pinned. Every case here is
           one that would otherwise pass for something legal — `=> false` is a removal idiom one
           level up, and a marker that is not `true` still reads as permission — so each is
           asserted through both its message and the fallback that message promises. */
        $framework = $this->frameworkForFixtures('malformed-overrides');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });
        $problems = $entry['value']['problems'];

        $this->assertContains(
            'test/allowed-false / animation-tester: `allowed` must map option names to their permitted values. Leaving every option unrestricted.',
            $problems,
        );
        $this->assertSame([], $blocks['test/allowed-false']['animation-tester']['allowed']);

        $this->assertContains(
            'test/allowed-option-false / animation-tester / allowed / color: is not a list of permitted values. Leaving the option unrestricted.',
            $problems,
        );
        $this->assertSame([], $blocks['test/allowed-option-false']['animation-tester']['allowed']);

        $this->assertContains(
            'test/defaults-false / animation-tester: `defaults` must map option names to their default values. Applying no defaults.',
            $problems,
        );
        $this->assertSame([], $blocks['test/defaults-false']['animation-tester']['defaults']);

        // Counted as well as named, for the same reason the parent fixture's count is pinned.
        $this->assertCount(7, $problems);
    }

    public function testAKnownAnimationMappedToAScalarIsDropped(): void
    {
        $framework = $this->frameworkForFixtures('malformed-overrides');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });

        $this->assertContains(
            'test/scalar-animation / animation-tester: has a scalar value where `true` or an override array was expected. Dropping it.',
            $entry['value']['problems'],
        );

        // Its only animation dropped, the block has nothing to offer and leaves the map entirely.
        $this->assertArrayNotHasKey('test/scalar-animation', $blocks);
    }

    public function testAPermittedValueMarkedWithAnythingButTrueIsDroppedAndFlagged(): void
    {
        /* After normalization a permitted value arrives as `value => true`, and a child's removal
           as `value => false`. Anything else is a mistake that would otherwise read as permission:
           a forgotten nesting level permits the wrapper's name and loses the palette behind it. */
        $framework = $this->frameworkForFixtures('malformed-overrides');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });
        $problems = $entry['value']['problems'];

        $this->assertContains(
            'test/nested-allowed / animation-tester / allowed / color / brand: is marked with neither `true` nor `false`, so it is not a permitted value as written. Dropping the value.',
            $problems,
        );
        $this->assertSame(['color' => []], $blocks['test/nested-allowed']['animation-tester']['allowed']);

        // The sibling marked `true` is still permitted; only the odd one out is dropped.
        $this->assertContains(
            'test/odd-marker / animation-tester / allowed / color / purple: is marked with neither `true` nor `false`, so it is not a permitted value as written. Dropping the value.',
            $problems,
        );
        $this->assertSame(['color' => ['green']], $blocks['test/odd-marker']['animation-tester']['allowed']);
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
                'test/bool-default',
            ]
            as $offender
        ) {
            $this->assertStringContainsString($offender, $entry['message']);
        }

        /* Counted as well as named: substrings alone would not notice the resolver growing a
         false positive on one of the fixture's many valid entries. */
        $this->assertCount(8, $entry['value']['problems']);
    }

    public function testAttributeNamesJoinTheCamelCaseKeyAnimationAndTheControlName(): void
    {
        $this->assertSame('letterAnimationColor', AnimationFrameworkModule::attributeName('letter', 'color'));
        $this->assertSame('fadeUpAnimationSpeed', AnimationFrameworkModule::attributeName('fade-up', 'speed'));
        $this->assertSame('fadeUpAnimationStartAt', AnimationFrameworkModule::attributeName('fade-up', 'startAt'));
    }

    public function testControlsAreKeyedByAnimationAndName(): void
    {
        $controls = $this->frameworkFor(AnimationTester::class, SecondAnimationTester::class)->getControls();

        $this->assertSame(['color', 'opacity', 'speed', 'reverse'], array_keys($controls['animation-tester']));
        $this->assertSame(['speed', 'direction', 'tint'], array_keys($controls['second-tester']));
    }

    public function testTheEditorReceivesEachControlWithItsAttribute(): void
    {
        $serialized = $this->frameworkFor(SecondAnimationTester::class)->getAnimationControls();

        // Unset settings are left out rather than sent as null.
        $this->assertSame(
            [
                'type' => 'number',
                'name' => 'speed',
                'label' => 'Speed',
                'default' => 50,
                'min' => 0,
                'max' => 100,
                'attribute' => 'secondTesterAnimationSpeed',
            ],
            $serialized['second-tester'][0],
        );
        $this->assertSame('test.tint-options', $serialized['second-tester'][2]['optionsFilter']);
        $this->assertArrayNotHasKey('options', $serialized['second-tester'][2]);
    }

    public function testTheEditorReceivesStaticOptionValuesAsStrings(): void
    {
        $serialized = $this->frameworkFor(AnimationTester::class)->getAnimationControls();

        // Declared as integers; the editor matches values with ===, against the strings config is checked as.
        $this->assertSame(['', '10', '30', '50'], array_column($serialized['animation-tester'][1]['options'], 'value'));
    }

    public function testAnAnimationWithoutControlsSendsNone(): void
    {
        $this->assertSame([], $this->frameworkFor(DuplicateAnimationTester::class)->getAnimationControls());
    }

    public function testEveryBrokenControlIsDroppedInOneErrorAndTheFirstDefinitionWins(): void
    {
        $framework = $this->frameworkFor(SecondAnimationTester::class, MalformedControlsAnimationTester::class);
        $controls = null;

        $entry = $this->captureLogs(function () use ($framework, &$controls) {
            $controls = $framework->getControls();
        });

        // SecondAnimationTester registered first, so it keeps secondTesterAnimationSpeed; of the two `ok`
        // toggles, the first declared is kept.
        $this->assertSame(['speed', 'direction', 'tint'], array_keys($controls['second-tester']));
        $this->assertSame(['ok'], array_keys($controls['secondTester']));
        $this->assertSame('Kept', $controls['secondTester']['ok']->label);

        $this->assertSame(LogLevel::ERROR, $entry['level']);
        $this->assertSame(
            [
                'secondTester / #0: is not an AnimationControl. Dropping it.',
                'secondTester / Bad-Name: the name must be camelCase letters and digits, starting with a lowercase letter. Dropping it.',
                "secondTester / trailingNewline\n: the name must be camelCase letters and digits, starting with a lowercase letter. Dropping it.",
                'secondTester / noOptions: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                'secondTester / bothOptions: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                "secondTester / looseOptions: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                'secondTester / emptyFilter: a select needs exactly one of `options` or `optionsFilter`. Dropping it.',
                "secondTester / emptyOptions: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                "secondTester / nullValue: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                "secondTester / arrayValue: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                "secondTester / numberLabel: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                "secondTester / emptyLabel: `options` must be a non-empty list of ['label' => …, 'value' => …] pairs, each label a non-empty string and each value a string or number. Dropping it.",
                'secondTester / unofferedDefault: its default "" is not one of its options. Dropping it.',
                'secondTester / outOfRange: its default 5 is outside its range (at most 3). Dropping it.',
                'secondTester / speed: its attribute "secondTesterAnimationSpeed" is already used by second-tester / speed. Dropping it.',
                'secondTester / ok: is declared twice. Keeping the first.',
            ],
            $entry['value']['problems'],
        );
    }

    public function testControlValidationIsMemoized(): void
    {
        $framework = $this->frameworkFor(MalformedControlsAnimationTester::class);

        $this->assertNotNull($this->captureLogs(fn() => $framework->getControls()));
        $this->assertNull($this->captureLogs(fn() => $framework->getControls()));
    }

    public function testEveryControlMismatchIsFlaggedWithItsFallback(): void
    {
        $framework = $this->frameworkForFixtures('controls');
        $blocks = null;

        $entry = $this->captureLogsAt(LogLevel::WARNING, function () use ($framework, &$blocks) {
            $blocks = $framework->getBlockAnimations();
        });
        $problems = $entry['value']['problems'];

        $this->assertContains(
            'test/unknown-control / animation-tester / allowed / glyph: names no control this animation has. Ignoring it.',
            $problems,
        );
        $this->assertContains(
            'test/unknown-control / animation-tester / defaults / glyph: names no control this animation has. Ignoring it.',
            $problems,
        );
        $this->assertSame([], $blocks['test/unknown-control']['animation-tester']['allowed']);
        $this->assertSame([], $blocks['test/unknown-control']['animation-tester']['defaults']);

        $this->assertContains(
            "test/value-not-offered / animation-tester / allowed / color / teal: is not one of the control's options. Dropping the value.",
            $problems,
        );
        $this->assertSame(['color' => ['purple']], $blocks['test/value-not-offered']['animation-tester']['allowed']);

        $this->assertContains(
            'test/default-wrong-type / animation-tester / defaults / reverse: has a string default, which a toggle control cannot take. Dropping it.',
            $problems,
        );
        $this->assertContains(
            'test/default-wrong-type / animation-tester / defaults / color: has a bool default, which a select control cannot take. Dropping it.',
            $problems,
        );
        $this->assertContains(
            'test/default-wrong-type / second-tester / defaults / speed: has a string default, which a number control cannot take. Dropping it.',
            $problems,
        );
        $this->assertSame([], $blocks['test/default-wrong-type']['animation-tester']['defaults']);

        $this->assertContains(
            'test/own-default-excluded / second-tester / allowed / direction: excludes the control\'s own default "up" (permits down). The control will start on a value it does not offer — permit it, or set a default here.',
            $problems,
        );

        $this->assertContains(
            'test/default-not-offered / animation-tester / defaults / color: defaults to "teal", which is not one of the control\'s options. The control will not offer it.',
            $problems,
        );
        $this->assertContains(
            'test/default-not-offered-restricted / animation-tester / defaults / color: defaults to "teal", which is not one of the control\'s options. The control will not offer it.',
            $problems,
        );
        $this->assertSame(['color' => 'teal'], $blocks['test/default-not-offered']['animation-tester']['defaults']);

        $this->assertContains(
            'test/default-out-of-range / second-tester / defaults / speed: defaults to 150, outside the control\'s range (0 to 100).',
            $problems,
        );
        $this->assertSame(['speed' => 150], $blocks['test/default-out-of-range']['second-tester']['defaults']);

        // Counted as well as named, so the two valid entries are proven silent and the restricted
        // teal is reported once.
        $this->assertCount(10, $problems);
    }

    public function testAnOptionsFilterSelectTakesItsPermittedValuesAsWritten(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('controls'), 'test/filtered-options', 'second-tester');

        $this->assertSame(['tint' => ['anything']], $entry['allowed']);
    }

    public function testANumericStringDefaultIsCastForANumberControl(): void
    {
        $entry = $this->entryFor($this->frameworkForFixtures('controls'), 'test/number-default-cast', 'second-tester');

        $this->assertSame(['speed' => 25], $entry['defaults']);
    }

    public function testAllowedOnAToggleIsIgnoredAndItsDefaultSurvives(): void
    {
        $framework = $this->frameworkForFixtures('parent');

        $entry = $this->captureLogsAt(LogLevel::WARNING, fn() => $framework->getBlockAnimations());

        $this->assertContains(
            'test/bool-default / animation-tester / allowed / reverse: only applies to a select, and this is a toggle control. Ignoring it.',
            $entry['value']['problems'],
        );
        $bool = $this->entryFor($framework, 'test/bool-default');
        $this->assertSame([], $bool['allowed']);
        $this->assertSame(['reverse' => false], $bool['defaults']);
    }
}
