<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

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
                's_order_details_attributes',
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
     * @TODO for SW5.3 use CRUD Service instead of Shopware()->Models()->removeAttribute();
     */
    public static function copyAttributesInSchemaV200()
    {
        $defaultShopId = Shopware()->Plugins()->Backend()->HitmeMarketplace()->Config()->get('defaultShop');
        if (empty($defaultShopId)) {
            throw new \Exception('This plugin config requires a default shop');
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
                'status' => $row['hm_status'],
            ];
            Shopware()->Db()->insert('s_plugin_hitme_stock', $data);
        }

        Shopware()->Models()->removeAttribute(
            's_articles_attributes',
            'hm',
            'unit_id'
        );

        Shopware()->Models()->removeAttribute(
            's_articles_attributes',
            'hm',
            'last_access_date'
        );

        Shopware()->Models()->removeAttribute(
            's_articles_attributes',
            'hm',
            'status'
        );

        Shopware()->Models()->generateAttributeModels(
            [
                's_articles_attributes',
            ]
        );
    }
}
