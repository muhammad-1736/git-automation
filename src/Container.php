<?php

namespace Mu\CherryPicker;

use Mu\CherryPicker\Clients\GitLabClient;
use Mu\CherryPicker\Clients\JiraClient;
use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use DI\ContainerBuilder;
use DI\Container as DIContainer;
use Psr\Container\ContainerInterface;

class Container implements ContainerInterface
{
    private DIContainer $container;

    /**
     * Initialize the container with all service bindings
     *
     * @param string $basePath Base path of the application
     */
    public function __construct(string $basePath)
    {
        $builder = new ContainerBuilder();

        $builder->useAutowiring(true);

        $builder->addDefinitions([
            ConfigLoader::class => \DI\factory(function () use ($basePath) {
                $config = ConfigLoader::getInstance();
                $config->initialize($basePath, $basePath . '/src/Config');
                return $config;
            }),

            VCSProviderContract::class => \DI\get(GitLabClient::class),
            WMTProviderContract::class => \DI\get(JiraClient::class),
        ]);

        $this->container = $builder->build();
    }

    /**
     * Generic getter method for retrieving services
     *
     * @template T
     * @param string|class-string<T> $name The service name or class name
     * @return mixed The requested service
     */
    public function get(string $name): mixed
    {
        return $this->container->get($name);
    }

    /**
     * Check if a service is defined in the container
     *
     * @param string $name Entry name or a class name.
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->container->has($name);
    }
}
