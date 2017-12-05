<?php

namespace Drupal\cache_enhancements;

/**
 * Interface CacheableDataFactoryInterface.
 *
 * @package Drupal\cache_enhancements
 */
interface CacheableDataFactoryInterface {

  /**
   * Factory method; creates a cacheable data instance.
   *
   * @param array $cache_keys
   *   Cache keys.
   * @param string $cache_bin
   *   (optional) Cache bin. Defaults to 'default'.
   *
   * @return null|\Drupal\cache_enhancements\CacheableDataInterface
   *   The cacheable data instance, or NULL on failure.
   *
   * @see \Drupal\Core\Render\RendererInterface::render()
   *   Cache keys definition.
   * @see \Drupal\Core\Cache\CacheFactoryInterface::get()
   *   Cache bin definition.
   */
  public function createInstance(array $cache_keys, string $cache_bin = 'default');

}
