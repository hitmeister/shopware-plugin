<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

use Exception;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Zend_Db_Adapter_Pdo_Mysql;

/**
 * Class Attributes
 *
 * @package ShopwarePlugins\HitmeMarketplace\Bootstrap
 */
class Attributes
{
    public static function create()
    {
        self::createCategoryAttributes();
        self::createOrderAttributes();

        Shopware()->Models()->generateAttributeModels(
            [
                's_categories_attributes',
                's_order_attributes',
                's_order_details_attributes'
            ]
        );
    }

    public static function createCategoryAttributes()
    {
        Shopware()->Models()->addAttribute(
            's_categories_attributes',
            'hm',
            'category_id',
            'varchar(20)', // BIGINT(20) unsigned
            true
        );

        Shopware()->Models()->addAttribute(
            's_categories_attributes',
            'hm',
            'category_title',
            'varchar(255)',
            true
        );
    }

    public static function createOrderAttributes()
    {
        Shopware()->Models()->addAttribute(
            's_order_attributes',
            'hm',
            'order_id',
            'varchar(20)',
            true
        );

        Shopware()->Models()->addAttribute(
            's_order_details_attributes',
            'hm',
            'order_unit_id',
            'varchar(20)',
            true
        );

        Shopware()->Models()->addAttribute(
            's_order_details_attributes',
            'hm',
            'status',
            'varchar(20)',
            true
        );
    }

    public static function fixDev2210()
    {
        Shopware()->Db()->query(
            'ALTER TABLE `s_articles_attributes` MODIFY COLUMN `hm_status` VARCHAR(20) NULL DEFAULT NULL'
        );
        Shopware()->Db()->query(
            'ALTER TABLE `s_order_details_attributes` MODIFY COLUMN `hm_status` VARCHAR(20) NULL DEFAULT NULL'
        );
    }

    /**
     * @throws \InvalidArgumentException
     * @throws Exception
     */
    public static function copyAttributesInSchemaV200()
    {
        $defaultShopId = Shopware()->Plugins()->Backend()->HitmeMarketplace()->Config()->get('defaultShop');
        if (empty($defaultShopId)) {
            throw new Exception('This plugin config requires a default shop');
        }

        $sql = Shopware()->Db()->select()
            ->from('s_articles_attributes', ['articledetailsID', 'hm_unit_id', 'hm_last_access_date', 'hm_status'])
            ->where('hm_unit_id IS NOT NULL');
        $res = Shopware()->Db()->fetchAll($sql);
        foreach ($res as $row) {
            $data = [
                'shop_id' => $defaultShopId,
                'article_detail_id' => $row['articledetailsID'],
                'unit_id' => $row['hm_unit_id'],
                'last_access_date' => $row['hm_last_access_date'],
                'status' => $row['hm_status']
            ];
            Shopware()->Db()->insert('s_plugin_hitme_stock', $data);
        }

        /** @var CrudService $crudService */
        $crudService = Shopware()->Container()->get('shopware_attribute.crud_service');

        $crudService->delete('s_articles_attributes', 'hm_unit_id');
        $crudService->delete('s_articles_attributes', 'hm_last_access_date');
        $crudService->delete('s_articles_attributes', 'hm_last_status');

        Shopware()->Models()->generateAttributeModels(
            [
                's_articles_attributes'
            ]
        );
    }

    /**
     * returns column names of s_articles_attributes
     *
     * @return array
     * @throws Exception
     */
    public static function getAttributesColumnNames()
    {
        /** @var Zend_Db_Adapter_Pdo_Mysql $db Pdo adapter for Mysql */
        $db = Shopware()->Container()->get('db');

        /** @var array $dbCfg */
        $dbCfg = $db->getConfig();
        $dbName = $dbCfg['dbname'];

        $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = 's_articles_attributes'
                      AND COLUMN_NAME NOT IN ('id', 'articledetailsID', 'articleID')";

        return Shopware()->Db()->fetchCol($sql, $dbName);
    }
}
