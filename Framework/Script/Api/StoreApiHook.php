<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Api;

use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\Framework\Script\Execution\Awareness\ScriptResponseAwareTrait;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHook;
use Shopware\Core\Framework\Script\Execution\Awareness\StoppableHookTrait;
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * Triggered when the api endpoint /store-api/script/{hook} is called. Used to execute your logic and provide a response to the request.
 *
 * @hook-use-case custom_endpoint
 *
 * @since 6.4.9.0
 */
class StoreApiHook extends Hook implements SalesChannelContextAware, StoppableHook
{
    use StoppableHookTrait;
    use ScriptResponseAwareTrait;

    public const HOOK_NAME = 'store-api-{hook}';

    private array $request;

    private array $query;

    private SalesChannelContext $salesChannelContext;

    private string $script;

    public function __construct(string $name, array $request, array $query, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->query = $query;
        $this->salesChannelContext = $salesChannelContext;

        parent::__construct($salesChannelContext->getContext());
        $this->script = $name;
    }

    public function getRequest(): array
    {
        return $this->request;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getName(): string
    {
        return \str_replace(
            ['{hook}'],
            [$this->script],
            self::HOOK_NAME
        );
    }

    public static function getServiceIds(): array
    {
        return [
            RepositoryFacadeHookFactory::class,
            SystemConfigFacadeHookFactory::class,
            SalesChannelRepositoryFacadeHookFactory::class,
            RepositoryWriterFacadeHookFactory::class,
            ScriptResponseFactoryFacadeHookFactory::class,
        ];
    }
}
