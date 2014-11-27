<?php

/**
 * @file
 * Contains \Drupal\render_cache\Tests\Cache\CacheTest
 */

namespace Drupal\render_cache\Tests\Cache;

use Drupal\render_cache\Cache\Cache;

use Mockery;

/**
 * @coversDefaultClass \Drupal\render_cache\Cache\Cache
 * @group cache
 */
class CacheTest extends \PHPUnit_Framework_TestCase {

  /**
   * Tests ::keyFromQuery() method.
   * @covers ::keyFromQuery()
   */
  public function test_keyFromQuery() {
    $query = Mockery::mock('\SelectQueryInterface');
    $query
      ->shouldReceive('preExecute')
      ->once();
    $query
      ->shouldReceive('getArguments')
      ->once()
      ->andReturn(array(':foo' => 'bar'));
    $query
      ->shouldReceive('__toString')
      ->once()
      ->andReturn('SELECT * from {node} WHERE nid = :foo');

    $this->assertEquals('46387e3c7711dfd22bf707bcd79fe77bd652741b9b53a7adeb9be32fa3e010bb', Cache::keyFromQuery($query), 'Key from query returns the right hash.');
  }
}
