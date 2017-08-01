<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Hitmeister\Component\Api\Client;

/**
 * Class CategoryFetcher
 * @package ShopwarePlugins\HitmeMarketplace\Components
 */
class CategoryFetcher
{
    /** @var Client */
    private $apiClient;

    /** @var \Zend_Cache_Core */
    private $cache;

    /** @var array */
    private static $data;

    /**
     * @param Client $apiClient
     * @param \Zend_Cache_Core $cache
     */
    public function __construct(Client $apiClient, \Zend_Cache_Core $cache)
    {
        $this->apiClient = $apiClient;
        $this->cache = $cache;
    }

    /**
     * @return array|null
     */
    public function buildTree()
    {
        return $this->buildNode(1);
    }

    /**
     * @param $id
     * @return array|null
     */
    private function buildNode($id)
    {
        $node = $this->fetchById($id);
        if (!$node) {
            return null;
        }

        if (!empty($node['children'])) {
            $node['leaf'] = false;
            foreach ($node['children'] as $i => $childId) {
                $ch = $this->buildNode($childId);
                if (!$ch) {
                    unset($node['children'][$i]);
                    continue;
                }
                $node['children'][$i] = $ch;
            }
        } else {
            $node['leaf'] = true;
            unset($node['children']);
        }
        return $node;
    }

    /**
     * @param int $id
     * @return array|null
     */
    public function fetchById($id)
    {
        $data = $this->fetch();
        return isset($data[$id]) ? $data[$id] : null;
    }

    /**
     * @param int $id
     * @return array
     */
    public function fetchChildren($id)
    {
        $data = $this->fetch();
        if (!isset($data[$id])) {
            return array();
        }

        $result = array();
        foreach ($data[$id]['children'] as $itemId) {
            if (!isset($data[$itemId])) {
                continue;
            }
            $data[$itemId]['leaf'] = empty($data[$itemId]['children']);
            $result[] = $data[$itemId];
        }

        return $result;
    }

    /**
     * @return array
     */
    private function fetch()
    {
        if (null !== self::$data) {
            return self::$data;
        }

        self::$data = $this->cache->load('hm_categories');
        if (false !== self::$data) {
            return self::$data;
        }

        self::$data = array();
        $categories = $this->apiClient->categories()->find(null, null, null, 0);
        foreach ($categories as $category) {
            $item = array(
                'id' => $category->id_category,
                'id_parent' => $category->id_parent_category,
                'title' => $category->title_singular,
                'url' => $category->url,
            );

            if (!isset(self::$data[$category->id_category])) {
                self::$data[$category->id_category] = array_merge(array('children' => array()), $item);
            } else {
                self::$data[$category->id_category] = array_merge(self::$data[$category->id_category], $item);
            }

            if ($category->id_parent_category) {
                self::$data[$category->id_parent_category]['children'][] = $category->id_category;
            }
        }

        $this->cache->save(self::$data, 'hm_categories', array(), 2500000);
        return self::$data;
    }
}
