<?php

/**
 * @file
 * Contains \Drupal\render_cache\ServiceContainer\ServiceProvider\RenderCacheServiceProvider
 */

namespace Drupal\render_cache\ServiceContainer\ServiceProvider;

use Drupal\service_container\DependencyInjection\ServiceProviderInterface;

/**
 * Provides render cache service definitions.
 *
 * @codeCoverageIgnore
 */
class RenderCacheServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getContainerDefinition() {
    $parameters = array();
    $parameters['cache_contexts'] = array();
    $parameters['service_container.static_event_listeners'] = array('RenderCache');

    $services = array();

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
        array('name' => 'cache.context'),
      ),
    );
    $services['cache_context.language'] = array(
      'class' => '\Drupal\render_cache\Cache\LanguageCacheContext',
      'tags' => array(
        array('name' => 'cache.context'),
      ),
    );
    $services['cache_context.theme'] = array(
      'class' => '\Drupal\render_cache\Cache\ThemeCacheContext',
      'tags' => array(
        array('name' => 'cache.context'),
      ),
    );
    $services['cache_context.theme'] = array(
      'class' => '\Drupal\render_cache\Cache\TimezoneCacheContext',
      'tags' => array(
        array('name' => 'cache.context'),
      ),
    );
    // Render Stack
    $services['render_stack'] = array(
      'class' => '\Drupal\render_cache\Cache\RenderStack',
    );
    $services['render_cache.cache'] = array(
      'class' => '\Drupal\render_cache\Cache\RenderCacheBackendAdapter',
      'arguments' => array('@render_stack'),
    );

    // Services provided normally by user.module.
    $services['cache_context.user'] = array(
      'class' => '\Drupal\render_cache\Cache\UserCacheContext',
      'tags' => array(
        array('name' => 'cache.context'),
      ),
    );
    $services['cache_context.user.roles'] = array(
      'class' => '\Drupal\render_cache\Cache\UserRolesCacheContext',
      'tags' => array(
        array('name' => 'cache.context'),
      ),
    );


    $parameters['ctools_plugins_auto_discovery']['render_cache'] = TRUE;

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
  }
}
