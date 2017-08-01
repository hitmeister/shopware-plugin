<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Shopware\Models\Shop\Shop as SwShop;

/**
 * Class Shop
 *
 * @package ShopwarePlugins\HitmeMarketplace\Components
 */
class Shop
{
    protected $shopConfig;

    protected $shopConfigRaw;

    public static function getActiveShops()
    {
        $subShops = [];
        $shops = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->getActiveShops();
        /** @var \Shopware\Models\Shop\Shop $shop */
        foreach ((array)$shops as $shop) {
            $shopId = $shop->getId();
            $shopConfig = self::getShopConfigByShopId($shopId);

            if ($shopConfig->get('syncStatus') === 1) {
                $subShops[$shopId] = [
                    'id' => $shopId,
                    'name' => $shop->getName(),
                    'category_id' => $shop->getCategory()->getId(),
                    'hm_config' => $shopConfig
                ];
            }
        }

        return $subShops;
    }

    public static function getShopConfigByShopId($shopId)
    {
        $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId);
        return self::getSwShopConfigByShop($shop);
    }

    private static function getSwShopConfigByShop(SwShop $shop)
    {
        $config = [];
        $config['shop'] = $shop;
        $config['db'] = Shopware()->Db();

        return  new \Shopware_Components_Config($config);
    }

    /**
     * @param $shopId
     * @param bool $withBaseFile
     *
     * @return string
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getShopUrl($shopId, $withBaseFile = false)
    {
        $shopConfig = self::getShopConfigByShopId($shopId);
        /* @var $shop \Shopware\Models\Shop\Shop */
        $shop = Shopware()->Models()->find("Shopware\\Models\\Shop\\Shop", $shopId);
        $host = $shop->getMain() !== null ? $shop->getMain()->getHost() : $shop->getHost();
        $basePath = $shop->getMain() !== null ? $shop->getMain()->getBasePath() : $shop->getBasePath();
        $baseUrl = $shop->getBaseUrl();
        $secure = $shop->getSecure() === true ? 'https' : 'http';

        $shopUrl = $secure . '://' . $host;
        if (!empty($baseUrl)) {
            $shopUrl .= $baseUrl;
        } elseif (!empty($basePath)) {
            $shopUrl .= $basePath;
        }

        $baseFile = $shopConfig->get('baseFile');

        return $shopUrl . DIRECTORY_SEPARATOR . ($withBaseFile ? $baseFile : '');
    }
}
