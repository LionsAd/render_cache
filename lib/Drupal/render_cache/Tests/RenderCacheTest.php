<?php
/**
 * @file
 * Contains \Drupal\render_cache\Tests\RenderCacheTest.
 */
namespace Drupal\render_cache\Tests;

/**
 * Tests the RenderCache implementation of the render_cache module.
 */
class RenderCacheTest extends \DrupalWebTestCase {
  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'RenderCache',
      'description' => 'Tests the generic RenderCache functionality.',
      'group' => 'render_cache',
    );
  }

  protected function setUp() {
    parent::setUp(array('render_cache', 'render_cache_block'));

    \ServiceContainer::init();
    $this->container = \Drupal::getContainer();
  }

  /**
   * The basic functionality of the RenderCache class.
   */
  public function testRenderCache() {
    $recursive = \RenderCache::isRecursive();
    $this->assertFalse($recursive, "Render Stack is not recursive at the start.");
  }
}

