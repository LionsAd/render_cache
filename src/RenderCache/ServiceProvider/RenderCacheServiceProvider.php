<?php

/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\ServiceProvider\RenderCacheServiceProvider
 */

namespace Drupal\render_cache\RenderCache\ServiceProvider;

use Drupal\render_cache\DependencyInjection\ServiceProviderInterface;
use Drupal\render_cache\Plugin\Discovery\CToolsPluginDiscovery;

/**
 * Provides render cache service definitions.
 */
class RenderCacheServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getContainerDefinition() {
    $parameters = array();
    $parameters['cache_contexts'] = array();

    $services = array();
    $services['service_container'] = array(
      'class' => '\Drupal\render_cache\DependencyInjection\Container',
    );

    // Cache Contexts
    $services['cache_contexts'] = array(
      'class' => '\Drupal\render_cache\Cache\CacheContexts',
      'arguments' => array(
        '@service_container',
        '%cache_contexts%',
      ),
    );
    $services['cache_context.url'] = array(
      'class' => '\Drupal\render_cache\Cache\UrlCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );
    $services['cache_context.language'] = array(
      'class' => '\Drupal\render_cache\Cache\LanguageCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );
    $services['cache_context.theme'] = array(
      'class' => '\Drupal\render_cache\Cache\ThemeCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );
    $services['cache_context.theme'] = array(
      'class' => '\Drupal\render_cache\Cache\TimezoneCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );
    // Render Stack
    $services['render_stack'] = array(
      'class' => '\Drupal\render_cache\Cache\RenderStack',
    );

    // Plugin Managers
    $services['render_cache.controller'] = array(
      'class' => '\Drupal\render_cache\Plugin\ContainerAwarePluginManager',
      'arguments' => array('render_cache.controller.internal.'),
      'calls' => array(
        array('setContainer', array('@service_container')),
      ),
      'tags' => array(
        array('ctools.plugin', array(
          'owner' => 'render_cache',
          'type' => 'Controller',
          'prefix' => 'render_cache.controller.internal.')
        ),
      ),
    );
    $services['render_cache.render_strategy'] = array(
      'class' => '\Drupal\render_cache\Plugin\ContainerAwarePluginManager',
      'arguments' => array('render_cache.render_strategy.internal.'),
      'calls' => array(
        array('setContainer', array('@service_container')),
      ),
      'tags' => array(
        array('ctools.plugin', array(
          'owner' => 'render_cache',
          'type' => 'RenderStrategy',
          'prefix' => 'render_cache.render_strategy.internal.')
        ),
      ),
    );
    $services['render_cache.validation_strategy'] = array(
      'class' => '\Drupal\render_cache\Plugin\ContainerAwarePluginManager',
      'arguments' => array('render_cache.validation_strategy.internal.'),
      'calls' => array(
        array('setContainer', array('@service_container')),
      ),
      'tags' => array(
        array('ctools.plugin', array(
          'owner' => 'render_cache',
          'type' => 'ValidationStrategy',
          'prefix' => 'render_cache.validation_strategy.internal.')
        ),
      ),
    );

    // Services provided normally by user.module.
    $services['cache_context.user'] = array(
      'class' => '\Drupal\render_cache\Cache\UserCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );
    $services['cache_context.user.roles'] = array(
      'class' => '\Drupal\render_cache\Cache\UserRolesCacheContext',
      'tags' => array(
        array('cache.context'),
      ),
    );

    return array(
      'parameters' => $parameters,
      'services' => $services,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterContainerDefinition(&$container_definition) {
    // Register cache contexts parameter in the container.
    $container_definition['parameters']['cache_contexts'] = array_keys($container_definition['tags']['cache.context']);

    // Register ctools plugins as private services in the container.
    foreach ($container_definition['tags']['ctools.plugin'] as $service => $tags) {
      foreach ($tags as $tag) {
        $discovery = new CToolsPluginDiscovery($tag['owner'], $tag['type']);
        $definitions = $discovery->getDefinitions();
        foreach ($definitions as $key => $definition) {
          // If arguments are not set, pass the definition as plugin argument.
          if (!isset($definition['arguments'])) {
            $definition['arguments'] = array($definition);
          }
          $container_definition['services'][$tag['prefix'] . $key] = $definition + array('public' => FALSE);
        }
      }
    }
  }
}
