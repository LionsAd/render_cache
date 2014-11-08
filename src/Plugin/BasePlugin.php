<?php
/**
 * @file
 * Contains \Drupal\render_cache\Plugin\BasePlugin;
 */

namespace Drupal\render_cache\Plugin;

/**
 * Defines a base class for render cache plugin objects.
 *
 * @ingroup rendercache
 */
class BasePlugin implements PluginInterface {
  /**
   * The plugin array from the plugin class's associated .inc file.
   *
   * @var array
   */
  protected $plugin;

  /**
   * The type this plugin implements.
   *
   * @var string
   */
  protected $type;

  /**
   * Public constructor.
   *
   * @param array $plugin
   *   The plugin associated with this class.
   */
  public function __construct($plugin) {
    $this->plugin = $plugin;
    $this->type = $plugin['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin() {
    return $this->plugin;
  }
}
