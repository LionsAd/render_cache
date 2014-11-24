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
    $stack = Mockery::mock('\Drupal\render_cache\Cache\RenderStack[drupalRender,collectAttached]');
    $this->renderStack = $stack;
  }

  /**
   * Tests that RenderStack::getCacheKeys() is working properly.
   * @covers ::getCacheKeys()
   */
  public function test_getCacheKeys() {
    $this->assertEquals(array('render_cache', 'foo'), $this->renderStack->getCacheKeys());
  }

  /**
   * Tests that RenderStack::getCacheTags() is working properly.
   * @covers ::getCacheTags()
   */
  public function test_getCacheTags() {
    $this->assertEquals(array(array('node' => 1)), $this->renderStack->getCacheTags());
  }

  /**
   * Tests that RenderStack::getCacheMaxAge() is working properly.
   * @covers ::getCacheMaxAge()
   */
  public function test_getCacheMaxAge() {
    $this->assertEquals(600, $this->renderStack->getCacheMaxAge());
  }

  /**
   * Tests that RenderStack::isCacheable() is working properly.
   * @covers ::isCacheable()
   */
  public function test_isCacheable() {
    $this->assertEquals(TRUE, $this->renderStack->isCacheable());
  }

  /**
   * Basic tests for recursive functions.
   *
   * @covers ::increaseRecursion()
   * @covers ::decreaseRecursion()
   * @covers ::getRecursionLevel()
   * @covers ::isRecursive()
   */
  public function test_basicRecursion() {
    $this->assertFalse($this->renderStack->isRecursive(), 'isRecursive() is FALSE at the beginning.');
    $this->renderStack->increaseRecursion();
    $this->assertTrue($this->renderStack->isRecursive(), 'isRecursive() is TRUE after increase.');
    $this->assertEquals(1, $this->renderStack->getRecursionLevel(), 'Recursion Level is 1 after increase.');
    $this->renderStack->decreaseRecursion();
    $this->assertFalse($this->renderStack->isRecursive(), 'isRecursive() is FALSE at the end.');
    $this->assertEquals(0, $this->renderStack->getRecursionLevel(), 'Recursion Level is 0 after increase and decrease.');
  }

  /**
   * @covers ::getRecursionStorage()
   * @covers ::setRecursionStorage()
   * @covers ::addRecursionStorage()
   */
  public function test_getsetRecursionStorage() {
    $attached = array();
    $attached['css'][] = 'foo.css';
    $attached['js'][] = 'bar.js';
    $attached['library'][] = array('baz_module', 'lama');

    $attached2 = array();
    $attached2['css'][] = 'lama2.css';

    $attached_combined = $attached;
    $attached_combined['css'][] = 'lama2.css';

    $recursion_storage_test_1_set = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
          'rendered',
        ),
        '#tags' => array(
          'invalid',
        ),
      ),
      '#attached' => $attached,
    );
    $recursion_storage_test_1 = $recursion_storage_test_1_set;
    unset($recursion_storage_test_1['#cache']['#tags']);

    $recursion_storage_test_2 = array(
      '#cache' => array(
        'tags' => array(
          'foo:42',
          'zoo:1',
        ),
      ),
      '#attached' => $attached2,
    );
    $recursion_storage_test_combined = array(
      '#cache' => array(
        'tags' => array(
          'foo:42',
          'node:1',
          'rendered',
          'zoo:1',
        ),
      ),
      '#attached' => $attached_combined,
    );
    $this->renderStack
      ->shouldReceive('collectAttached')
      ->times(8)
      ->andReturnUsing(function($render) use ($attached_combined) {
        if (!empty($render['#attached']) && !empty($render[0]['#attached'])) {
          return $attached_combined;
        }
        if (!empty($render['#attached'])) {
          return $render['#attached'];
        }
        if (!empty($render[0]['#attached'])) {
          return $render[0]['#attached'];
        }

        return array();
      });


    $this->assertEmpty($this->renderStack->getRecursionStorage(), 'getRecursionStorage() is empty() at the beginning.');
    $this->renderStack->increaseRecursion();
    $this->assertEmpty($this->renderStack->getRecursionStorage(), 'getRecursionStorage() is empty() at the beginning for level 1.');
    $this->assertEquals(1, $this->renderStack->getRecursionLevel(), 'Recursion Level is 1 after increase.');
    $this->renderStack->setRecursionStorage($recursion_storage_test_1_set);
    $this->assertEquals($recursion_storage_test_1, $this->renderStack->getRecursionStorage(), 'getRecursionStorage() matches what was set.');
    $this->renderStack->increaseRecursion();
    $this->assertEmpty($this->renderStack->getRecursionStorage(), 'getRecursionStorage() is empty() at the beginning for level 2.');
    $copy = $recursion_storage_test_2;
    $this->renderStack->addRecursionStorage($copy, TRUE);
    unset($copy['#attached']);
    $this->assertEmpty($copy, 'addRecursionStorage() makes argument empty() after adding of storage (except for attached).');
    $this->assertEquals($recursion_storage_test_2, $this->renderStack->getRecursionStorage(), 'getRecursionStorage() matches what was added.');
    $storage = $this->renderStack->decreaseRecursion();
    $this->assertEquals($recursion_storage_test_2, $storage, 'decreaseRecursion() matches what was added.');
    $this->assertEquals($recursion_storage_test_1, $this->renderStack->getRecursionStorage(), 'getRecursionStorage() matches what was set.');
    $this->renderStack->addRecursionStorage($storage, TRUE);
    $storage2 = $this->renderStack->getRecursionStorage();
    $storage = $this->renderStack->decreaseRecursion();
    $this->assertEquals($recursion_storage_test_combined, $storage, 'decreaseRecursion() matches what was added combined.');
    $this->assertEmpty($this->renderStack->getRecursionStorage(), 'getRecursionStorage() is empty() at the end.');
  }

  /**
   * @covers ::getRecursionStorage()
   * @covers ::addRecursionStorage()
   */
  public function test_addRecursionStorage() {
    $this->renderStack->increaseRecursion();
    $this->renderStack->decreaseRecursion();
  }

  /**
   * Tests that RenderStack::convertRenderArrayToD7() is working properly.
   * @covers ::convertRenderArrayToD7()
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
   * @covers ::convertRenderArrayFromD7()
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
