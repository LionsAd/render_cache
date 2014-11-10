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

  protected $cacheHitData;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $data_raw = array(
      'foo' => array(
        '#markup' => 'foo',
      ),
      'bar' => array(
        '#markup' => 'bar',
      ),
      'baz' => array(
        'lama' => array(
           '#markup' => 'baz',
         ),
      ),
    );
    $data = $data_raw;
    $data['#cache'] = array(
      'tags' => array('bar:1')
    );
    $data['foo']['#cache'] = array(
      'tags' => array('foo:1')
    );
    $data['baz']['lama']['#cache'] = array(
      'tags' => array('lama:1')
    );

    $this->cacheHitData = (object) array(
      'data' => $data,
    );

    $properties = array(
      '#cache' => array(
        'tags' => array(
          'bar:1',
          'foo:1',
          'lama:1',
        ),
      )
    );
    $preserved = array( 'baz' => $data['baz'] );

    $this->cacheHitRendered = 'foobarbaz';
    $this->cacheHitNoRender = $data_raw + $properties;

    $this->cacheHitLateRender = $data_raw + $properties + array(
     '#attached' => array(
       'render_cache' => $properties + $preserved,
     ),
   );
    $this->cacheHitLateRender['#cache']['cid'] = 'render:foo:late';

    $this->cacheHitRenderDirect = array(
      '#markup' => $this->cacheHitRendered,
      '#attached' => array(),
    ) + $properties + $preserved;

    // @todo This should use more mocked methods.
    $stack = Mockery::mock('\Drupal\render_cache\Cache\RenderStack[render,collectAttached]');

    // @todo Still need to implement those.
    $stack->shouldReceive('render')
      ->andReturn($this->cacheHitRendered);
    $stack->shouldReceive('collectAttached')
      ->andReturn($this->cacheHitLateRender['#attached']);

    $cache_bin = Mockery::mock('\DrupalCacheInterface');
    // Cache hit
    $cache_bin->shouldReceive('getMultiple')
      ->with(
        Mockery::on(function(&$cids) {
          $cid = reset($cids);
          if ($cid == 'render:foo:exists') {
            $cids = array();
            return TRUE;
          }
          return FALSE;
        })
      )
      ->andReturn(
        array('render:foo:exists' => $this->cacheHitData)
      );

    // Cache miss
    $cache_bin->shouldReceive('getMultiple')
      ->with(
        Mockery::on(function(&$cids) {
          $cid = reset($cids);
          if ($cid == 'render:foo:not_exists') {
            return TRUE;
          }
          return FALSE;
        })
      )
      ->andReturn(
        array(
          // This is test only and will never happen with D7 backend.
          'render:foo:not_exists' => FALSE,
        )
      );

    $cache_bin->shouldReceive('set')
      ->with('render:foo:bar', Mockery::on(function($data) { return TRUE; }), -1);

    $cache_bin->shouldReceive('set')
      ->with('render:foo:no', Mockery::on(function($data) { return TRUE; }), -1);

    $cache_bin->shouldReceive('set')
      ->with('render:foo:late', Mockery::on(function($data) { return TRUE; }), -1);

    $cache_bin->shouldReceive('set')
      ->with('render:foo:direct', Mockery::on(function($data) { return TRUE; }), -1);

    $cache = Mockery::mock('\Drupal\render_cache\Cache\RenderCacheBackendAdapter[cache]', array($stack));
    $cache->shouldReceive('cache')
      ->once()
      ->andReturn($cache_bin);
    $this->cache = $cache;
  }
  
  /**
   * Tests that RenderCacheBackendAdapter::get() is working properly.
   */
  public function test_get_hit() {
    $cache_info = $this->getCacheInfo('render:foo:exists', RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER);
    $this->assertEquals($this->cacheHitData->data, $this->cache->get($cache_info), 'Cache Hit Data matches for ::get()');
  }

  public function test_get_miss() {
    $cache_info = $this->getCacheInfo('render:foo:not_exists', RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER);
    $this->assertEquals(array(), $this->cache->get($cache_info), 'Cache Miss data matches for ::get()');
  }


  /**
   * Tests that RenderCacheBackendAdapter::set() is working properly.
   */
  public function test_set() {
    $cache_info = $this->getCacheInfo('render:foo:bar', RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER);
    // Also test that there are no properties to preserve.
    $cache_info['render_cache_preserve_properties'] = array();
    $render = $this->cacheHitData->data;
    $this->cache->set($render, $cache_info);
    $this->assertEquals($this->cacheHitNoRender, $render, 'Data is the same for no render strategy');
  }

  /**
   * Tests that RenderCacheBackendAdapter::set() throwing an exception.
   * @expectedException \RunTimeException
   */
  public function test_set_exception() {
    $cache_info = $this->getCacheInfo('render:foo:bar', -1);
    $render = array();
    // Ensure cache() was called one times ...
    $this->cache->get($cache_info);
    // ... because this throws an exception.
    $this->cache->set($render, $cache_info);
  }


  /**
   * Tests that RenderCacheBackendAdapter::getMultiple() is working properly.
   */
  public function test_getMultiple() {
    $cache_info_map = array(
      '42' => $this->getCacheInfo('render:foo:exists', RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER),
      '23' => $this->getCacheInfo('render:foo:not_exists', RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER),
    );
    $this->assertEquals(array('42' => $this->cacheHitData->data), $this->cache->getMultiple($cache_info_map), 'Cache data matches for ::getMultiple()');
  }
  
  /**
   * Tests that RenderCacheBackendAdapter::setMultiple() is working properly.
   */
  public function test_setMultiple() {
    // @todo consider using a data provider instead.
    $cache_info_map = array(
      'no' => $this->getCacheInfo('render:foo:no', RenderCache::RENDER_CACHE_STRATEGY_NO_RENDER),
      'late' => $this->getCacheInfo('render:foo:late', RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER),
    );

    $build = array();
    foreach ($cache_info_map as $id => $cache_info) {
      $build[$id] = $this->cacheHitData->data;
    }
    $this->cache->setMultiple($build, $cache_info_map);
    $this->assertEquals($this->cacheHitNoRender, $build['no'], 'Data is the same for no render strategy');
    $this->assertEquals($this->cacheHitLateRender, $build['late'], 'Data is the same for late render strategy');
  }

  /**
   * Tests that RenderCacheBackendAdapter::setMultiple() for direct strategy.
   */
  public function test_setMultiple_direct() {
    // @todo consider using a data provider instead.
    $cache_info_map = array(
      'direct' => $this->getCacheInfo('render:foo:direct', RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER),
      'late' => $this->getCacheInfo('render:foo:late', RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER),
    );

    $build = array();
    foreach ($cache_info_map as $id => $cache_info) {
      $build[$id] = $this->cacheHitData->data;
    }
    $this->cache->setMultiple($build, $cache_info_map);
    $this->assertEquals($this->cacheHitRenderDirect, $build['direct'], 'Data is the same for direct render strategy');
    $this->assertEquals($this->cacheHitLateRender, $build['late'], 'Data is the same for late render strategy');
  }

  protected function getCacheInfo($cid, $strategy) {
    return array(
      'cid' => $cid,
      'keys' => array('render', 'foo','bar'), 
      'render_cache_ignore_request_method_check' => FALSE,
      'render_cache_cache_strategy' => $strategy,
      'render_cache_preserve_properties' => array('baz'),
    );
  }
}
