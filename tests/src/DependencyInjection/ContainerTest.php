<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\DependencyInjection\ContainerTest
 */

namespace Drupal\render_cache\Tests\DependencyInjection;

use Drupal\render_cache\DependencyInjection\Container;
use Drupal\render_cache\DependencyInjection\ContainerInterface;

use Mockery;
use Mockery\MockInterface;

/**
 * @coversDefaultClass \Drupal\render_cache\DependencyInjection\Container
 * @group dic
 */
class ContainerBuilder extends \PHPUnit_Framework_TestCase {

  public function setUp() {
    $this->containerDefinition = $this->getMockContainerDefinition();
    $this->container = new Container($this->containerDefinition);
  }

  /**
   * Tests that Container::hasDefinition() works properly.
   */
  public function test_hasDefinition() {
    $this->assertEquals($this->container->hasDefinition('service_container'), TRUE, 'Container has definition of itself.');
    $this->assertEquals($this->container->hasDefinition('service.does_not_exist'), FALSE, 'Container does not have non-existent service.');
    $this->assertEquals($this->container->hasDefinition('service.provider'), TRUE, 'Container has service.provider service.');
  }

  /**
   * Tests that Container::getDefinition() works properly.
   */
  public function test_getDefinition() {
    $this->assertEquals( $this->containerDefinition['services']['service_container'], $this->container->getDefinition('service_container'), 'Container definition matches for container service.');
    $this->assertEquals( $this->containerDefinition['services']['service.provider'], $this->container->getDefinition('service.provider'), 'Container definition matches for service.provider service.');
  }

  /**
   * Tests that Container::getDefinitions() works properly.
   */
  public function test_getDefinitions() {
    $this->assertEquals($this->containerDefinition['services'], $this->container->getDefinitions(), 'Container definition matches input.');
  }

  /**
   * Tests that Container::getParameter() works properly.
   */
  public function test_getParameter() {
    $this->assertEquals($this->containerDefinition['parameters']['some_config'], $this->container->getParameter('some_config'), 'Container parameter matches for %some_config%.');
    $this->assertEquals($this->containerDefinition['parameters']['some_other_config'], $this->container->getParameter('some_other_config'), 'Container parameter matches for %some_other_config%.');
  }

  /**
   * Tests that Container::hasParameter() works properly.
   */
  public function test_hasParameter() {
    $this->assertTrue($this->container->hasParameter('some_config'), 'Container parameters include %some_config%.');
    $this->assertFalse($this->container->hasParameter('some_config_not_exists'), 'Container parameters do not include %some_config_not_exists%.');
  }

  /**
   * Tests that Container::get() works properly.
   */
  public function test_get() {
    $container = $this->container->get('service_container');
    $this->assertEquals($this->container, $container, 'Container can be retrieved from itself.');

    // Retrieve services of the container.
    $other_service_class = $this->containerDefinition['services']['other.service']['class'];
    $other_service = $this->container->get('other.service');
    $this->assertInstanceOf($other_service_class, $other_service, 'other.service has the right class.');

    $some_parameter = $this->containerDefinition['parameters']['some_config'];
    $some_other_parameter = $this->containerDefinition['parameters']['some_other_config'];

    $service = $this->container->get('service.provider');

    $this->assertEquals($other_service, $service->getSomeOtherService(), '@other.service was injected via constructor.');
    $this->assertEquals($some_parameter, $service->getSomeParameter(), '%some_config% was injected via constructor.');
    $this->assertEquals($this->container, $service->getContainer(), 'Container was injected via setter injection.');
    $this->assertEquals($some_other_parameter, $service->getSomeOtherParameter(), '%some_other_config% was injected via setter injection.');
  }

  /**
   * Tests that Container::get() for factories via services works properly.
   */
  public function test_get_factoryService() {
    $factory_service = $this->container->get('factory_service');
    $factory_service_class = $this->container->getParameter('factory_service_class');
    $this->assertInstanceOf($factory_service_class, $factory_service);
  }

  /**
   * Tests that Container::get() for factories via factory_class works.
   */
  public function test_get_factoryClass() {
    $service = $this->container->get('service.provider');
    $factory_service= $this->container->get('factory_class');

    $this->assertInstanceOf(get_class($service), $factory_service);
    $this->assertEquals('bar', $factory_service->getSomeParameter(), 'Correct parameter was passed via the factory class instantiation.');
    $this->assertEquals($this->container, $factory_service->getContainer(), 'Container was injected via setter injection.');
  }

  /**
   * Returns a mock container definition.
   *
   * @return array
   *   Associated array with parameters and services.
   */
  protected function getMockContainerDefinition() {
    $fake_service = Mockery::mock('alias:Drupal\render_cache\Tests\DependencyInjection\FakeService');
    $parameters = array();
    $parameters['some_config'] = 'foo';
    $parameters['some_other_config'] = 'lama';
    $parameters['factory_service_class'] = get_class($fake_service);

    $services = array();
    $services['service_container'] = array(
      'class' => '\Drupal\render_cache\DependencyInjection\Container',
    );
    $services['other.service'] = array(
      // @todo Support parameter expansion for classes.
      'class' => get_class($fake_service),
    );
    $services['service.provider'] = array(
      'class' => '\Drupal\render_cache\Tests\DependencyInjection\MockService',
      'arguments' => array('@other.service', '%some_config%'),
      'calls' => array(
        array('setContainer', array('@service_container')),
        array('setOtherConfigParameter', array('%some_other_config%')),
       ),
      'priority' => 0,
    );
    $services['factory_service'] = array(
      'class' => '\Drupal\render_cache\RenderCache\ControllerInterface',
      'factory_method' => 'getFactoryMethod',
      'factory_service' => 'service.provider',
      'arguments' => array('%factory_service_class%'),
    );
    $services['factory_class'] = array(
      'class' => '\Drupal\render_cache\RenderCache\ControllerInterface',
      'factory_method' => 'getFactoryMethod',
      'factory_class' => '\Drupal\render_cache\Tests\DependencyInjection\MockService',
      'arguments' => array(
        '\Drupal\render_cache\Tests\DependencyInjection\MockService',
        array(NULL, 'bar'),
      ),
      'calls' => array(
        array('setContainer', array('@service_container')),
      ),
    );

    return array(
      'parameters' => $parameters,
      'services' => $services,
    );
  }
}
