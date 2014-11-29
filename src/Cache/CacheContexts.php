<?php
/**
 * @file
 * Contains \Drupal\render_cache\Cache\CacheContexts
 */

namespace Drupal\render_cache\Cache;

use Drupal\service_container\DependencyInjection\ContainerInterface;

use Drupal\Core\Cache\CacheContexts as DrupalCacheContexts;

/**
 * Defines the CacheContexts service.
 *
 * Provides CacheContexts service using the render_cache container.
 *
 * Note: The original Drupal 8 CacheContexts service uses the Symfony
 *       ContainerInterface, which is not available here. So we over-
 *       write the constructor to enable us to use the functionality of the
 *       stock file.
 *
 * @ingroup cache
 */
class CacheContexts extends DrupalCacheContexts {

  /**
   * The service container.
   *
   * @var \Drupal\service_container\DependencyInjection\ContainerInterface
   */
  protected $container;

 /**
   * Constructs a CacheContexts object.
   *
   * @param \Drupal\service_container\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param array $contexts
   *   An array of key-value pairs, where the keys are service names (which also
   *   serve as the corresponding cache context token) and the values are the
   *   cache context labels.
   */
  public function __construct(ContainerInterface $container, array $contexts) {
    $this->container = $container;
    $this->contexts = $contexts;
  }
}
