<?php

use ShopwarePlugins\HmMarketplace\Components\StockManagement;

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
                    'da.hm_unit_id',
                    'da.hm_last_access_date',
                    'da.hm_status',
                ))
                ->from('s_articles_details', 'd')
                ->innerJoin('d', 's_articles', 'a', 'a.id = d.articleID')
                ->innerJoin('d', 's_articles_attributes', 'da', 'da.articledetailsID = d.id AND da.articleID = a.id')
                ->leftJoin('d', '(' . $joinBuilder->getSQL() . ')', 'v', 'v.article_id = d.id');

            $where = $builder
                ->expr()
                ->andX('d.active = 1');

            if (!empty($filter)) {
                $or = $builder->expr()->orX();

                foreach ($filter as $item) {
                    $prefix = ($item['property'] == 'name') ? 'a' : 'd';
                    $or->add($prefix . '.' . $item['property'] . ' LIKE :' . $item['property']);
                    $builder->setParameter(':' . $item['property'], '%' . $item['value'] . '%');
                }

                $where->add($or);
            }

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

        try {
            // Cleanup first
            if (StockManagement::STATUS_BLOCKED == $status) {
                $this->getStockManagement()->blockByDetailId($detailsId);
            }

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get('dbal_connection');

            // Then remove data
            $connection->update('s_articles_attributes',
                array(
                    'hm_status' => $status,
                    'hm_last_access_date' => null,
                    'hm_unit_id' => null,
                ),
                array(
                    'articledetailsID' => (int)$detailsId,
                ),
                array(
                    'hm_status' => PDO::PARAM_STR,
                    'hm_last_access_date' => PDO::PARAM_NULL,
                    'hm_unit_id' => PDO::PARAM_NULL,
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

        try {
            $this->getStockManagement()->syncByArticleDetails($detail, true);

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function readyForSyncAction()
    {
        $sql = "SELECT d.`id` FROM `s_articles_details` d LEFT JOIN `s_articles_attributes` a ON d.id = a.articledetailsID WHERE d.`ean` IS NOT NULL AND d.`ean` != '' AND a.`hm_status` NOT IN ('%s')";
        $query = sprintf($sql, StockManagement::STATUS_BLOCKED);

        try {
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = Shopware()->Container()->get('dbal_connection');
            $stmt = $connection->executeQuery($query);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->View()->assign(array('success' => true, 'data' => $data));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'data' => array(), 'message' => $e->getMessage()));
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