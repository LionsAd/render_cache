<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\CacheContextsTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\render_cache\Cache\CacheContexts;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Cache\CacheContexts
 * @group cache
 */
class CacheContextsTest extends \PHPUnit_Framework_TestCase {

  /**
   * The cache contexts service.
   *
   * @var \Drupal\render_cache\Cache\CacheContexts
   */
  protected $cacheContexts;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    // Setup a foo cache context.
    $foo_cache_context = Mockery::mock('\Drupal\Core\Cache\CacheContextInterface');
    $foo_cache_context->shouldReceive('getLabel')
      ->andReturn('Foo');
    $foo_cache_context->shouldReceive('getContext')
      ->andReturn('bar');

    // Setup a mock container.
    $container = Mockery::mock('\Drupal\render_cache\DependencyInjection\Container');
    $container->shouldReceive('get')
      ->once()
      ->with('cache_context.foo')
      ->andReturn($foo_cache_context);

    $this->cacheContexts = new CacheContexts($container, array('cache_context.foo'));
  }

  /**
   * Tests that CacheContexts::getAll() is working properly.
   */
  public function test_getAll() {
    $this->assertEquals(array('cache_context.foo'), $this->cacheContexts->getAll(), 'Cache Contexts service contains the right services.');
  }

  /**
   * Tests that CacheContexts::getLabels() is working properly.
   */
  public function test_getLabels() {
    $this->assertEquals(array('cache_context.foo' => 'Foo'), $this->cacheContexts->getLabels(), 'Cache Contexts service retrieves the right labels.');
  }

  /**
   * Tests that CacheContexts::convertTokensToKeys() is working properly.
   */
  public function test_convertTokensToKeys() {
    $tokens = array('foo', 'bar', 'cache_context.foo');
    $altered_tokens = array('foo', 'bar', 'bar');
    $this->assertEquals($altered_tokens, $this->cacheContexts->convertTokensToKeys($tokens), 'Cache Contexts can be converted properly.');
  }
}
