<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Plugin\BasePluginTest
 */

namespace Drupal\render_cache\Tests\Plugin;

use Drupal\render_cache\Plugin\BasePlugin;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Plugin\ContainerAwarePluginManager
 * @group dic
 */
class BasePluginTest extends \PHPUnit_Framework_TestCase {
 
  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->basePlugin = new BasePlugin(array('name' => 'foo'));
  }

  /**
   * Tests that BasePlugin::getType() works properly.
   */
  public function test_getType() {
    $this->assertEquals('foo', $this->basePlugin->getType());
  }

  /**
   * Tests that BasePlugin::getPlugin() works properly.
   */
  public function test_getPlugin() {
    $this->assertEquals(array('name' => 'foo'), $this->basePlugin->getPlugin());
  }
}
