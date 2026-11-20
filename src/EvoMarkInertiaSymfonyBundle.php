<?php

namespace EvoMark\InertiaSymfonyBundle;

use EvoMark\InertiaSymfonyBundle\Config\InertiaConfig;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The Inertia.js adapter for Symfony. Its configuration lives under the
 * `evo_mark_inertia_symfony` key, the alias Symfony derives from this class.
 */
class EvoMarkInertiaSymfonyBundle extends AbstractBundle
{
    /**
     * The bundle's configuration tree, under `evo_mark_inertia_symfony`.
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('root_view')->defaultValue('app.html.twig')->end()
                // Fixed asset version sent to clients. When null, the version is derived
                // from asset_url, then the build manifest
                ->scalarNode('version')->defaultNull()->end()
                // Base URL assets are served from (e.g. a CDN): when set, its hash is the version
                ->scalarNode('asset_url')->defaultNull()->end()
                // Build manifest hashed to derive the version (Vite/Reprise and Encore both write it here)
                ->scalarNode('manifest_path')->defaultValue('%kernel.project_dir%/public/build/manifest.json')->end()
                ->arrayNode('ssr')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->stringNode('runtime')->defaultValue('node')->end()
                        ->booleanNode('ensure_runtime_exists')->defaultFalse()->end()
                        ->stringNode('url')->defaultValue('http://127.0.0.1:13714')->end()
                        ->stringNode('hot_url')->defaultNull()->end()
                        ->integerNode('timeout')->defaultNull()->end()
                        ->booleanNode('ensure_bundle_exists')->defaultTrue()->end()
                        ->scalarNode('bundle')->defaultValue('%kernel.project_dir%/var/ssr/ssr.js')->end()
                        ->booleanNode('throw_on_error')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('pages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('ensure_pages_exist')->defaultFalse()->end()
                        ->arrayNode('paths')
                            ->scalarPrototype()->end()
                            ->defaultValue(['%kernel.project_dir%/assets/pages'])
                        ->end()
                        ->arrayNode('extensions')
                            ->scalarPrototype()->end()
                            ->defaultValue(['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('testing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('ensure_pages_exist')->defaultTrue()->end()
                    ->end()
                ->end()
                ->booleanNode('expose_shared_prop_keys')->defaultTrue()->end()
                // Send every validation message per field, not just the first
                ->booleanNode('with_all_errors')->defaultFalse()->end()
                ->booleanNode('store_previous_url')->defaultFalse()->end()
                ->arrayNode('history')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('encrypt')->defaultFalse()->end()
                    ->end()
                ->end()
                ->arrayNode('devtools')
                    ->addDefaultsIfNotSet()
                    ->children()
                        // null means "only enabled in the dev environment"
                        ->booleanNode('enabled')->defaultNull()->end()
                        ->arrayNode('except')
                            ->scalarPrototype()->end()
                            ->defaultValue(['_profiler*', '_wdt*', '_inertia/devtools*'])
                        ->end()
                        ->arrayNode('storage')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('path')->defaultValue('%kernel.project_dir%/var/inertia-devtools')->end()
                                ->integerNode('ttl')->defaultValue(24)->end()
                                ->integerNode('prune_interval')->defaultValue(300)->end()
                                ->integerNode('limit')->defaultValue(100)->end()
                            ->end()
                        ->end()
                        // Security attribute / voter checked before serving devtools data
                        ->scalarNode('gate')->defaultNull()->end()
                        ->arrayNode('redact')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('keys')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([
                                        'password',
                                        'password_confirmation',
                                        'current_password',
                                        'token',
                                        '_token',
                                        'access_token',
                                        'refresh_token',
                                        'secret',
                                        'client_secret',
                                        'api_key',
                                    ])
                                ->end()
                                ->arrayNode('headers')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([
                                        'cookie',
                                        'set-cookie',
                                        'authorization',
                                        'proxy-authorization',
                                        'x-xsrf-token',
                                        'x-csrf-token',
                                    ])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('preserve_big_integers')->defaultFalse()->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     */
    /**
     * Register the processed configuration as a service, then the bundle's services.
     *
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set('evo_mark_inertia_symfony.config', InertiaConfig::class)->args([$config]);
        $services->alias(InertiaConfig::class, 'evo_mark_inertia_symfony.config');

        $container->import('../config/services.php');
    }
}
