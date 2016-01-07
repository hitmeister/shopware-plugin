<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Hitmeister\Component\Api\ClientBuilder;
use ShopwarePlugins\HitmeMarketplace\Components\CategoryFetcher;
use ShopwarePlugins\HitmeMarketplace\Components\Exporter;
use ShopwarePlugins\HitmeMarketplace\Components\Ordering;
use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;

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
        return array(
            'Enlight_Bootstrap_InitResource_HmApi' => 'onInitApi',
            'Enlight_Bootstrap_InitResource_HmCategoryFetcher' => 'onInitCategoryFetcher',
            'Enlight_Bootstrap_InitResource_HmStockManagement' => 'onInitStockManagement',
            'Enlight_Bootstrap_InitResource_HmExporter' => 'onInitExporter',
            'Enlight_Bootstrap_InitResource_HmOrdering' => 'onInitOrdering',
        );
    }

    /**
     * @return \Hitmeister\Component\Api\Client
     * @throws \Exception
     */
    public function onInitApi()
    {
        $builder = new ClientBuilder();
        $builder
            ->setLogger(Shopware()->Container()->get('pluginlogger'))
            ->setBaseUrl($this->config->get('apiUrl'))
            ->setClientKey($this->config->get('clientKey'))
            ->setClientSecret($this->config->get('secretKey'));

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
            Shopware()->Container()->get('dbal_connection'),
            Shopware()->Container()->get('HmApi'),
            $this->config->get('defaultDeliveryMethod'),
            $this->config->get('defaultPaymentMethod'),
            $this->config->get('defaultShop')
        );
    }
}