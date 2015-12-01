<?php

namespace ShopwarePlugins\HmMarketplace\Bootstrap;

class Attributes
{
    public static function create()
    {
        self::createCategoryAttributes();
        self::createArticleAttributes();
        self::createOrderAttributes();

        Shopware()->Models()->generateAttributeModels(array(
            's_categories_attributes',
            's_articles_attributes',
            's_order_attributes',
            's_order_details_attributes',
        ));
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

    public static function createArticleAttributes()
    {
        Shopware()->Models()->addAttribute(
            's_articles_attributes',
            'hm',
            'unit_id',
            'varchar(20)', // BIGINT(20) unsigned
            true
        );

        Shopware()->Models()->addAttribute(
            's_articles_attributes',
            'hm',
            'last_access_date',
            'datetime',
            true
        );

        Shopware()->Models()->addAttribute(
            's_articles_attributes',
            'hm',
            'status',
            'varchar(20)',
            false,
            'new'
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
            false,
            'new'
        );
    }
}