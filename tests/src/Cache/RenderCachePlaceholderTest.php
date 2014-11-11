<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\RenderCachePlaceholderTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\Component\Utility\NestedArray;
use Drupal\render_cache\Cache\RenderCachePlaceholder;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Cache\RenderCachePlaceholder
 * @group cache
 */
class RenderCachePlaceholderTest extends \PHPUnit_Framework_TestCase {

  /**
   * Helper variable to see that foo was loaded correctly.
   * 
   * @var mixed $foo
   */
  protected static $foo;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->staticClass = RenderCachePlaceholderMock::helper_getClass();
    $this->testClass = get_class($this);
    $this->placeholderArgFunc = '%' . $this->testClass . '::foo';
    $this->placeholderArgLoad = $this->testClass . '::foo_load';

    $data = array(
      'single' => array(
        'func' => 'postRenderCacheCallback',
        'token' => 'n6lnoFtakTea+1C4Ox28YJ9GX/YA33x18RTw46nOOb+42Qofut1EvArYqECraJzlypf7DAR9MA==',
        'callback' => $this->testClass . '::singleCallback',
        'args' => array(
          $this->placeholderArgFunc => 'foo',
          'bar',
        ),
        'return' => 'Foobar',
      ),
      'multi_1' => array(
        'func' => 'postRenderCacheMultiCallback',
        'token' => 'M41Dgx+Cep02ViLzyRydUNgba8xMNdBjxn7Iu4dXNIXX+6L9RkNsXNfh8msI9mVFgd8yuQLlew==',
        'callback' => $this->testClass . '::multiCallback',
        'args' => array(
          1,
        ),
        'return' => 'bar_1',
      ),
      'multi_2' => array(
        'func' => 'postRenderCacheMultiCallback',
        'token' => '5jOFkKm/lxLXBzraoHNGi8Aa5KxTZ9sDRTu7doVgxHACDxU2va7N2OpysutTffjDGupAGFpNRg==',
        'callback' => $this->testClass . '::multiCallback',
        'args' => array(
          2,
        ),
        'return' => 'bar_2',
      ),
    );
    foreach ($data as $key => $info) {
      $data[$key]['placeholder'] = $this->getPlaceholderArray($info['token'], $info['callback'], $info['args'], $info['func']);
    }
    $this->data = $data;

    $test_class = $this->testClass;

    $mock = Mockery::mock('\Drupal\render_cache\Tests\Cache\RenderCachePlaceholderHelper');
    $mock->shouldReceive('generatePlaceholder')
      ->with(Mockery::any(), Mockery::on(function(array &$context) use ($data, $test_class) {
        if ($context['function'] == ($test_class . '::singleCallback')) {
          $context['token'] = $data['single']['token'];
          return TRUE;
        }
        if ($context['function'] != ($test_class . '::multiCallback')) {
          return FALSE;
        }
        $key = 'multi_' . $context['args'][0];
        $context['token'] = $data[$key]['token'];
        return TRUE;
      }))
      ->andReturnUsing(function($callback, $context) use ($data, $test_class) {
        if ($context['function'] == ($test_class . '::singleCallback')) {
          return $data['single']['placeholder']['#markup'];
        }
        $key = 'multi_' . $context['args'][0];
        return $data[$key]['placeholder']['#markup'];
      });

    $mock->shouldReceive('drupalRender')
      ->andReturnUsing(function($elements) {
        return $elements['#markup'];
      });
   
    $class_name = $this->staticClass;
    $class_name::$mock = $mock;
  }

  /**
   * @covers ::getPlaceholder()
   */
  public function test_getPlaceholder() {
    $class_name = $this->staticClass;

    $render = $class_name::getPlaceholder($this->data['single']['callback'], $this->data['single']['args']);
    $this->assertEquals($this->data['single']['placeholder'], $render, 'getPlaceholder() returns the right output for single callback.');
    return $render;
  }

  /**
   * @depends test_getPlaceholder
   * @covers ::postRenderCacheCallback()
   * @covers ::loadPlaceholderFunctionArgs()
   */
  public function test_postRenderCacheCallback($render) {
    $func = $this->placeholderArgLoad;
    $foo = call_user_func($func, 'foo');

    $this->processPostRenderCache($render);
    $this->assertEquals($foo, static::$foo, 'Foo was loaded succesfully.');
    
    $expected = $this->data['single']['placeholder'];
    $expected['#markup'] = $this->data['single']['return'];

    $this->assertEquals($expected, $render, 'Placeholder was replaced.');
  }

  /**
   * @depends test_getPlaceholder
   * @covers ::postRenderCacheCallback()
   */
  public function test_postRenderCacheCallback_noPlaceholder($render) {
    static::$foo = NULL;
    $render['#markup'] = 'Bar';
    $this->processPostRenderCache($render);
    $this->assertNull(static::$foo, 'Foo was not loaded.');
    $expected = $this->data['single']['placeholder'];
    $expected['#markup'] = 'Bar';
    $this->assertEquals($expected, $render, 'Placeholder was not replaced.');
  }

  /**
   * @depends test_getPlaceholder
   * @covers ::postRenderCacheMultiCallback()
   */
  public function test_postRenderCacheCallback_wrongFunction($render) {
    // Move the callback from the single callback to the multi callback.
    $tmp = $render['#post_render_cache'][$this->staticClass . '::postRenderCacheCallback'];
    $render['#post_render_cache'][$this->staticClass . '::postRenderCacheMultiCallback'] = $tmp;
    unset($render['#post_render_cache'][$this->staticClass . '::postRenderCacheCallback']);

    $expected = $render;
    $this->processPostRenderCache($render);
    $this->assertEquals($expected, $render, 'Expected still matches $render.');
  }

  /**
   * @covers ::getPlaceholder()
   */
  public function test_getPlaceholder_multi() {
    $class_name = $this->staticClass;

    $render_1 = $class_name::getPlaceholder($this->data['multi_1']['callback'], $this->data['multi_1']['args'], TRUE);
    $this->assertEquals($this->data['multi_1']['placeholder'], $render_1, 'getPlaceholder() returns the right output for multi_1 callback.');

    $render_2 = $class_name::getPlaceholder($this->data['multi_2']['callback'], $this->data['multi_2']['args'], TRUE);
    $this->assertEquals($this->data['multi_2']['placeholder'], $render_2, 'getPlaceholder() returns the right output for multi_2 callback.');

    $render = array();
    $render['#post_render_cache'] = NestedArray::mergeDeep($render_1['#post_render_cache'], $render_2['#post_render_cache']);
    $render['#markup'] = $render_1['#markup'] . '|' . $render_2['#markup'];

    return $render;
  }

  /**
   * @depends test_getPlaceholder_multi
   * @covers ::postRenderCacheMultiCallback()
   */
  public function test_postRenderCacheMultiCallback($render) {
    $expected = $render;
    $this->processPostRenderCache($render);
    $expected['#markup'] = $this->data['multi_1']['return'] . '|' . $this->data['multi_2']['return'];
    $this->assertEquals($expected, $render, 'Placeholders have been replaced.');
  }

  /**
   * @depends test_getPlaceholder_multi
   * @covers ::postRenderCacheMultiCallback()
   */
  public function test_postRenderCacheMultiCallback_noPlaceholder($render) {
    $expected = $render;
    $render['#markup'] = 'Bar12345';
    $this->processPostRenderCache($render);
    $expected['#markup'] = 'Bar12345';
    $this->assertEquals($expected, $render, 'Placeholders have not been replaced.');
  }


  /**
   * Helper function to check that foo was populated
   * and the right bar is returned.
   */
  public static function singleCallback($foo, $bar) {
    static::$foo = $foo;
    return array('#markup' => 'Foo' . $bar);
  }

  /**
   * Helper function to check that the right arg is returned.
   */
  public static function multiCallback($pholders) {
    $placeholders = array();
    foreach ($pholders as $pholder => $args) {
      $placeholders[$pholder] = array(
        '#markup' => 'bar_' . $args[0],
      );
    }

    return $placeholders;
  }

  /**
   * Helper function to test loadPlaceholderFunctionArgs().
   *
   * @param string $argument
   *   The path argument.
   * @return object|NULL
   *   The loaded object.
   */
  public static function foo_load($argument) {
    if ($argument == 'foo') {
      return (object) array('foo_id' => 1, 'type' => 'foo', 'title' => 'bar');
    }

    return NULL;
  }

  protected function processPostRenderCache(&$elements) {
    foreach (array_keys($elements['#post_render_cache']) as $callback) {
      foreach ($elements['#post_render_cache'][$callback] as $context) {
        $elements = call_user_func_array($callback, array($elements, $context));
      }
    }
  }

  protected function getPlaceholderArray($token, $callback, $args, $func) {
    $inner = array(array(
              'function' => $callback,
              'args' => $args,
              'token' => $token,
             ));
    if ($func == 'postRenderCacheMultiCallback') {
      $inner = array($callback => $inner);
    }
    return array(
      '#post_render_cache' => 
      array(
        ($this->staticClass . '::' . $func) => $inner,
      ),
      '#markup' => '<drupal-render-cache-placeholder callback="' . $callback . '" token="' . $token . '"></drupal-render-cache-placeholder>',
    );
  }
}

class RenderCachePlaceholderMock extends RenderCachePlaceholder {
  /**
   * A mock that retrieves static calls we override.
   *
   * @var object
   */
  public static $mock;

  /**
   * {@inheritdoc}
   */
  protected static function generatePlaceholder($callback, array &$context) {
    return static::$mock->generatePlaceholder($callback, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected static function drupalRender(array &$elements) {
    return static::$mock->drupalRender($elements);
  }

  /**
   * Helper to return the class of the class.
   *
   * @return string
   *   Returns what the class was called as.
   */
  public static function helper_getClass() {
    return get_called_class();
  }
}

/**
 * Helper class to test the placeholder functionality as a mock.
 */
abstract class RenderCachePlaceholderHelper {
  abstract public function generatePlaceholder($callback, array &$context);
  abstract public function drupalRender(array &$elements);
}
