<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Shopware\Models\Shop\Shop as SwShop;

/**
 * Class Shop
 * @package ShopwarePlugins\HitmeMarketplace\Components
 */
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
                  'hm_config' => $shopConfig
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

    /*
     * @param int $shopId
     * @param bool $withBaseFile
     * @return string
     */
    public static function getShopUrl($shopId, $withBaseFile = false)
    {
        $shopConfig = self::getShopConfigByShopId($shopId);
        /* @var $shop \Shopware\Models\Shop\Shop */
        $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId );
        $host = $shop->getMain() !== null ? $shop->getMain()->getHost() : $shop->getHost();
        $basePath = $shop->getMain() !== null ? $shop->getMain()->getBasePath() : $shop->getBasePath();
        $baseUrl = $shop->getBaseUrl();

        $shopUrl = 'http://' . $host;
        if(!empty($baseUrl)){
            $shopUrl .= $baseUrl;
        }elseif(!empty($basePath)){
            $shopUrl .= $basePath;
        }

        $baseFile = $shopConfig->get('baseFile');
        $shopUrl .= DIRECTORY_SEPARATOR;
        $shopUrl .= $withBaseFile ? $baseFile : '';

        return $shopUrl;

    }
}
