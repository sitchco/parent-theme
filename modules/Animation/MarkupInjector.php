<?php

namespace Sitchco\Parent\Modules\Animation;

use Sitchco\Parent\Modules\ExtendBlock\ExtendBlockModule;

/**
 * Puts an animation's markup into a block's rendered HTML, as the first child of its host element.
 *
 * The HTML API locates the host; it has no public way to insert HTML, so this subclass adds one the
 * way core's own subclasses do (see the skip-link processor in wp-includes/block-template.php):
 * bookmark the host's opening tag and queue a zero-length text replacement just past its end. The
 * rest of the document is untouched, byte for byte.
 *
 * The markup is inserted as given, unescaped. It is a constant an animation module declares
 * (AnimationModule::markup()), never anything an author entered.
 */
final class MarkupInjector extends \WP_HTML_Tag_Processor
{
    private const BOOKMARK = 'sitchco-animation-markup';

    /**
     * $html with $markup inserted as the first child of its host, or null when there is no host.
     *
     * The host is the first element, in document order, carrying any of $hosts' classes. Document
     * order and not the order of $hosts, because a block's rendered HTML already holds its inner
     * blocks: a row's own wrapper comes before the columns inside it, so it is found first even
     * when a column carries an earlier class in the list. With no $hosts, the host is the block's
     * outermost element.
     *
     * A void element (`img`, `input`, …) has no children, so it cannot host markup either.
     *
     * @param list<string> $hosts
     */
    public static function inject(string $html, string $markup, array $hosts = []): ?string
    {
        $processor = new self($html);
        if (!($hosts ? $processor->seekHost($hosts) : ExtendBlockModule::seekWrapper($processor))) {
            return null;
        }
        if (\WP_HTML_Processor::is_void($processor->get_tag())) {
            return null;
        }

        $processor->set_bookmark(self::BOOKMARK);
        $opener = $processor->bookmarks[self::BOOKMARK];
        $processor->lexical_updates[] = new \WP_HTML_Text_Replacement($opener->start + $opener->length, 0, $markup);

        return $processor->get_updated_html();
    }

    /**
     * @param list<string> $hosts
     */
    private function seekHost(array $hosts): bool
    {
        while ($this->next_tag()) {
            foreach ($hosts as $host) {
                if ($this->has_class($host)) {
                    return true;
                }
            }
        }

        return false;
    }
}
