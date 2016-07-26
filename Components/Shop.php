<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Shopware\Models\Shop\Shop as SwShop;

class Shop
{
    protected $shopConfig;

    protected $shopConfigRaw;

    public static function getActiveShops()
    {
        $subshops = array();
        $shops = Shopware()->Models()->getRepository(
          'Shopware\Models\Shop\Shop'
        )->getActiveShops();

        foreach ($shops as $shop) {
            $shopId = $shop->getId();
            $shopConfig = self::getShopConfigByShopId($shopId);
            if($shopConfig->get('syncStatus') == 1){
                $subshops[$shopId] = array(
                  'id' => $shop->getId(),
                  'name' => $shop->getName(),
                  'category_id' => $shop->getCategory()->getId(),
                  'hm_config' => $shopConfig->toArray()
                );
            }
        }

        return $subshops;

    }

    public static function getShopConfigByShopId($shopId){
        $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId );
        return self::getSwShopConfigByShop($shop);
    }

    private function getSwShopConfigByShop(SwShop $shop)
    {
        $config = array();
        $config['shop'] = $shop;
        $config['db'] = Shopware()->Db();

        $subshopConfig = new \Shopware_Components_Config( $config );

        return $subshopConfig;
    }

}