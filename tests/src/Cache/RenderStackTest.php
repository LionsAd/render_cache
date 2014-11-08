<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\RenderStackTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\render_cache\Cache\RenderStack;
use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Cache\RenderStack
 * @group cache
 */
class RenderStackTest extends \PHPUnit_Framework_TestCase {

  /**
   * The renderStack service.
   *
   * @var \Drupal\render_cache\Cache\RenderStack
   */
  protected $renderStack;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->renderStack = new RenderStack();
  }
  
  /**
   * Tests that RenderStack::getCacheKeys() is working properly.
   */
  public function test_getCacheKeys() {
    $this->assertEquals(array('render_cache', 'foo'), $this->renderStack->getCacheKeys());
  }

  /**
   * Tests that RenderStack::getCacheTags() is working properly.
   */
  public function test_getCacheTags() {
    $this->assertEquals(array(array('node' => 1)), $this->renderStack->getCacheTags());
  }

  /**
   * Tests that RenderStack::getCacheMaxAge() is working properly.
   */
  public function test_getCacheMaxAge() {
    $this->assertEquals(600, $this->renderStack->getCacheMaxAge());
  }
  
  /**
   * Tests that RenderStack::isCacheable() is working properly.
   */
  public function test_isCacheable() {
    $this->assertEquals(TRUE, $this->renderStack->isCacheable());
  }

  /**
   * Tests that RenderStack::convertRenderArrayToD7() is working properly.
   */
  public function test_convertRenderArrayToD7() {
    $render = array(
      '#cache' => array(
        'tags' => array(
          array('node' => 1),
        ),
        'max-age' => 600,
      ),
      '#attached' => array(
        'css' => 'test.css',
      ),
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );
    $render_result = array(
      '#attached' => array(
        'css' => 'test.css',
      ),
    );
    $render_result['#attached']['render_cache'] = array(
      '#cache' => array(
        'tags' => array(
          array('node' => 1),
        ),
        'max-age' => 600,
      ),

      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );

    $this->assertEquals($render_result, $this->renderStack->convertRenderArrayToD7($render));
  }

  /**
   * Tests that RenderStack::convertRenderArrayFromD7() is working properly.
   */
  public function test_convertRenderArrayFromD7() {
    $render = array(
      '#cache' => array(
        'tags' => array(
          array('node' => 1),
        ),
        'max-age' => 600,
      ),
      '#attached' => array(
        'css' => 'test.css',
      ),
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );
    $render_result = array(
      '#attached' => array(
        'css' => 'test.css',
      ),
    );
    $render_result['#attached']['render_cache'] = array(
      '#cache' => array(
        'tags' => array(
          array('node' => 1),
        ),
        'max-age' => 600,
      ),

      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );

    $this->assertEquals($render, $this->renderStack->convertRenderArrayFromD7($render_result));
  }
}
