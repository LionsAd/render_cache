<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\RenderStackTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\Component\Utility\NestedArray;
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
    $stack = Mockery::mock('\Drupal\render_cache\Cache\RenderStack[drupalRender,collectAttached,callOriginalFunction]');
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
   * @covers ::supportsDynamicAssets()
   */
  public function test_supportsDynamicAssets() {
    $this->assertFalse($this->renderStack->supportsDynamicAssets(), 'supportsDynamicAssets() is FALSE by default.');
    $this->renderStack->supportsDynamicAssets(TRUE);
    $this->assertTrue($this->renderStack->supportsDynamicAssets(), 'supportsDynamicAssets() is TRUE after set.');
    $this->renderStack->supportsDynamicAssets(FALSE);
    $this->assertFalse($this->renderStack->supportsDynamicAssets(), 'supportsDynamicAssets() is FALSE after set.');
  }

  /**
   * @covers ::render()
   */
  public function test_render_common() {
    $storage = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
          'node:2',
        ),
        'max-age' => array(600),
        'downstream-ttl' => array(300),
      ),
      '#attached' => array(
        'library' => array(
          array('foo', 'bar'),
        ),
        'js' => array(
          'foo.js',
        ),
        'css' => array(
          'foo.css',
        ),
      ),
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );
    $render = array(
      '#markup' => 'foo',
      '#attached' => array(
        'library' => array(
          array('inner', 'baz'),
        ),
        'js' => array(
          'baz.js',
        ),
      ),
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
      ),
      'bar' => array(
        '#markup' => 'bar',
       ),
    );
    $original_render_result = array(
      '#markup' => 'foo',
      '#printed' => TRUE,
      'bar' => array(
        '#markup' => 'bar',
        '#printed' => TRUE,
       ),
      '#attached' => array(
        'library' => array(
          array('inner', 'baz'),
        ),
        'js' => array(
          'baz.js',
        ),
      ),
    );
    $render_result = array(
      '#markup' => 'foobar',
      '#attached' => array(
        'library' => array(
          array('foo', 'bar'),
          array('inner', 'baz'),
        ),
        'js' => array(
          'foo.js',
          'baz.js',
        ),
        'css' => array(
          'foo.css',
        ),
      ),
    ) + $storage;

    return array($storage, $render, $original_render_result, $render_result);
  }

  /**
   * @covers ::render()
   * @depends test_render_common
   */
  public function test_render($args) {
    list($storage, $render, $original_render_result, $render_result) = $args;

    $this->renderStack
      ->shouldReceive('collectAttached')
      ->times(4)
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $stack = $this->renderStack;

    $this->renderStack
      ->shouldReceive('drupalRender')
      ->with(Mockery::on(function(&$render) use ($stack) {
          $render['#printed'] = TRUE;
          $render['bar']['#printed'] = TRUE;

          // This is called by drupal_render() normally.
          if ($stack->supportsDynamicAssets()) {
            $stack->drupal_process_attached($render);
          }

          // Store and remove recursive storage.
          // for our properties.
          $stack->addRecursionStorage($render);

          return TRUE;
        }))
      ->once()
      ->andReturn('foobar');

    $render['x_render_cache_recursion_storage'] = $storage;
    list($markup, $original) = $this->renderStack->render($render);
    $this->assertEquals('foobar', $markup, 'Markup matches expected rendered data.');
    $this->assertEquals($original_render_result, $original, 'Original render array matches expected rendered data.');
    $this->assertEquals($render_result, $render, 'Render array matches expected rendered data.');
  }

  /**
   * @covers ::render()
   * @depends test_render_common
   */
  public function test_render_supportsDynamicAssets($args) {
    list($storage, $render, $original_render_result, $render_result) = $args;

    $this->renderStack
      ->shouldReceive('collectAttached')
      ->times(3)
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $stack = $this->renderStack;

    $this->renderStack
      ->shouldReceive('drupalRender')
      ->with(Mockery::on(function(&$render) use ($stack) {
          $render['#printed'] = TRUE;
          $render['bar']['#printed'] = TRUE;

          // This is called by drupal_render() normally.
          if ($stack->supportsDynamicAssets()) {
            $stack->drupal_process_attached($render);
          }

          // Store and remove recursive storage.
          // for our properties.
          $stack->addRecursionStorage($render);

          return TRUE;
        }))
      ->once()
      ->andReturn('foobar');

    $this->renderStack->supportsDynamicAssets(TRUE);

    $render['x_render_cache_recursion_storage'] = $storage;
    list($markup, $original) = $this->renderStack->render($render);
    $this->assertEquals('foobar', $markup, 'Markup matches expected rendered data.');
    $this->assertEquals($original_render_result, $original, 'Original render array matches expected rendered data.');
    $this->assertEquals($render_result, $render, 'Render array matches expected rendered data.');
  }

  /**
   * @covers ::collectAndRemoveAssets()
   */
  public function test_collectAndRemoveAssets() {
    $render = array();
    $render[] = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
      ),
    );
    $render[] = array(
      '#cache' => array(
        'max-age' => 600,
        'downstream-ttl' => 300,
      ),
    );
    $render[] = array(
      '#attached' => array(
        'css' => 'test.css',
      ),
    );
    $render[] = array(
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );
    $render[] = array(
      'child_1' => array(
        '#markup' => 'foo',
        '#cache' => array(
          'tags' => array(
            'node:2',
          ),
        ),
      ),
    );
    $render_result = array();
    $render_result[0] = array();
    $render_result[1] = array();
    $render_result[2] = array(
      '#attached' => array(
        'css' => 'test.css',
      ),
    );
    $render_result[3] = array();
    $render_result[4] = array(
      'child_1' => array(
        '#markup' => 'foo',
      ),
    );

    $storage = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
          'node:2',
        ),
        'max-age' => array(600),
        'downstream-ttl' => array(300),
      ),
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );

    $this->assertEquals($storage, $this->renderStack->collectAndRemoveAssets($render), 'Collected assets match after collectAndRemoveAssets.');
    $this->assertEquals($render_result, $render, 'Render array matches after collectAndRemoveAssets.');
  }

  /**
   * @covers ::collectAndRemoveAssets()
   */
  public function test_collectAndRemoveAssets_empty() {
    $render = array(
      'child_1' => array(
        '#markup' => 'foo'
      ),
    );
    $render_result = $render;

    $this->assertEmpty($this->renderStack->collectAndRemoveAssets($render), 'Collected assets match for empty case.');
    $this->assertEquals($render_result, $render, 'Render is unchanged for empty case.');
  }

  /**
   * @covers ::collectAndRemoveD8Properties()
   */
  public function test_collectAndRemoveD8Properties() {
    $render = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
        'max-age' => 600,
        'downstream-ttl' => 300,
      ),
      '#attached' => array(
        'css' => 'test.css',
      ),
      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
      'child_1' => array(
        '#markup' => 'foo',
      ),
    );
    $render_result = $render;
    unset($render_result['#attached']);
    unset($render_result['child_1']);

    $this->assertEquals($render_result, $this->renderStack->collectAndRemoveD8Properties($render));
  }

  /**
   * Tests that RenderStack::convertRenderArrayToD7() is working properly.
   * @covers ::convertRenderArrayToD7()
   */
  public function test_convertRenderArrayToD7() {
    $render = array(
      '#cache' => array(
        'tags' => array(
          'node:1',
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
          'node:1',
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
          'node:1',
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
          'node:1',
        ),
        'max-age' => 600,
      ),

      '#post_render_cache' => array(
        'test_post_render_cache' => array(),
      ),
    );

    $this->assertEquals($render, $this->renderStack->convertRenderArrayFromD7($render_result));
  }

  /**
   * @covers ::processPostRenderCache()
   */
  public function test_processPostRenderCache() {
    $this->renderStack
      ->shouldReceive('collectAttached')
      ->twice()
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $cache_info = array(
      'render_cache_cache_strategy' => \RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER,
    );
    $render = array(
      '#markup' => 'bar',
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
      ),
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
        ),
      ),
    );

    $render['#post_render_cache'] = array(
      '\Drupal\render_cache\Tests\Cache\RenderStackTest::renderStackPostProcessTest' => array(array('bar', 'baz', FALSE, $this->renderStack)),
    );

    $result = array(
      '#markup' => 'baz',
      '#cache' => array(
        'tags' => array(
          'bar',
          'node:1',
        ),
      ),
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
          array('bar', 'baz'),
          array('foo', 'baz'),
        ),
      ),
    );

    $this->renderStack->processPostRenderCache($render, $cache_info, 'Render matches when using normal post render cache.');
    $this->assertEquals($result, $render);
  }

  /**
   * @covers ::processPostRenderCache()
   */
  public function test_processPostRenderCache_recursive() {
    $this->renderStack
      ->shouldReceive('collectAttached')
      ->times(4)
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $cache_info = array(
      'render_cache_cache_strategy' => \RenderCache::RENDER_CACHE_STRATEGY_DIRECT_RENDER,
    );

    $render = array(
      '#markup' => 'bar',
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
      ),
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
        ),
      ),
    );
    $render['#post_render_cache'] = array(
      '\Drupal\render_cache\Tests\Cache\RenderStackTest::renderStackPostProcessTest' => array(array('bar', 'baz', array('baz', 'foo', FALSE, $this->renderStack), $this->renderStack)),
    );

    $result = array(
      '#markup' => 'foo',
      '#cache' => array(
        'tags' => array(
          'bar',
          'baz',
          'node:1',
        ),
      ),
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
          array('bar', 'baz'),
          array('foo', 'baz'),
          array('bar', 'foo'),
          array('foo', 'foo'),
        ),
      ),
    );

    $this->renderStack->processPostRenderCache($render, $cache_info);
    $this->assertEquals($result, $render, 'Render matches when using recursive post render cache.');
  }

  /**
   * @covers ::processPostRenderCache()
   */
  public function test_processPostRenderCache_lateStrategy() {
    $cache_info = array(
      'render_cache_cache_strategy' => \RenderCache::RENDER_CACHE_STRATEGY_LATE_RENDER,
    );

    $render = array(
      '#markup' => 'bar',
      '#cache' => array(
        'tags' => array(
          'node:1',
        ),
      ),
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
        ),
      ),
    );
    $render['#post_render_cache'] = array(
      '\Drupal\render_cache\Tests\Cache\RenderStackTest::renderStackPostProcessTest' => array(array('bar', 'baz', array('baz', 'foo', FALSE, $this->renderStack), $this->renderStack)),
    );

    $result = $render;

    $this->renderStack->processPostRenderCache($render, $cache_info);
    $this->assertEquals($result, $render, 'Render matches when using post_render_cache with late render strategy.');
  }


  /**
   * @covers ::drupal_add_assets()
   */
  public function test_drupal_add_assets() {
    $this->renderStack
      ->shouldReceive('callOriginalFunction')
      ->twice()
      ->with('drupal_add_css', Mockery::any(), Mockery::any())
      ->andReturn(NULL);

    $this->renderStack
      ->shouldReceive('callOriginalFunction')
      ->twice()
      ->with('drupal_add_js', Mockery::any(), Mockery::any())
      ->andReturn(NULL);

    $this->renderStack
      ->shouldReceive('collectAttached')
      ->times(4)
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $elements_found = array(
      '#attached' => array(
        'js' => array(
          array(
            'data' => 'foo.js',
          ),
          array(
            'data' => array('bar' => 'baz'),
            'type' => 'setting',
          ),
        ),
        'css' => array(
          array(
            'data' => 'foo.css',
          ),
        ),

      ),
    );

    $this->assertNull($this->renderStack->drupal_add_assets('js', 'foo.js'), 'Original function returns NULL and is called one time.');
    $this->assertNull($this->renderStack->drupal_add_assets('css', 'foo.css'), 'Original function returns NULL and is called one time.');

    $this->renderStack->increaseRecursion();
    $this->assertNull($this->renderStack->drupal_add_assets('js', 'foo.js'), 'Original function returns NULL and is called one time.');
    $this->assertNull($this->renderStack->drupal_add_assets('css', 'foo.css'), 'Original function returns NULL and is called one time.');
    $this->assertNull($this->renderStack->drupal_add_assets('js', array('bar' => 'baz'), 'setting'), 'Original function returns NULL and is called one time.');
    $storage = $this->renderStack->decreaseRecursion();
    $this->assertEquals($elements_found, $storage, 'Storage matches what was pushed via drupal_add_assets.');

    $this->assertNull($this->renderStack->drupal_add_assets('js', 'bar.js'), 'Original function returns NULL and is called one time.');
    $this->assertNull($this->renderStack->drupal_add_assets('css', 'bar.css'), 'Original function returns NULL and is called one time.');
  }

  /**
   * @covers ::drupal_add_library()
   */
  public function test_drupal_add_library() {
    $this->renderStack
      ->shouldReceive('callOriginalFunction')
      ->twice()
      ->andReturn(FALSE, TRUE);
    $this->renderStack
      ->shouldReceive('collectAttached')
      ->twice()
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $elements_found = array(
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
        ),
      ),
    );

    $this->assertFalse($this->renderStack->drupal_add_library('not_exist', 'foo'), 'Original function returns FALSE and is called one time.');

    $this->renderStack->increaseRecursion();
    $this->assertTrue($this->renderStack->drupal_add_library('contextual', 'contextual-links'), 'Dynamic call path returns TRUE when found.');
    // @todo Fix this test.
    // $this->assertFalse($this->renderStack->drupal_add_library('not_exist', 'foo'), 'Dynamic call path returns FALSE when not found.');
    $storage = $this->renderStack->decreaseRecursion();
    $this->assertEquals($elements_found, $storage, 'Storage matches what was pushed via drupal_process_attached.');

    $this->assertTrue($this->renderStack->drupal_process_attached('contextual', 'contextual-links'), 'Original function returns TRUE and is called one time.');
  }

  /**
   * @covers ::drupal_process_attached()
   */
  public function test_drupal_process_attached() {
    $this->renderStack
      ->shouldReceive('callOriginalFunction')
      ->twice()
      ->andReturn(FALSE, TRUE);
    $this->renderStack
      ->shouldReceive('collectAttached')
      ->twice()
      ->andReturnUsing(array($this, 'helperCollectAttached'));

    $elements_not_found = array(
      '#attached' => array(
        'library' => array('not_exist', 'foo'),
      ),
    );
    $elements_found = array(
      '#attached' => array(
        'library' => array(
          array('contextual', 'contextual-links'),
        ),
        'js' => array(
          'foo.js',
        ),
        'css' => array(
          'bar.css',
        ),
        'drupal_set_html_head' => array(
          'X-Foo' => 'Baz',
        ),
      ),
    );

    $this->assertFalse($this->renderStack->drupal_process_attached($elements_not_found), 'Original function returns FALSE and is called one time.');

    $this->renderStack->increaseRecursion();
    $this->assertTrue($this->renderStack->drupal_process_attached($elements_found), 'Dynamic call path returns TRUE when found.');
    // @todo Fix this test.
    // $this->assertFalse($this->renderStack->drupal_process_attached($elements_not_found), 'Lazy function path returns FALSE when not found.');
    $storage = $this->renderStack->decreaseRecursion();
    $this->assertEquals($elements_found, $storage, 'Storage matches what was pushed via drupal_process_attached.');

    $this->assertTrue($this->renderStack->drupal_process_attached($elements_found), 'Original function returns TRUE and is called one time.');
  }

  /**
   * @covers ::callOriginalFunction()
   */
  public function test_callOriginalFunction() {
    $this->renderStack->shouldDeferMissing();
    $result = $this->renderStack->callOriginalFunction('\Drupal\render_cache\Tests\Cache\RenderStackTest::renderStackTest', 42, 23);
    $this->assertEquals(42+23, $result);
  }

  /**
   * Helper function to mock collect attached.
   *
   * @param array $render
   *   The render array to process.
   *
   * @return array
   *   The collected attachments.
   */
  public function helperCollectAttached(array $render) {
    if (!empty($render[2]['#attached']) && empty($render[1]['#attached'])) {
      return NestedArray::mergeDeep($render[0]['#attached'], $render[2]['#attached']);
    }

    if (!empty($render[2]['#attached'])) {
      return NestedArray::mergeDeep($render[0]['#attached'], $render[1]['#attached'], $render[2]['#attached']);
    }

    if (!empty($render[1]['#attached']) && !empty($render[0]['#attached'])) {
      return NestedArray::mergeDeep($render[0]['#attached'], $render[1]['#attached']);
    }

    if (!empty($render[1]['#attached'])) {
      return $render[1]['#attached'];
    }

    if (!empty($render[0]['#attached'])) {
      return $render[0]['#attached'];
    }

    if (!empty($render['#attached'])) {
      return $render['#attached'];
    }

    return array();
  }

  /**
   * A test callback function.
   * @param int $somearg_1
   * @param int $somearg_2
   *
   * @return int
   */
  public static function renderStackTest($somearg_1, $somearg_2) {
    return $somearg_1 + $somearg_2;
  }

  /**
   * Helper function to test #post_render_cache.
   */
  public static function renderStackPostProcessTest(array $element, array $context) {
    $source = $context[0];
    $dest = $context[1];
    $prc = $context[2];
    $stack = $context[3];

    $element['#markup'] = str_replace($source, $dest, $element['#markup']);
    $element['#cache']['tags'][] = $source;
    $element['#attached']['library'][] = array('bar', $dest);
    $stack->drupal_add_library('foo', $dest);

    if (!empty($prc)) {
      $element['#post_render_cache']['\Drupal\render_cache\Tests\Cache\RenderStackTest::renderStackPostProcessTest'][] = $prc;
    }
    return $element;
  }

}
