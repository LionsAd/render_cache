<?php

interface RenderCachePluginInterface {
  /**
   * Public constructor.
   *
   * @param $plugin
   *   The plugin associated with this class.
   */
  public function __construct($plugin);

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

class RenderCachePluginBase implements RenderCachePluginInterface {
  /**
   * The plugin array from the plugin class's associated .inc file.
   *
   * @var array
   */
  protected $plugin = array();

  /**
   * The type this plugin implements.
   *
   * @var string
   */
  protected $type = array();

  /**
   * {@inheritdoc}
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
