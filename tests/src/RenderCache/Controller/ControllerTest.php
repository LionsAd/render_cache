<?php
/**
 * @file
 * Contains \Drupal\render_cache\RenderCache\Controller\ControllerTest
 */

namespace Drupal\render_cache\RenderCache\Controller;

/**
 * Class Test
 * @covers Drupal\render_cache\RenderCache\Controller\BaseController
 */
class ControllerTest extends \PHPUnit_Framework_TestCase {

  /**
   * Tests that the controller interface has a view method.
   */
  public function testControllerInterface() {
    $mockController = $this->getMockBuilder('\Drupal\render_cache\RenderCache\Controller\ControllerInterface')
      ->disableOriginalConstructor()
      ->getMock();

  $mockController->expects($this->any())
      ->method('view')
      ->will($this->returnValue('foo'));

    $objects = array();
    $this->assertEquals('foo', $mockController->view($objects));
  }
}
