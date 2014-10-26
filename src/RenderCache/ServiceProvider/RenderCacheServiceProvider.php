<?php

/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\ServiceProvider\RenderCacheServiceProvider
 */

namespace Drupal\render_cache\RenderCache\ServiceProvider;

use Drupal\render_cache\DependencyInjection\ServiceProviderInterface;

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
  }
}
