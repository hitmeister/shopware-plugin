<?php

use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;

class Shopware_Controllers_Backend_HmArticles extends Shopware_Controllers_Backend_ExtJs
{
    public function getListAction()
    {
        $limit = $this->Request()->getParam('limit', 100);
        $offset = ($this->Request()->getParam('page', 1) - 1) * $limit;
        $sort = $this->Request()->getParam('sort', array());
        $filter = $this->Request()->getParam('filter', array());

        try {
            /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
            $joinBuilder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
            $joinBuilder
                ->select(array(
                    'cor.article_id',
                    'GROUP_CONCAT(CONCAT(cg.name, " ", co.name) ORDER BY cg.position SEPARATOR ", ") AS variant_text',
                ))
                ->from('s_article_configurator_option_relations', 'cor')
                ->innerJoin('cor', 's_article_configurator_options', 'co', 'co.id = cor.option_id')
                ->innerJoin('co', 's_article_configurator_groups', 'cg', 'cg.id = co.group_id')
                ->groupBy('cor.article_id');

            /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
            $builder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
            $builder
                ->select(array(
                    'd.id',
                    'd.ordernumber',
                    'CONCAT_WS(", ", a.name, v.variant_text) AS name',
                    'd.ean',
                    'd.instock',
                    'da.unit_id AS hm_unit_id',
                    'da.last_access_date AS hm_last_access_date',
                    'da.status AS hm_status',
                ))
                ->from('s_articles_details', 'd')
                ->innerJoin('d', 's_articles', 'a', 'a.id = d.articleID')
                ->leftJoin('d', 's_plugin_hitme_stock', 'da', 'da.article_detail_id = d.id')
                ->leftJoin('d', '(' . $joinBuilder->getSQL() . ')', 'v', 'v.article_id = d.id');

            $where = $builder
                ->expr()
                ->andX('d.active = 1');

            if (!empty($filter)) {
                $or = $builder->expr()->orX();

                foreach ($filter as $item) {
                    if($item['property']=='shopId'){
                        if(!empty($item['value'])){
                            $shopId = (int) $item['value'];
                            $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId );
                            $categoryId = (int)$shop->getCategory()->getId();
                        }
                    }else{
                        $prefix = ($item['property'] == 'name') ? 'a' : 'd';
                        $or->add($prefix . '.' . $item['property'] . ' LIKE :' . $item['property']);
                        $builder->setParameter(':' . $item['property'], '%' . $item['value'] . '%');
                    }
                }

                if($or->count()){
                    $where->add($or);
                }
            }

            $filterShopBuilder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
            $filterShopBuilder
              ->select(array(
                'cat.articleID',
              ))
              ->from('s_articles_categories_ro', 'cat')
              ->groupBy('cat.articleID');

            if($shop && !empty($categoryId)){
                $filterShopBuilder->where('cat.categoryID = :filter_shop');
                $builder->setParameter(':filter_shop', $categoryId);
            }else{
                $hmActiveShops = Shop::getActiveShops();
                $hmActiveShopsCatIds = array_map(function ($item) {return $item['category_id'];}, $hmActiveShops);
                if(count($hmActiveShopsCatIds)){
                    $filterShopBuilder->where(
                      $filterShopBuilder->expr()->in('cat.categoryID',implode(",", $hmActiveShopsCatIds))
                    );
                }else{
                    throw new Exception("No Articles to sync");
                }
            }

            $where->add(
              $builder->expr()->in('d.articleID', $filterShopBuilder->getSQL())
            );

            $builder->where($where);

            $totalBuilder = clone $builder;

            $builder
                ->setFirstResult($offset)
                ->setMaxResults($limit);

            if (!empty($sort)) {
                $sort = array_pop($sort);
                $builder->orderBy($sort['property'], $sort['direction']);
            } else {
                $builder->orderBy('ordernumber', 'ASC');
            }

            $totalBuilder
                ->select('COUNT(d.id)')
                ->from('s_articles_details', 'd')
                ->where($where);

            $data = $builder->execute()->fetchAll(PDO::FETCH_ASSOC);
            $total = $totalBuilder->execute()->fetchColumn(0);

            $this->View()->assign(array(
                'success' => true,
                'data' => $data,
                'total' => $total,
            ));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'data' => array(), 'total' => 0, 'message' => $e->getMessage()));
        }
    }

    public function changeStatusByIdAction()
    {
        $detailsId = $this->Request()->getParam('id', null);
        if (empty($detailsId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No article details id passed!'));
        }

        $status = $this->Request()->getParam('status');
        if (!in_array($status, array(StockManagement::STATUS_NEW, StockManagement::STATUS_BLOCKED))) {
            return $this->View()->assign(array('success' => false, 'message' => 'Unexpected status value. Expecting new or blocked.'));
        }

        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id is passed!'));
        }

        try {
            // Cleanup first
            if (StockManagement::STATUS_BLOCKED == $status) {
                $this->getStockManagement()->blockByDetailId($detailsId, $shopId);
            }

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get('dbal_connection');

            // Then remove data
            $connection->update('s_plugin_hitme_stock',
                array(
                    'status' => $status,
                    'last_access_date' => null,
                    'unit_id' => null,
                ),
                array(
                    'article_detail_id' => (int)$detailsId,
                    'shop_id'           => (int)$shopId,
                ),
                array(
                    'status' => PDO::PARAM_STR,
                    'last_access_date' => PDO::PARAM_NULL,
                    'unit_id' => PDO::PARAM_NULL,
                )
            );

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function syncStockByIdAction()
    {
        $detailsId = $this->Request()->getParam('id', null);
        if (empty($detailsId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No article details id passed!'));
        }

        /** @var Shopware\Models\Article\Detail $detail */
        $detail = Shopware()->Models()->find('Shopware\Models\Article\Detail', $detailsId);
        if (empty($detail)) {
            return $this->View()->assign(array('success' => false, 'message' => sprintf('Article details %d not found!', $detailsId)));
        }

        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id passed!'));
        }

        /** @var Shopware\Models\Shop\Shop $shop */
        $shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shopId);
        if (empty($shop)) {
            return $this->View()->assign(array('success' => false, 'message' => sprintf('Shop %d not found!', $shopId)));
        }

        /** @var Shopware\CustomModels\HitmeMarketplace\Stock $stock */
        $stockRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\HitmeMarketplace\Stock');
        $builder = $stockRepository->createQueryBuilder('Stock')
          ->where('Stock.shopId = :shopId')
          ->andWhere('Stock.articleDetailId = :articleDetailId');

        $builder->setParameters(array(
          'shopId'  => $shopId,
          'articleDetailId' => $detail->getId()
        ));

        $stock = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_OBJECT);

        try {
            $this->getStockManagement()->syncByArticleDetails($detail, true, $stock, $shop);

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function readyForSyncAction()
    {
        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id passed!'));
        }

        /** @var Shopware\Models\Shop\Shop $shop */
        $shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shopId);
        if (empty($shop)) {
            return $this->View()->assign(array('success' => false, 'message' => sprintf('Shop %d not found!', $shopId)));
        }

        $sql = "SELECT d.`id` FROM `s_articles_details` d LEFT JOIN `s_plugin_hitme_stock` a ON d.id = a.article_detail_id WHERE d.`ean` IS NOT NULL AND d.`ean` != '' AND (a.`status` NOT IN (?) OR a.`status` IS NULL ) AND d.articleID IN(SELECT cat.articleID FROM s_articles_categories_ro cat WHERE cat.categoryID = ? GROUP BY cat.articleID)";

        try {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get('dbal_connection');
            $stmt = $connection->executeQuery($sql, array(StockManagement::STATUS_BLOCKED, $shop->getCategory()->getId()));
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->View()->assign(array('success' => true, 'data' => $data));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'data' => array(), 'message' => $e->getMessage()));
        }
    }

    public function changeStatusAllAction()
    {
        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id is passed!'));
        }

        $status = $this->Request()->getParam('status');
        if (!in_array($status, array(StockManagement::STATUS_NEW, StockManagement::STATUS_BLOCKED))) {
            return $this->View()->assign(array('success' => false, 'message' => 'Unexpected status value. Expecting new or blocked.'));
        }

        try {
            // Cleanup first
            $this->getStockManagement()->flushInventory($shopId);

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get('dbal_connection');

            // Then update data
            $connection->update('s_plugin_hitme_stock',
              array(
                'status' => $status,
                'last_access_date' => null,
                'unit_id' => null
              ),
              array(
                'shop_id'           => (int)$shopId,
              ),
              array(
                'status' => PDO::PARAM_STR,
                'last_access_date' => PDO::PARAM_NULL,
                'unit_id' => PDO::PARAM_NULL
              )
            );

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    /**
     * @return StockManagement
     */
    private function getStockManagement()
    {
        return $this->get('HmStockManagement');
    }
}