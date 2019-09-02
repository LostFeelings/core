<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ConfigHandler;

use function array_walk;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;

class File implements ConfigHandlerInterface
{
    /**
     * @var array
     */
    private $excludedUrls;

    /**
     * @var array
     */
    private $customUrls;

    public function __construct($sitemapConfig)
    {
        $this->customUrls = $sitemapConfig[ConfigHandler::CUSTOM_URLS_KEY];
        $this->excludedUrls = $sitemapConfig[ConfigHandler::EXCLUDED_URLS_KEY];
    }

    /**
     * {@inheritdoc}
     */
    public function getSitemapConfig(): array
    {
        return [
            ConfigHandler::CUSTOM_URLS_KEY => $this->getSitemapCustomUrls($this->customUrls),
            ConfigHandler::EXCLUDED_URLS_KEY => $this->excludedUrls,
        ];
    }

    private function getSitemapCustomUrls(array $customUrls): array
    {
        array_walk($customUrls, static function (array &$customUrl): void {
            $customUrl['lastMod'] = \DateTime::createFromFormat('Y-m-d H:i:s', $customUrl['lastMod']);
        });

        return $customUrls;
    }
}
