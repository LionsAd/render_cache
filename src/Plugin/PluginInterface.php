<?php
/**
 * @file
 * Contains \Drupal\render_cache\Plugin\PluginInterface;
 */

namespace Drupal\render_cache\Plugin;

/**
 * Defines an interface for render cache plugin objects.
 *
 * @ingroup rendercache
 */
interface PluginInterface {
  /**
   * Returns the plugin associated with this class.
   *
   * @return array
   *   The plugin array from the plugin class's associated .inc file.
   */
  public function getPlugin();

  /**
   * Returns the type this plugin implements.
   *
   * @return string
   *   The type this plugin implements.
   */
  public function getType();
}
