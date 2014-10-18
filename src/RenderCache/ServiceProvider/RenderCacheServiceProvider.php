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
    $parameters['some_config'] = 'foo';
    $parameters['some_other_config'] = 'kitten';

    $services = array();
    $services['some_service'] = array(
      'class' => '\Drupal\render_cache\Service\SomeService',
      'arguments' => array('@container', '%some_config'),
      'calls' => array('setContainer', array('@container')),
      'tags' => array(
        array('service' => array()),
      ),
      'priority' => 0,
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
    $container_definition['services']['some_service']['tags'][] = array('bar' => array());
    $container_definition['services']['some_service']['tags'][] = array('baz' => array());
    $container_definition['parameters']['some_other_config'] = 'lama';
  }
}
