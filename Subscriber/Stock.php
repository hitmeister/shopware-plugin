<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Psr\Log\LoggerInterface;
use Shopware\Models\Article\Detail;
use Shopware\Models\Shop\Shop;
use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;

class Stock implements SubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'product_stock_was_changed' => 'onOrderStockChanged',
            'Shopware\Models\Article\Detail::postUpdate' => 'onDetailsStockUpdate',
            'Shopware\Models\Article\Detail::postPersist' => 'onDetailsStockUpdate',
        ];
    }

    /**
     * @param array $data
     *
     * @throws \Exception
     */
    public function onOrderStockChanged($data)
    {
        /** @var Detail $details */
        $detail = Shopware()->Models()
            ->getRepository('Shopware\Models\Article\Detail')
            ->findOneBy([
                'number' => $data['number'],
            ]);

        $this->updateStock($detail);
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @throws \Exception
     */
    public function onDetailsStockUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Detail $details */
        $details = $args->get('entity');
        $this->updateStock($details);
    }

    /**
     * @param Detail $detail
     *
     * @throws \Exception
     */
    private function updateStock(Detail $detail)
    {
        if (!$detail) { // Do not check for EAN here
            return;
        }

        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');

        /** @var StockManagement $manager */
        $manager = Shopware()->Container()->get('HmStockManagement');

        /** @var Shop $shop */
        $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->findOneBy(['default' => true]);

        /** @var \Shopware\CustomModels\HitmeMarketplace\Stock $stock */
        $stockRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\HitmeMarketplace\Stock');
        $builder = $stockRepository->createQueryBuilder('Stock')
            ->where('Stock.shopId = :shopId')
            ->andWhere('Stock.articleDetailId = :articleDetailId');

        $builder->setParameters([
            'shopId' => $shop->getId(),
            'articleDetailId' => $detail->getId()
        ]);

        $stock = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_OBJECT);

        try {
            $res = $manager->syncByArticleDetails($detail, true, $stock, $shop);
            $logger->info('Stock auto update', ['number' => $detail->getNumber(), 'res' => (int)$res]);
        } catch (\Exception $e) {
            $logger->error('Error on stock sync', ['number' => $detail->getNumber(), 'exception' => $e]);
        }
    }
}
