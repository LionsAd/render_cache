<?php
/**
 * @file
 * Contains \Drupal\render_cache\DependencyInjection\ServiceProviderPluginManager
 */

namespace Drupal\render_cache\DependencyInjection;

use Drupal\Component\Plugin\PluginManagerBase;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\render_cache\Plugin\Discovery\CToolsPluginDiscovery;
use Drupal\render_cache\Plugin\DefaultPluginManager;

/**
 * Defines a plugin manager used for discovering container service definitions.
 */
class ServiceProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ServiceProviderPluginManager object.
   *
   * This uses ctools for discovery of render_cache ServiceProvider objects.
   */
  public function __construct() {
   $discovery = new CToolsPluginDiscovery('render_cache', 'ServiceProvider');
   parent::__construct($discovery);
  }
}
