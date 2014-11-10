<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\RenderCacheBackendAdapterTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\render_cache\Cache\RenderCacheBackendAdapter;
use Drupal\render_cache\Cache\RenderStack;
use DrupalCacheInterface;
use RenderCache;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Cache\RenderCacheBackendAdapter
 * @group cache
 */
class RenderCacheBackendAdapterTest extends \PHPUnit_Framework_TestCase {

  /**
   * The cache backend adapter service.
   *
   * @var \Drupal\render_cache\Cache\RenderCacheBackendAdapter
   */
  protected $cache;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $stack = new RenderStack(); 

    $cache_bin = Mockery::mock('\DrupalCacheInterface');
    $cache_bin->shouldReceive('getMultiple')
      ->with(
        Mockery::on(function(&$cids) {
          return TRUE;
        })
      )
      ->andReturn(array());
    $cache_bin->shouldReceive('set')
      ->with('render:foo:bar', Mockery::on(function($data) { return TRUE; }), -1);

    $cache = Mockery::mock('\Drupal\render_cache\Cache\RenderCacheBackendAdapter[cache]', array($stack));
    $cache->shouldReceive('cache')
      ->once()
      ->andReturn($cache_bin);
    $this->cache = $cache;
  }
  
  /**
   * Tests that RenderCacheBackendAdapter::get() is working properly.
   */
  public function test_get() {
    $cache_info = $this->getCacheInfo(RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER);
    $this->cache->get($cache_info);
  }

  /**
   * Tests that RenderCacheBackendAdapter::set() is working properly.
   */
  public function test_set() {
    $cache_info = $this->getCacheInfo(RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER);
    $render = array();
    $this->cache->set($render, $cache_info);
  }

  /**
   * Tests that RenderCacheBackendAdapter::getMultiple() is working properly.
   */
  public function test_getMultiple() {
    $cache_info_map = array(
      '42' => $this->getCacheInfo(RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER),
    );
    $this->cache->getMultiple($cache_info_map);
  }
  
  /**
   * Tests that RenderCacheBackendAdapter::setMultiple() is working properly.
   */
  public function test_setMultiple() {
    $cache_info_map = array(
      '42' => $this->getCacheInfo(RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER),
    );
    $build = array(
      '42' => array(),
    );
    $this->cache->setMultiple($build, $cache_info_map);
  }

  protected function getCacheInfo($strategy) {
    return array(
      'cid' => 'render:foo:bar',
      'keys' => array('render', 'foo','bar'), 
      'render_cache_ignore_request_method_check' => FALSE,
      'render_cache_cache_strategy' => $strategy,
      'render_cache_preserve_properties' => array(),
    );
  }
}
