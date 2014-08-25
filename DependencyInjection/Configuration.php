<?php
namespace Mathielen\ImportEngineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $storageTypes = array('service', 'array', 'doctrine', 'file');
        $providerTypes = array('directory', 'upload', 'doctrine');
        $fileFormats = array('csv', 'excel', 'xml', 'yaml');

        $treeBuilder = new TreeBuilder();
        $treeBuilder
            ->root('mathielen_import_engine')
            ->fixXmlConfig('importer')
                ->children()
                    ->arrayNode('storageprovider')
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->enumNode('type')
                                    ->values($providerTypes)
                                ->end()
                                ->scalarNode('uri')->end()      //file
                                ->scalarNode('service')->end()  //service
                                ->scalarNode('method')->end()   //service
                                ->arrayNode('queries')
                                    ->prototype('scalar')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('importers')
                        ->requiresAtLeastOneElement()
                        ->useAttributeAsKey('name')
                        ->prototype('array')
                            ->children()
                                ->arrayNode('preconditions')
                                    ->fixXmlConfig('field')
                                    ->children()
                                        ->arrayNode('format')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) { return array($v); })
                                            ->end()
                                            ->prototype('enum')
                                                ->values($fileFormats)
                                            ->end()
                                        ->end()
                                        ->integerNode('fieldcount')->min(0)->end()
                                        ->arrayNode('filename')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) { return array($v); })
                                            ->end()
                                            ->prototype('scalar')->end()
                                        ->end()
                                        ->arrayNode('fieldset')
                                            ->prototype('scalar')->end()
                                        ->end()
                                        ->arrayNode('fields')
                                            ->prototype('scalar')->end()
                                        ->end()
                                    ->end()
                                ->end()

                                ->arrayNode('object_factory')
                                    ->children()
                                        ->enumNode('type')
                                            ->defaultValue('default')
                                            ->values(array('default', 'jms_serializer'))
                                        ->end()
                                        ->scalarNode('class')
                                        ->end()
                                    ->end()
                                ->end()

                                ->arrayNode('source')
                                    ->children()
                                        ->enumNode('type')
                                            ->values($storageTypes)
                                        ->end()
                                        ->scalarNode('uri')->end()
                                        ->enumNode('format')
                                            ->values($fileFormats)
                                        ->end()
                                        ->scalarNode('service')->end()
                                        ->scalarNode('method')->end()
                                    ->end()
                                ->end()

                                ->arrayNode('validation')
                                    ->children()
                                        ->arrayNode('options')
                                            ->children()
                                                ->booleanNode('allowExtraFields')->end()
                                                ->booleanNode('allowMissingFields')->end()
                                            ->end()
                                        ->end()
                                        ->arrayNode('source')
                                            ->beforeNormalization()
                                                ->ifArray()
                                                ->then(function ($v) { return array('constraints'=>$v); })
                                            ->end()
                                            ->children()
                                                ->arrayNode('constraints')
                                                    ->prototype('scalar')->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                        ->arrayNode('target')
                                            ->beforeNormalization()
                                                ->ifArray()
                                                ->then(function ($v) { return array('constraints'=>$v); })
                                            ->end()
                                            ->children()
                                                ->arrayNode('constraints')
                                                    ->prototype('scalar')->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()

                                ->arrayNode('target')
                                    ->isRequired()
                                    ->children()
                                        ->enumNode('type')
                                            ->values($storageTypes)
                                        ->end()
                                        ->enumNode('format')            //file
                                            ->values($fileFormats)
                                        ->end()
                                        ->scalarNode('uri')->end()      //file
                                        ->scalarNode('service')->end()  //service
                                        ->scalarNode('method')->end()   //service
                                        ->scalarNode('entity')->end()   //doctrine
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end();

        return $treeBuilder;
    }

}
