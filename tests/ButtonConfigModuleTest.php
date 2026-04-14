<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ButtonConfig\ButtonConfigModule;
use Sitchco\Tests\TestCase;

class ButtonConfigModuleTest extends TestCase
{
    protected ButtonConfigModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->make(ButtonConfigModule::class);
    }

    public function testInitRegistersFilter(): void
    {
        $this->module->init();
        $this->assertGreaterThan(0, has_filter('block_type_metadata', [$this->module, 'filterButtonStyleVariations']));
    }

    public function testNonButtonBlockPassedThrough(): void
    {
        $metadata = [
            'name' => 'core/paragraph',
            'styles' => [['name' => 'outline', 'label' => 'Outline']],
        ];
        $result = $this->module->filterButtonStyleVariations($metadata);
        $this->assertEquals($metadata, $result);
    }

    public function testRemovesOutlineAndFillByDefault(): void
    {
        $result = $this->module->filterButtonStyleVariations($this->buttonMetadata());
        $styleNames = array_column($result['styles'], 'name');
        $this->assertNotContains('outline', $styleNames);
        $this->assertNotContains('fill', $styleNames);
    }

    public static function styleToggleProvider(): array
    {
        return [
            'outline only' => [['outline'], ['outline'], ['fill']],
            'fill only' => [['fill'], ['fill'], ['outline']],
            'both' => [['outline', 'fill'], ['outline', 'fill'], []],
        ];
    }

    /**
     * @dataProvider styleToggleProvider
     */
    public function testKeepsEnabledStylesOnly(array $enable, array $expectPresent, array $expectAbsent): void
    {
        foreach ($enable as $method) {
            $this->module->$method();
        }
        $result = $this->module->filterButtonStyleVariations($this->buttonMetadata());
        $styleNames = array_column($result['styles'], 'name');
        foreach ($expectPresent as $name) {
            $this->assertContains($name, $styleNames);
        }
        foreach ($expectAbsent as $name) {
            $this->assertNotContains($name, $styleNames);
        }
    }

    public function testHandlesEmptyStyles(): void
    {
        $metadata = ['name' => 'core/button', 'styles' => []];
        $result = $this->module->filterButtonStyleVariations($metadata);
        $this->assertEmpty($result['styles']);
    }

    public function testHandlesMissingStyles(): void
    {
        $metadata = ['name' => 'core/button'];
        $result = $this->module->filterButtonStyleVariations($metadata);
        $this->assertArrayNotHasKey('styles', $result);
    }

    public function testPreservesOtherStyles(): void
    {
        $metadata = $this->buttonMetadata();
        $result = $this->module->filterButtonStyleVariations($metadata);
        $styleNames = array_column($result['styles'], 'name');
        $this->assertContains('default', $styleNames);
        $this->assertContains('squared', $styleNames);
    }

    public function testStylesArrayIsReindexed(): void
    {
        $metadata = $this->buttonMetadata();
        $result = $this->module->filterButtonStyleVariations($metadata);
        $keys = array_keys($result['styles']);
        $this->assertEquals(range(0, count($result['styles']) - 1), $keys);
    }

    private function buttonMetadata(): array
    {
        return [
            'name' => 'core/button',
            'styles' => [
                ['name' => 'default', 'label' => 'Default', 'isDefault' => true],
                ['name' => 'outline', 'label' => 'Outline'],
                ['name' => 'fill', 'label' => 'Fill'],
                ['name' => 'squared', 'label' => 'Squared'],
            ],
        ];
    }
}
