<?php
/**
 * @file
 * Contains \Drupal\render_cache\DependencyInjection\ContaineInterface
 */

namespace Drupal\render_cache\DependencyInjection;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;

/**
 * Simple DI Container Interface used to get services and discover definitions.
 *
 * @ingroup dic
 */
interface ContainerInterface extends DiscoveryInterface {

  /**
   * Returns a service from the container.
   *
   * @param string $name
   *   The name of the service to retrieve.
   *
   * @return object
   *   Returns the object that provides the service.
   */
  public function get($name);
}
