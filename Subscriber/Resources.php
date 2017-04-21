<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Hitmeister\Component\Api\ClientBuilder;
use ShopwarePlugins\HitmeMarketplace\Components\CategoryFetcher;
use ShopwarePlugins\HitmeMarketplace\Components\Exporter;
use ShopwarePlugins\HitmeMarketplace\Components\Ordering;
use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;

/**
 * Class Resources
 * @package ShopwarePlugins\HitmeMarketplace\Subscriber
 */
class Resources implements SubscriberInterface
{
    private $config;

    /**
     * @param \Enlight_Config $config
     */
    public function __construct(\Enlight_Config $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Bootstrap_InitResource_HmApi' => 'onInitApi',
            'Enlight_Bootstrap_InitResource_HmCategoryFetcher' => 'onInitCategoryFetcher',
            'Enlight_Bootstrap_InitResource_HmStockManagement' => 'onInitStockManagement',
            'Enlight_Bootstrap_InitResource_HmExporter' => 'onInitExporter',
            'Enlight_Bootstrap_InitResource_HmOrdering' => 'onInitOrdering',
        ];
    }

    /**
     * @return bool|\Hitmeister\Component\Api\Client
     * @throws \Exception
     */
    public function onInitApi()
    {
        $shopConfig = $this->config;
        if (Shopware()->Front()->Request()->has('shopId')) {
            $shopId = (int)Shopware()->Front()->Request()->getParam('shopId');
            if (!empty($shopId)) {
                $shopConfig = Shop::getShopConfigByShopId($shopId);
            }
        }
        $apiUrl = $shopConfig->get('apiUrl');
        $clientKey = $shopConfig->get('clientKey');
        $secretKey = $shopConfig->get('secretKey');

        if ($clientKey === null || $secretKey === null) {
            return false;
        }

        $builder = new ClientBuilder();
        $builder
            ->setLogger(Shopware()->Container()->get('pluginlogger'))
            ->setBaseUrl($apiUrl)
            ->setClientKey($clientKey)
            ->setClientSecret($secretKey);

        return $builder->build();
    }

    /**
     * @return CategoryFetcher
     * @throws \Exception
     */
    public function onInitCategoryFetcher()
    {
        return new CategoryFetcher(
            Shopware()->Container()->get('HmApi'),
            Shopware()->Container()->get('cache')
        );
    }

    /**
     * @return StockManagement
     * @throws \Exception
     */
    public function onInitStockManagement()
    {
        return new StockManagement(
            Shopware()->Container()->get('HmApi'),
            Shopware()->Container()->get('dbal_connection'),
            $this->config->get('defaultDelivery'),
            $this->config->get('defaultCondition')
        );
    }

    /**
     * @return Exporter
     * @throws \Exception
     */
    public function onInitExporter()
    {
        return new Exporter(
            Shopware()->Container()->get('dbal_connection'),
            Shopware()->Container()->getParameter('kernel.cache_dir')
        );
    }

    /**
     * @return Ordering
     * @throws \Exception
     */
    public function onInitOrdering()
    {
        return new Ordering(
            Shopware()->Container()->get('HmApi'),
            $this->config->get('defaultDeliveryMethod'),
            $this->config->get('defaultPaymentMethod'),
            Shopware()->Shop()
        );
    }
}
