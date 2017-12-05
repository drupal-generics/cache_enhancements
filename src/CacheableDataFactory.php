<?php

namespace Drupal\cache_enhancements;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Class CacheableDataFactory.
 *
 * @package Drupal\cache_enhancements
 */
class CacheableDataFactory implements CacheableDataFactoryInterface {

  /**
   * The cache factory.
   *
   * @var \Drupal\Core\Cache\CacheFactoryInterface
   */
  protected $cacheFactory;

  /**
   * The cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $cacheContextsManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * CacheableDataFactory constructor.
   *
   * @param \Drupal\Core\Cache\CacheFactoryInterface $cache_factory
   *   The cache factory.
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager
   *   The cache contexts manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(CacheFactoryInterface $cache_factory, CacheContextsManager $cache_contexts_manager, TimeInterface $time) {
    $this->cacheFactory = $cache_factory;
    $this->cacheContextsManager = $cache_contexts_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance(array $cache_keys, string $cache_bin = 'default') {
    if (!empty($cache_keys) && ($cache = $this->cacheFactory->get($cache_bin))) {
      return new CacheableData($cache, $this->cacheContextsManager, $this->time, $cache_keys);
    }

    return NULL;
  }

}
