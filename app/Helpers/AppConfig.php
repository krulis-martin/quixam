<?php

declare(strict_types=1);

namespace App\Helpers;

use Nette\SmartObject;
use Nette\Utils\Arrays;

/**
 * A helper class for application configuration parameters.
 */
final class AppConfig
{
    use SmartObject;

    private string $title = 'Quixam';
    private ?string $style = null;

    /**
     * Initialize application configuration.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->title = Arrays::get($config, "title", 'Quixam');
        $this->style = Arrays::get($config, "style", null);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }
}
