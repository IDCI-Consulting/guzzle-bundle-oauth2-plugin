<?php

namespace IDCI\Bundle\GuzzleBundleKnpUOAuth2Plugin;

use EightPoints\Bundle\GuzzleBundle\PluginInterface;
use IDCI\Bundle\GuzzleBundleKnpUOAuth2Plugin\DependencyInjection\Compiler\InjectMiddlewareKnpUOAuthClientCompilerPass;
use IDCI\Bundle\GuzzleBundleKnpUOAuth2Plugin\DependencyInjection\IDCIGuzzleBundleKnpUOAuth2PluginExtension;
use IDCI\Bundle\GuzzleBundleKnpUOAuth2Plugin\Middleware\OAuth2Middleware;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class IDCIGuzzleBundleKnpUOAuth2Plugin extends Bundle implements PluginInterface
{
    public function getPluginName() : string
    {
        return 'knpu_oauth2';
    }

    public function addConfiguration(ArrayNodeDefinition $pluginNode) : void
    {
        $pluginNode
            ->canBeEnabled()
            ->validate()
                ->ifTrue(function (array $config) {
                    return $config['enabled'] === true && empty($config['client']);
                })
                ->thenInvalid('client is required')
            ->end()
            ->children()
                ->scalarNode('client')->defaultNull()->end()
            ->end();
        ;
    }

    public function load(array $configs, ContainerBuilder $container) : void
    {
        return;
    }

    public function loadForClient(array $configuration, ContainerBuilder $container, string $clientName, Definition $handler) : void
    {
        if (!$configuration['enabled']) {
            return;
        }

        $knpuClientDefinitionName = sprintf('knpu.oauth2.client.%s', $configuration['client']);

        // Define Middleware
        $oAuth2MiddlewareDefinitionName = sprintf('idci_guzzle_bundle_knpu_oauth2_plugin.middleware.%s', $clientName);
        $oAuth2MiddlewareDefinition = new Definition(OAuth2Middleware::class);
        $oAuth2MiddlewareDefinition->setPublic(true);
        $oAuth2MiddlewareDefinition->addTag('idci_guzzle_bundle_knpu_oauth2_plugin.middleware');
        $oAuth2MiddlewareDefinition->setProperty('knpu_oauth2_client', $knpuClientDefinitionName);
        $container->setDefinition($oAuth2MiddlewareDefinitionName, $oAuth2MiddlewareDefinition);

        $onBeforeExpression = new Expression(sprintf('service("%s").onBefore()', $oAuth2MiddlewareDefinitionName));
        //$onFailureExpression = new Expression(sprintf('service("%s").onFailure(%d)', $oAuth2MiddlewareDefinitionName, $configuration['retry_limit']));

        $handler->addMethodCall('push', [$onBeforeExpression]);
        //$handler->addMethodCall('push', [$onFailureExpression]);
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new InjectMiddlewareKnpUOAuthClientCompilerPass());
    }
}