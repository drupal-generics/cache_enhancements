<?php

namespace Drupal\cache_enhancements;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;

/**
 * Defines an interface for caching custom data with cacheability metadata.
 *
 * @package Drupal\cache_enhancements
 */
interface CacheableDataInterface extends RefinableCacheableDependencyInterface {

  /**
   * Retrieves the custom data from cache.
   *
   * @return mixed|false
   *   The custom data from cache, or FALSE if no cached copy is available.
   */
  public function getData();

  /**
   * Caches the given custom data.
   *
   * @param mixed $data
   *   The custom data to store in the cache.
   *
   * @return bool|null
   *   Returns FALSE if no cache item could be created, NULL otherwise.
   */
  public function setData($data);

}
