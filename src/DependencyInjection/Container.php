<?php
/**
 * @file
 * Contains \Drupal\render_cache\DependencyInjection\Container
 */

namespace Drupal\render_cache\DependencyInjection;

/**
 * Container is a DI container that provides services to users of the class.
 *
 * @ingroup dic
 */
class Container implements ContainerInterface {

  /**
   * The parameters of the container.
   *
   * @var array
   */
  protected $parameters = array();

  /**
   * The service definitions of the container.
   *
   * @var array
   */
  protected $serviceDefinitions = array();

  /**
   * The instantiated services.
   *
   * @var array
   */
  protected $services = array();
  
  public function __construct(array $container_definition) {
    $this->parameters = $container_definition['parameters'];
    $this->serviceDefinitions = $container_definition['services'];
  }

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    $definition = $this->getDefinition($name);
    return new $definition['class']();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    $definition = isset($this->serviceDefinitions[$plugin_id]) ? $this->serviceDefinitions[$plugin_id] : NULL;

    if (!$definition && $exception_on_invalid) {
      throw new PluginNotFoundException($plugin_id, sprintf('The "%s" plugin does not exist.', $plugin_id));
    }

    return $definition;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    return $this->serviceDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function hasDefinition($plugin_id) {
    return (bool) $this->getDefinition($plugin_id, FALSE);
  }
}
