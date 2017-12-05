<?php

namespace Drupal\cache_enhancements;

use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Class CacheableData.
 *
 * Wrapper around a cache backend instance to ease the storage and retrieval of
 * custom data.
 *
 * @package Drupal\cache_enhancements
 *
 * @see \Drupal\Core\Render\RenderCache
 *   The source idea behind this implementation. Instead of dealing directly
 *   with the cache backend using a manually created cache ID, we reuse the
 *   render caching system's logic and deal with cacheability metadata instead,
 *   that is, cache keys, contexts, tags and max-age.
 * @see \Drupal\Core\Cache\CacheableMetadata
 * @see \Drupal\cache_enhancements\CacheableDataFactoryInterface::createInstance()
 *   The preferred way to instantiate this class.
 */
class CacheableData implements CacheableDataInterface {

  use RefinableCacheableDependencyTrait
  {
    RefinableCacheableDependencyTrait::addCacheContexts as traitAddCacheContexts;
  }

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

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
   * Cache keys.
   *
   * @var string[]
   *
   * @see createCacheId()
   */
  protected $cacheKeys;

  /**
   * Stores the cache ID so it's re-created only when it's necessary.
   *
   * @var string
   *
   * @see createCacheId()
   * @see addCacheContexts()
   */
  protected $cacheID;

  /**
   * Stores the cached data so we hit the cache backend only when necessary.
   *
   * Associative array keyed by cache ID, where the value is the cached data.
   *
   * @var mixed
   *
   * @see getData()
   * @see setData()
   */
  protected $data = [];

  /**
   * CacheableData constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cache_contexts_manager
   *   The cache contexts manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param array $cache_keys
   *   Cache keys.
   */
  public function __construct(CacheBackendInterface $cache,
                              CacheContextsManager $cache_contexts_manager,
                              TimeInterface $time,
                              array $cache_keys) {
    $this->cache = $cache;
    $this->cacheContextsManager = $cache_contexts_manager;
    $this->time = $time;
    $this->cacheKeys = $cache_keys;
  }

  /**
   * Creates the cache ID for the custom data.
   *
   * Creates the cache ID string based on ::cacheKeys + ::cacheContexts.
   *
   * @return bool|string
   *   The cache ID string, or FALSE if the custom data may not be cached.
   *
   * @see \Drupal\Core\Render\RenderCache::createCacheID()
   * @see addCacheContexts()
   */
  protected function createCacheId() {
    // If the maximum age is zero, then caching is effectively prohibited.
    if (isset($this->cacheMaxAge) && ($this->cacheMaxAge === 0)) {
      return FALSE;
    }

    if ($this->cacheID) {
      return $this->cacheID;
    }

    if (isset($this->cacheKeys)) {
      $cid_parts = $this->cacheKeys;

      if (!empty($this->cacheContexts)) {
        $context_cache_keys = $this->cacheContextsManager->convertTokensToKeys($this->cacheContexts);
        $cid_parts = array_merge($cid_parts, $context_cache_keys->getKeys());
      }

      return ($this->cacheID = implode(':', $cid_parts));
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Invalidates the stored cached cache ID when new cache contexts are added.
   *
   * @see createCacheId()
   */
  public function addCacheContexts(array $cache_contexts) {
    if ($cache_contexts) {
      $this->cacheID = NULL;
    }

    return $this->traitAddCacheContexts($cache_contexts);
  }

  /**
   * Maps the Cache API's "expire" value to the ::cacheMaxAge value.
   *
   * @param int $expire
   *   The "expire" value.
   */
  protected function expireToMaxAge(int $expire) {
    if ($expire === Cache::PERMANENT) {
      $this->cacheMaxAge = Cache::PERMANENT;
    }
    else {
      $this->cacheMaxAge = max([
        (int) $expire - $this->time->getRequestTime(),
        0,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Render\RenderCache::get()
   * @see createCacheId()
   *   IMPORTANT: All desired cache keys and contexts must be set before data
   *   retrieval, otherwise it isn't possible to construct the cache ID
   *   correctly.
   */
  public function getData() {
    if ($cid = $this->createCacheId()) {
      if (array_key_exists($cid, $this->data)) {
        return $this->data[$cid];
      }
      elseif ($cache_object = $this->cache->get($cid)) {
        $this->data = [
          $cid => $cache_object->data,
        ];

        $this->cacheTags = $cache_object->tags;
        $this->expireToMaxAge($cache_object->expire);

        return $cache_object->data;
      }
    }

    return FALSE;
  }

  /**
   * Maps the ::cacheMaxAge value to an "expire" value for the Cache API.
   *
   * @return int
   *   A corresponding "expire" value.
   *
   * @see \Drupal\Core\Render\RenderCache::maxAgeToExpire()
   */
  protected function maxAgeToExpire() {
    if ($this->cacheMaxAge === Cache::PERMANENT) {
      return Cache::PERMANENT;
    }

    return (int) $this->time->getRequestTime() + $this->cacheMaxAge;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Render\RenderCache::set()
   * @see createCacheId()
   *   IMPORTANT: The cache contexts must not be altered between an unsuccessful
   *   data retrieval from cache and storage of fresh data in cache, as it would
   *   prevent the data's retrieval from cache the next time it's requested,
   *   that is, because of difference between cache IDs.
   */
  public function setData($data) {
    if (!($cid = $this->createCacheId())) {
      return FALSE;
    }

    $this->data = [
      $cid => $data,
    ];

    $this->cache->set($cid, $data, $this->maxAgeToExpire(), $this->cacheTags);
    return NULL;
  }

}
