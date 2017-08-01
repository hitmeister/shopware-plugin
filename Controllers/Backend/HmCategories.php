<?php

use ShopwarePlugins\HitmeMarketplace\Components\CategoryFetcher;
use Shopware\Components\CSRFWhitelistAware;

/**
 * Class Shopware_Controllers_Backend_HmCategories
 */
class Shopware_Controllers_Backend_HmCategories extends Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware
{
    /**
     * Returns local categories
     */
    public function getLocalListAction()
    {
        $node = (int)$this->Request()->getParam('node');
        $node = !empty($node) ? $node : 1;

        /** @var \Doctrine\DBAL\Query\QueryBuilder $subBuilder */
        $subBuilder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $subBuilder
            ->select('COUNT(c2.id)')
            ->from('s_categories', 'c2')
            ->where('c2.parent = c.id');

        /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
        $builder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $builder
            ->select(array(
                'c.id',
                'c.active',
                'c.description',
                'a.hm_category_id',
                'a.hm_category_title',
            ))
            ->addSelect('(' . $subBuilder->getSQL() . ') as children_count')
            ->from('s_categories', 'c')
            ->leftJoin('c', 's_categories_attributes', 'a', 'c.id = a.categoryID')
            ->where('c.parent = ?')
            ->orderBy('c.parent')
            ->addOrderBy('c.position')
            ->setParameter(0, $node);

        $data = $builder->execute()->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as $key => &$category) {
            $category['leaf'] = 0 == (int)$category['children_count'];
            unset($category['children_count']);
        }

        $this->View()->assign(array('success' => true, 'data' => $data));
    }

    /**
     * Builds category tree
     */
    public function getTreeAction()
    {
        $rootNode = $this->getCategoryFetcher()->buildTree();
        $rootNode['expanded'] = true;
        $this->View()->assign(array('success' => true, 'children' => $rootNode));
    }

    public function updateMapAction()
    {
        $id = (int)$this->Request()->getParam('id');
        if (empty($id)) {
            return $this->View()->assign(array('success' => false));
        }

        $hmId = $this->Request()->getParam('hm_category_id');
        $hmTitle = $this->Request()->getParam('hm_category_title');

        if (empty($hmId)) {
            $hmId = null;
        }
        if (empty($hmTitle)) {
            $hmTitle = null;
        }

        /** @var \Doctrine\DBAL\Query\QueryBuilder $builder */
        $builder = Shopware()->Container()->get('dbal_connection')->createQueryBuilder();
        $builder->update('s_categories_attributes', 'a')
            ->set('a.hm_category_id', ':hm_category_id')
            ->set('a.hm_category_title', ':hm_category_title')
            ->where('a.categoryID = :id')
            ->setParameter(':id', $id)
            ->setParameter(':hm_category_id', $hmId)
            ->setParameter(':hm_category_title', $hmTitle)
            ->execute();

        $this->View()->assign(array('success' => true));
    }

    /**
     * @return CategoryFetcher
     */
    private function getCategoryFetcher()
    {
        return $this->get('HmCategoryFetcher');
    }

    /**
     * Whitelist notify- and webhook-actions
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'getLocalList',
            'getTree',
            'updateMap'
        );
    }
}
