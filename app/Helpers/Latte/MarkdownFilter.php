<?php

declare(strict_types=1);

namespace App\Helpers;

use League\CommonMark\CommonMarkConverter;
use Latte\Runtime\Html;
use Nette;

class MarkdownFilter
{
    use Nette\SmartObject;

    private $converter = null;

    /**
     * A filter extension for latte: Markdown-to-HTML rendering filter.
     */
    public function __invoke(string $markdown): Html
    {
        if (!$this->converter) {
            // lazy initialization
            $this->converter = new CommonMarkConverter([
                'html_input' => 'escape',
                'allow_unsafe_links' => true,
            ]);
        }

        $html = $this->converter->convert($markdown)->getContent();
        return new Html("<div class=\"markdown\">$html</div>");
    }
}
