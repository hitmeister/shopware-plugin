<?php

use Shopware\Components\CSRFWhitelistAware;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;
use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;

/**
 * Class Shopware_Controllers_Backend_HmArticles
 */
class Shopware_Controllers_Backend_HmArticles extends Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware
{
    const ERROR_MSG = 'errors';
    const DETAIL = 'detail';
    const STOCK = 'stock';
    const SHOP = 'shop';
    const DB = 'dbal_connection';
    const PROPERTY = 'property';
    const VALUE = 'value';
    const SHOP_ID = 'shopId';
    const S_PLUGIN_HITME_STOCK = 's_plugin_hitme_stock';

    /**
     * get list of articles
     */
    public function getListAction()
    {
        $limit = $this->Request()->getParam('limit', 100);
        $offset = ($this->Request()->getParam('page', 1) - 1) * $limit;
        $sort = $this->Request()->getParam('sort', []);
        $filter = $this->Request()->getParam('filter', []);

        try {
            /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
            $joinBuilder = Shopware()->Container()->get(self::DB)->createQueryBuilder();
            $joinBuilder
                ->select([
                    'cor.article_id',
                    'GROUP_CONCAT(CONCAT(cg.name, " ", co.name) ORDER BY cg.position SEPARATOR ", ") AS variant_text',
                ])
                ->from('s_article_configurator_option_relations', 'cor')
                ->innerJoin('cor', 's_article_configurator_options', 'co', 'co.id = cor.option_id')
                ->innerJoin('co', 's_article_configurator_groups', 'cg', 'cg.id = co.group_id')
                ->groupBy('cor.article_id');

            /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
            $builder = Shopware()->Container()->get(self::DB)->createQueryBuilder();
            $builder
                ->select([
                    'd.id',
                    'd.ordernumber',
                    'CONCAT_WS(", ", a.name, v.variant_text) AS name',
                    'd.ean',
                    'd.instock',
                    'da.unit_id AS hm_unit_id',
                    'da.last_access_date AS hm_last_access_date',
                    'da.status AS hm_status'
                ])
                ->from('s_articles_details', 'd')
                ->innerJoin('d', 's_articles', 'a', 'a.id = d.articleID')
                ->leftJoin('d', '(' . $joinBuilder->getSQL() . ')', 'v', 'v.article_id = d.id');

            $where = $builder
                ->expr()
                ->andX('d.active = 1');

            if (!empty($filter)) {
                $or = $builder->expr()->orX();

                foreach ((array)$filter as $item) {
                    if ($item[self::PROPERTY] === 'categoryId') {
                        $categoryId = (int)$item[self::VALUE];
                    } elseif ($item[self::PROPERTY] === self::SHOP_ID) {
                        if (!empty($item[self::VALUE])) {
                            $shopId = (int)$item[self::VALUE];
                            $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId);
                            if (!$categoryId) {
                                $categoryId = (int)$shop->getCategory()->getId();
                            }
                        }
                    } else {
                        $prefix = ($item[self::PROPERTY] === 'name') ? 'a' : 'd';
                        $or->add($prefix . '.' . $item[self::PROPERTY] . ' LIKE :' . $item[self::PROPERTY]);
                        $builder->setParameter(':' . $item[self::PROPERTY], '%' . $item[self::VALUE] . '%');
                    }
                }

                if ($or->count()) {
                    $where->add($or);
                }
            }

            if (!empty($shopId)) {
                $shopConfig = Shop::getShopConfigByShopId($shopId);
                $shippingGroup = $shopConfig->get('defaultShippingGroup');
                $builder
                    ->addSelect('CASE WHEN da.shippinggroup IS NOT NULL
                    THEN da.shippinggroup
                    ELSE :default_shippinggroup
                    END AS hm_shippinggroup')
                    ->leftJoin(
                        'd',
                        self::S_PLUGIN_HITME_STOCK,
                        'da',
                        'da.article_detail_id = d.id AND da.shop_id = :join_shop_id'
                    );
                $builder->setParameter(':default_shippinggroup', $shippingGroup);
                $builder->setParameter(':join_shop_id', $shopId);
            } else {
                $builder
                    ->addSelect('da.shippinggroup AS hm_shippinggroup')
                    ->leftJoin('d', self::S_PLUGIN_HITME_STOCK, 'da', 'da.article_detail_id = d.id');
            }

            $filterShopBuilder = Shopware()->Container()->get(self::DB)->createQueryBuilder();
            $filterShopBuilder
                ->select(['cat.articleID'])
                ->from('s_articles_categories_ro', 'cat')
                ->groupBy('cat.articleID');

            if (!empty($categoryId)) {
                $filterShopBuilder->where('cat.categoryID = :filter_shop');
                $builder->setParameter(':filter_shop', $categoryId);
            } else {
                $hmActiveShops = Shop::getActiveShops();
                $hmActiveShopsCatIds = array_map(function ($item) {
                    return $item['category_id'];
                }, $hmActiveShops);
                if (count($hmActiveShopsCatIds)) {
                    $filterShopBuilder->where(
                        $filterShopBuilder->expr()->in('cat.categoryID', implode(",", $hmActiveShopsCatIds))
                    );
                } else {
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
                $builder->orderBy($sort[self::PROPERTY], $sort['direction']);
            } else {
                $builder->orderBy('ordernumber', 'ASC');
            }

            $totalBuilder
                ->select('COUNT(d.id)')
//                ->from('s_articles_details', 'd')
                ->where($where);

            $data = $builder->execute()->fetchAll(PDO::FETCH_ASSOC);
            $total = $totalBuilder->execute()->fetchColumn(0);

            $this->View()->assign(['success' => true, 'data' => $data, 'total' => $total]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'data' => [], 'total' => 0, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return Enlight_View|Enlight_View_Default
     */
    public function changeStatusByIdAction()
    {
        $detailsId = $this->Request()->getParam('id', null);
        if (empty($detailsId)) {
            return $this->View()->assign(['success' => false, 'message' => 'No article details id passed!']);
        }

        $status = $this->Request()->getParam('status');
        if (!in_array($status, [StockManagement::STATUS_NEW, StockManagement::STATUS_BLOCKED], true)) {
            return $this->View()->assign([
                'success' => false,
                'message' => 'Unexpected status value. Expecting new or blocked.'
            ]);
        }

        $shopId = $this->Request()->getParam(self::SHOP_ID);
        if (empty($shopId)) {
            return $this->View()->assign(['success' => false, 'message' => 'No shop id is passed!']);
        }

        try {
            // Cleanup first
            if (StockManagement::STATUS_BLOCKED === $status) {
                $this->getStockManagement()->blockByDetailId($detailsId, $shopId);
            }

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get(self::DB);

            // Then remove data
            $connection->update(
                self::S_PLUGIN_HITME_STOCK,
                [
                    'status' => $status,
                    'last_access_date' => null,
                    'unit_id' => null
                ],
                [
                    'article_detail_id' => (int)$detailsId,
                    'shop_id' => (int)$shopId
                ],
                [
                    'status' => PDO::PARAM_STR,
                    'last_access_date' => PDO::PARAM_NULL,
                    'unit_id' => PDO::PARAM_NULL
                ]
            );

            $this->View()->assign(['success' => true]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return StockManagement
     */
    private function getStockManagement()
    {
        return $this->get('HmStockManagement');
    }

    /**
     * @return Enlight_View|Enlight_View_Default
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function changeShippinggroupByIdAction()
    {
        $detailsId = (int)$this->Request()->getParam('id');
        $shopId = (int)$this->Request()->getParam(self::SHOP_ID);
        $shippingGroup = $this->Request()->getParam('shippinggroup');

        $prepareChange = $this->prepareChangeSync($detailsId, $shopId);

        if (empty($prepareChange[self::ERROR_MSG])) {
            try {
                $detail = $prepareChange[self::DETAIL];
                $stock = $prepareChange[self::STOCK];
                $shop = $prepareChange[self::SHOP];

                $this->getStockManagement()->updateShippingGroup($detail, $shop, $shippingGroup, $stock);

                return $this->View()->assign(['success' => true]);
            } catch (Exception $e) {
                return $this->View()->assign(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            return $this->View()->assign(['success' => false, 'message' => $prepareChange[self::ERROR_MSG][0]]);
        }
    }

    /**
     * prepare data for changeShippinggroupByIdAction and syncStockByIdAction
     *
     * @param int $detailId
     * @param int $shopId
     *
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\ORMException
     */
    private function prepareChangeSync($detailId, $shopId)
    {
        $errorMessages = [];
        /** @var Shopware\Models\Article\Detail $detail */
        $detail = null;
        /** @var Shopware\Models\Shop\Shop $shop */
        $shop = null;
        /** @var Shopware\CustomModels\HitmeMarketplace\Stock $stock */
        $stock = null;

        if ($detailId !== null) {
            try {
                $detail = Shopware()->Models()->find('Shopware\Models\Article\Detail', $detailId);
            } catch (Exception $e) {
                $errorMessages[] = $e->getMessage();
            }
        } else {
            $errorMessages[] = 'No article details id passed!';
        }

        if ($shopId !== null) {
            $shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shopId);
            if (null === $shop) {
                $errorMessages[] = sprintf('Shop %d not found!', $shopId);
            }
        } else {
            $errorMessages[] = 'No shop id is passed!';
        }

        if (empty($errorMessages)) {
            $stockRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\HitmeMarketplace\Stock');
            $builder = $stockRepository->createQueryBuilder('Stock')
                ->where('Stock.shopId = :shopId')
                ->andWhere('Stock.articleDetailId = :articleDetailId');

            $builder->setParameters([
                self::SHOP_ID => $shopId,
                'articleDetailId' => $detail->getId()
            ]);

            $stock = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_OBJECT);
        }

        return [
            self::ERROR_MSG => $errorMessages,
            self::DETAIL => $detail,
            self::SHOP => $shop,
            self::STOCK => $stock
        ];
    }

    /**
     * sync stock by id
     *
     * @return Enlight_View|Enlight_View_Default
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\ORMException
     */
    public function syncStockByIdAction()
    {
        $detailsId = $this->Request()->getParam('id', null);
        $shopId = $this->Request()->getParam(self::SHOP_ID);
        $prepareSync = $this->prepareChangeSync($detailsId, $shopId);

        if (empty($prepareSync[self::ERROR_MSG])) {
            try {
                $detail = $prepareSync[self::DETAIL];
                $stock = $prepareSync[self::STOCK];
                $shop = $prepareSync[self::SHOP];

                $this->getStockManagement()->syncByArticleDetails($detail, $shop, $stock, true);

                return $this->View()->assign(['success' => true]);
            } catch (Exception $e) {
                return $this->View()->assign(
                    [
                        'success' => false,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]
                );
            }
        } else {
            return $this->View()->assign(['success' => false, 'message' => $prepareSync[self::ERROR_MSG][0]]);
        }
    }

    public function readyForSyncAction()
    {
        $shopId = $this->Request()->getParam(self::SHOP_ID);
        if (empty($shopId)) {
            return $this->View()->assign(['success' => false, 'message' => 'No shop id passed!']);
        }

        /** @var Shopware\Models\Shop\Shop $shop */
        $shop = Shopware()->Models()->find('Shopware\Models\Shop\Shop', $shopId);
        if (null === $shop) {
            return $this->View()->assign(['success' => false, 'message' => sprintf('Shop %d not found!', $shopId)]);
        }

        $sql = "SELECT d.`id`
                FROM `s_articles_details` d
                LEFT JOIN `s_plugin_hitme_stock` a ON d.id = a.article_detail_id
                WHERE d.`ean` IS NOT NULL AND d.`ean` != ''
                AND (a.`status` NOT IN (?) OR a.`status` IS NULL )
                AND d.articleID IN
                ( 
                    SELECT cat.articleID
                    FROM s_articles_categories_ro cat
                    WHERE cat.categoryID = ?
                    GROUP BY cat.articleID
                )";

        try {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get(self::DB);
            $stmt = $connection->executeQuery($sql, [StockManagement::STATUS_BLOCKED, $shop->getCategory()->getId()]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->View()->assign(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'data' => [], 'message' => $e->getMessage()]);
        }
    }

    public function changeStatusAllAction()
    {
        $shopId = $this->Request()->getParam(self::SHOP_ID);
        if (empty($shopId)) {
            return $this->View()->assign(['success' => false, 'message' => 'No shop id is passed!']);
        }

        $status = $this->Request()->getParam('status');
        if (!in_array($status, [StockManagement::STATUS_NEW, StockManagement::STATUS_BLOCKED], true)) {
            return $this->View()->assign([
                'success' => false,
                'message' => 'Unexpected status value. Expecting new or blocked.'
            ]);
        }

        try {
            // Cleanup first
            $this->getStockManagement()->flushInventory($shopId);

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get(self::DB);

            // Then update data
            $connection->update(
                self::S_PLUGIN_HITME_STOCK,
                [
                    'status' => $status,
                    'last_access_date' => null,
                    'unit_id' => null
                ],
                [
                    'shop_id' => (int)$shopId,
                ],
                [
                    'status' => PDO::PARAM_STR,
                    'last_access_date' => PDO::PARAM_NULL,
                    'unit_id' => PDO::PARAM_NULL
                ]
            );

            $this->View()->assign(['success' => true]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Whitelist notify- and webhook-actions
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'getList',
            'changeStatusById',
            'changeShippinggroupById',
            'syncStockById',
            'readyForSync',
            'changeStatusAll'
        ];
    }
}
