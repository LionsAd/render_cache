<?php

/**
 * @file
 * Contains \Drupal\render_cache\DependencyInjection\ContainerAwareInterface
 */

namespace Drupal\render_cache\DependencyInjection;

/**
 * ContainerAwareInterface should be implemented by classes that depend on a Container.
 *
 * @ingroup dic
 */
interface ContainerAwareInterface {

  /**
   * Sets the Container associated with this service.
   *
   * @param ContainerInterface|null $container
   *   A ContainerInterface instance or NULL to be injected in the service.
   */
  public function setContainer(ContainerInterface $container = NULL);
}
