<?php

namespace Facile\MongoDbBundle\DependencyInjection;

use Facile\MongoDbBundle\Services\ClientRegistry;
use Facile\MongoDbBundle\Services\Loggers\DataCollectorLoggerInterface;
use MongoDB\Database;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class MongoDbBundleExtension.
 */
class MongoDbBundleExtension extends Extension
{
    /** @var ContainerBuilder */
    private $containerBuilder;
    private $env;

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->containerBuilder = $container;
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('factory.xml');

        $this->env = $container->getParameter("kernel.environment");
        if ($this->env === 'dev' && class_exists(WebProfilerBundle::class)) {
            $loader->load('web_profiler.xml');
        }

        $this->defineLoggers();
        $this->defineClientRegistry($config['clients']);
        $this->defineConnections($config['connections']);

        return $config;
    }

    private function defineLoggers()
    {
        $loggerDefinition = new Definition(DataCollectorLoggerInterface::class);
        $loggerDefinition->setFactory([new Reference('mongo.logger_factory'), 'createLogger']);
        $this->containerBuilder->setDefinition('facile_mongo_db.logger', $loggerDefinition);
    }

    /**
     * @param array $clientsConfig
     *
     * @return Definition
     */
    private function defineClientRegistry(array $clientsConfig)
    {
        $clientRegistryDefinition = new Definition(
            ClientRegistry::class,
            [
                new Reference('facile_mongo_db.logger'),
            ]
        );
        foreach ($clientsConfig as $name => $conf) {
            $clientRegistryDefinition
                ->addMethodCall(
                    'addClientConfiguration',
                    [
                        $name,
                        $conf,
                    ]
                );
        }

        $this->containerBuilder->setDefinition('mongo.client_registry', $clientRegistryDefinition);
    }

    /**
     * @param array $connections
     */
    private function defineConnections(array $connections)
    {

        foreach ($connections as $name => $conf) {
            $connectionDefinition = new Definition(
                Database::class,
                [
                    $conf['client_name'],
                    $conf['database_name'],
                ]
            );
            $connectionDefinition->setFactory([new Reference('mongo.connection_factory'), 'createConnection']);
            $this->containerBuilder->setDefinition('mongo.connection.'.$name, $connectionDefinition);
        }
        $this->containerBuilder->setAlias('mongo.connection', 'mongo.connection.'.array_keys($connections)[0]);
    }
}