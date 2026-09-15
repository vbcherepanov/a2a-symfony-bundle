<?php

declare(strict_types=1);

namespace A2A\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('a2a');
        $root = $tree->getRootNode()->children();
        $root->scalarNode('card_file')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('extended_card_file')->defaultNull()->end()
            ->scalarNode('executor')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('public_url')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('log_stream')->defaultValue('php://stderr')->end()
            ->scalarNode('metrics_service')->defaultValue('a2a.metrics')->end();
        $root->scalarNode('metrics_file')->defaultValue('%kernel.project_dir%/var/a2a/metrics.json')->end();
        $root->arrayNode('clients')->useAttributeAsKey('name')->arrayPrototype()->children()
            ->scalarNode('endpoint')->isRequired()->cannotBeEmpty()->end()
            ->enumNode('binding')->values(['jsonrpc', 'rest', 'grpc'])->defaultValue('jsonrpc')->end()
            ->floatNode('timeout_seconds')->min(0.001)->defaultValue(60.0)->end()
            ->integerNode('max_message_bytes')->min(1)->defaultValue(4194304)->end()
            ->arrayNode('headers')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
            ->arrayNode('extensions')->scalarPrototype()->end()->end()
            ->arrayNode('tls')->addDefaultsIfNotSet()->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('root_certificates')->defaultNull()->end()
                ->scalarNode('private_key')->defaultNull()->end()
                ->scalarNode('certificate_chain')->defaultNull()->end()
                ->scalarNode('ca_file')->defaultNull()->end()
                ->scalarNode('certificate_file')->defaultNull()->end()
                ->scalarNode('private_key_file')->defaultNull()->end()
            ->end()->end()
            ->end()->end()->end();
        $root->arrayNode('auth')->addDefaultsIfNotSet()->children()
            ->scalarNode('service')->defaultNull()->end()
            ->scalarNode('access_token_handler')->defaultNull()->end()
            ->arrayNode('tokens')->useAttributeAsKey('principal')->scalarPrototype()->end()->end()
            ->end()->end();
        $root->arrayNode('storage')->addDefaultsIfNotSet()->children()
            ->enumNode('driver')->values(['file', 'doctrine'])->defaultValue('file')->end()
            ->scalarNode('directory')->defaultValue('%kernel.project_dir%/var/a2a')->end()
            ->scalarNode('connection')->defaultValue('doctrine.dbal.default_connection')->end()
            ->scalarNode('table')->defaultValue('a2a_tasks')->end()
            ->integerNode('max_update_attempts')->min(1)->defaultValue(5)->end()
            ->end()->end();
        $root->arrayNode('messenger')->addDefaultsIfNotSet()->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->scalarNode('bus')->defaultValue('messenger.default_bus')->end()
            ->end()->end();
        $transports = $root->arrayNode('transports')->addDefaultsIfNotSet()->children();
        $transports->arrayNode('jsonrpc')->addDefaultsIfNotSet()->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('path')->defaultValue('/a2a/rpc')->end()->end()->end();
        $transports->arrayNode('rest')->addDefaultsIfNotSet()->children()
            ->booleanNode('enabled')->defaultTrue()->end()
            ->scalarNode('path')->defaultValue('/a2a')->end()->end()->end();
        $transports->arrayNode('grpc')->addDefaultsIfNotSet()->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->scalarNode('host')->defaultValue('127.0.0.1')->end()
            ->integerNode('port')->min(1)->max(65535)->defaultValue(50051)->end()
            ->scalarNode('public_url')->defaultNull()->end()
            ->integerNode('workers')->min(1)->defaultValue(4)->end()
            ->integerNode('max_requests_per_worker')->min(1)->defaultValue(1000)->end()
            ->integerNode('max_concurrent_streams')->min(1)->defaultValue(100)->end()
            ->integerNode('max_connections')->min(1)->defaultValue(1024)->end()
            ->arrayNode('tls')->addDefaultsIfNotSet()->children()
                ->scalarNode('certificate_file')->defaultNull()->end()
                ->scalarNode('private_key_file')->defaultNull()->end()
                ->scalarNode('client_ca_file')->defaultNull()->end()
                ->booleanNode('require_client_certificate')->defaultFalse()->end()
            ->end()->end()
            ->end()->end();
        $transports->end()->end();
        $root->arrayNode('discovery')->addDefaultsIfNotSet()->children()
            ->integerNode('last_modified')->min(0)->defaultNull()->end()
            ->scalarNode('path')->defaultValue('/.well-known/agent-card.json')->end()
            ->integerNode('cache_seconds')->min(0)->defaultValue(300)->end()
            ->end()->end();
        $root->arrayNode('limits')->addDefaultsIfNotSet()->children()
            ->floatNode('request_timeout_seconds')->min(0.001)->defaultValue(60.0)->end()
            ->floatNode('poll_interval_seconds')->min(0.001)->defaultValue(0.1)->end()
            ->floatNode('worker_lease_seconds')->min(0.001)->defaultValue(300.0)->end()
            ->integerNode('max_message_bytes')->min(1)->defaultValue(4194304)->end()
            ->integerNode('max_events_per_task')->min(1)->defaultValue(1000)->end()
            ->integerNode('max_history_messages')->min(1)->defaultValue(1000)->end()
            ->integerNode('max_push_configs_per_task')->min(1)->defaultValue(10)->end()
            ->end()->end();
        $root->arrayNode('push')->addDefaultsIfNotSet()->children()
            ->booleanNode('allow_private_network')->defaultFalse()->end()
            ->booleanNode('require_https')->defaultTrue()->end()
            ->arrayNode('allowed_hosts')->scalarPrototype()->end()->end()
            ->floatNode('timeout_seconds')->min(0.001)->defaultValue(10.0)->end()
            ->integerNode('max_attempts')->min(1)->defaultValue(5)->end()
            ->floatNode('retry_delay_seconds')->min(0.001)->defaultValue(5.0)->end()
            ->integerNode('max_pending_per_task')->min(1)->defaultValue(1000)->end()
            ->end()->end();
        return $tree;
    }
}
