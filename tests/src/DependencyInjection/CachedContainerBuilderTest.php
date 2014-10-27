<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\DependencyInjection\CachedContainerBuilderTest
 */

namespace Drupal\render_cache\Tests\DependencyInjection;

use Drupal\render_cache\DependencyInjection\ContainerBuilder;
use Drupal\render_cache\DependencyInjection\ContainerInterface;
use Drupal\render_cache\DependencyInjection\ServiceProviderInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\DependencyInjection\CachedContainerBuilder
 * @group dic
 */
class CachedContainerBuilderTest extends \PHPUnit_Framework_TestCase {
 
  /**
   * @var \DrupalCacheInterface
   */
  protected $cache;

  /**
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $serviceProviderManager;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $fake_definition = $this->getFakeContainerDefinition();

    // Setup the serviceProviderManager that returns no services.
    $service_provider_manager = Mockery::mock('\Drupal\Component\Plugin\PluginManagerInterface', array(
      'getDefinitions' => array(),
      'getDefinition' => array(),
      'hasDefinition' => FALSE,
      'createInstance' => NULL,
      'getInstance' => NULL,
    ));

    $this->serviceProviderManager = $service_provider_manager;

    // Setup the 'cache' bin.
    $cache = Mockery::mock('alias:DrupalCacheInterface');
    $cache->shouldReceive('get')
      ->with('render_cache:container_definition')
      ->once()
      ->andReturn((object) array('data' => $fake_definition));
    $cache->shouldReceive('get')
      ->with('render_cache:miss_container_definition')
      ->once()
      ->andReturn(FALSE, TRUE);
    $cache->shouldReceive('set');

    $this->cache  = $cache;
  }

  /**
   * Tests that CachedContainerBuilder::isCached() works properly.
   */
  public function test_isCached() {
    // It is cached.
    $cached_container_builder = $this->getCachedContainerBuilderMock('render_cache:container_definition');
    $this->assertTrue($cached_container_builder->isCached(), 'CachedContainerBuilder is cached.');

    // It is not cached.
    $uncached_container_builder = $this->getCachedContainerBuilderMock('render_cache:miss_container_definition');
    $this->assertFalse($uncached_container_builder->isCached(), 'CachedContainerBuilder is not cached.');
  }


  /**
   * Tests that CachedContainerBuilder::getContainerDefinition() works properly.
   */
  public function test_getContainerDefinition() {
    $fake_definition = $this->getFakeContainerDefinition();

    // It is cached.
    $cached_container_builder = $this->getCachedContainerBuilderMock('render_cache:container_definition');
    $this->assertEquals($fake_definition, $cached_container_builder->getContainerDefinition(), 'CachedContainerBuilder definition matches when cached.');

    // It is not cached.
    $uncached_container_builder = $this->getCachedContainerBuilderMock('render_cache:miss_container_definition');
    $this->assertEquals($fake_definition, $uncached_container_builder->getContainerDefinition(), 'CachedContainerBuilder definition matches when not cached.');
  }

  public function test_isCached_getContainerDefinition() {
    $fake_definition = $this->getFakeContainerDefinition();

    // Due to the nature of the isCached() functionality, here are some extra
    // tests to ensure the cached data is stored correctly.

    // It is cached, but isCached() was called before.
    $cached_container_builder = $this->getCachedContainerBuilderMock('render_cache:container_definition');
    $this->assertTrue($cached_container_builder->isCached(), 'CachedContainerBuilder is cached.');
    $this->assertEquals($fake_definition, $cached_container_builder->getContainerDefinition(), 'CachedContainerBuilder definition matches when cached.');

    // It is not cached, but isCached() was called before and then its cached.
    $uncached_container_builder = $this->getCachedContainerBuilderMock('render_cache:miss_container_definition');
    $this->assertFalse($uncached_container_builder->isCached(), 'CachedContainerBuilder is not cached.');
    $this->assertEquals($fake_definition, $uncached_container_builder->getContainerDefinition(), 'CachedContainerBuilder definition matches when not cached.');
    $this->assertTrue($uncached_container_builder->isCached(), 'CachedContainerBuilder is now cached.');
  }

  protected function getCachedContainerBuilderMock($cid) {
    $fake_definition = $this->getFakeContainerDefinition();

    $container_builder = Mockery::mock('\Drupal\render_cache\DependencyInjection\CachedContainerBuilder[getCacheId,moduleAlter]', array($this->serviceProviderManager, $this->cache));
    $container_builder->shouldAllowMockingProtectedMethods();

    $container_builder->shouldReceive('getCacheId')
      ->andReturn($cid);
    $container_builder->shouldReceive('moduleAlter')
      ->with(
        Mockery::on(function(&$container_definition) use ($fake_definition) {
          $container_definition['parameters'] = $fake_definition['parameters'];
          $container_definition['services'] = $fake_definition['services'];
          return TRUE;
        })
      );

    return $container_builder;
  }

  /**
   * Returns a fake container definition used for testing.
   *
   * @return array
   *   The fake container definition with services and parameters.
   */
  protected function getFakeContainerDefinition() {
    $parameters = array();
    $parameters['some_config'] = 'foo';
    $parameters['some_other_config'] = 'kitten';

    $services = array();
    $services['container'] = array(
      'class' => '\Drupal\render_cache\DependencyInjection\Container',
      'tags' => array(
        array('tagged-service'),
      ),
    );
    $services['some_service'] = array(
      'class' => '\Drupal\render_cache\Service\SomeService',
      'arguments' => array('@service_container', '%some_config%'),
      'calls' => array(
        array('setContainer', array('@service_container')),
      ),
      'tags' => array(
        array('tagged-service'),
        array('another-tag', array('tag-value' => 42, 'tag-value2' => 23)),
      ),
      'priority' => 0,
    );

    return array(
      'parameters' => $parameters,
      'services' => $services,
    );
  }
}
