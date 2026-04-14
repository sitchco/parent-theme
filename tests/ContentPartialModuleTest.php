<?php

namespace Sitchco\Parent\Tests;

use Sitchco\Parent\Modules\ContentPartial\ContentPartialModule;
use Sitchco\Parent\Modules\ContentPartial\ContentPartialPost;
use Sitchco\Tests\TestCase;

class ContentPartialModuleTest extends TestCase
{
    protected ContentPartialModule $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = $this->container->get(ContentPartialModule::class);
    }

    /**
     * @dataProvider makeSlugEditableProvider
     */
    public function testMakeSlugEditable(bool $currentValue, string $postType, bool $expected): void
    {
        $postTypeObject = get_post_type_object($postType);
        $result = $this->module->makeSlugEditable($currentValue, $postTypeObject);

        $this->assertSame($expected, $result);
    }

    public static function makeSlugEditableProvider(): array
    {
        return [
            'returns true for content-partial' => [false, ContentPartialPost::POST_TYPE, true],
            'passes through false for other types' => [false, 'post', false],
            'preserves existing true for other types' => [true, 'post', true],
        ];
    }

    public function testPageOptionsFilterFiresForMatchingGroup(): void
    {
        $filterFired = false;
        add_filter(ContentPartialModule::hookName('page-options-locations'), function ($group) use (&$filterFired) {
            $filterFired = true;
            return $group;
        });
        $group = ['key' => ContentPartialModule::PAGE_OPTIONS_GROUP_KEY, 'location' => []];
        $this->module->applyPageOptionsFilter($group);
        $this->assertTrue($filterFired);
    }

    public function testPageOptionsFilterDoesNotFireForOtherGroups(): void
    {
        $filterFired = false;
        add_filter(ContentPartialModule::hookName('page-options-locations'), function ($group) use (&$filterFired) {
            $filterFired = true;
            return $group;
        });
        $group = ['key' => 'group_other', 'location' => []];
        $this->module->applyPageOptionsFilter($group);
        $this->assertFalse($filterFired);
    }
}
