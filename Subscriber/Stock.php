<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Shopware\CustomModels\HitmeMarketplace\Stock as StockEntity;
use Shopware\Models\Article\Detail;
use Shopware\Models\Shop\Shop as SwShop;
use ShopwarePlugins\HitmeMarketplace\Components\StockManagement;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;

/**
 * Class Stock
 *
 * @package ShopwarePlugins\HitmeMarketplace\Subscriber
 */
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
            'Shopware\Models\Article\Detail::postPersist' => 'onDetailsStockUpdate'
        ];
    }

    /**
     * @param array $data
     *
     * @throws Exception
     */
    public function onOrderStockChanged($data)
    {
        /** @var Detail $details */
        $detail = Shopware()->Models()
            ->getRepository('Shopware\Models\Article\Detail')
            ->findOneBy([
                'number' => $data['number']
            ]);
        
        $this->updateStock($detail);
    }
    
    /**
     * @param Detail|null $detail
     *
     * @throws Exception
     */
    private function updateStock(Detail $detail = null)
    {
        if (null === $detail) { // Do not check for EAN here
            return;
        }
        
        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');
        
        /** @var StockManagement $stockManager */
        $stockManager = Shopware()->Container()->get('HmStockManagement');

        /** @var SwShop $shop */
        $shop = HmShop::getShopByArticleId($detail->getArticleId());

        /** @var \Doctrine\ORM\EntityRepository $stockRepository */
        $stockRepository = Shopware()->Models()->getRepository('Shopware\CustomModels\HitmeMarketplace\Stock');
        $builder = $stockRepository->createQueryBuilder('Stock')
            ->where('Stock.shopId = :shopId')
            ->andWhere('Stock.articleDetailId = :articleDetailId');
        
        $builder->setParameters([
            'shopId' => $shop->getId(),
            'articleDetailId' => $detail->getId()
        ]);
        
        /** @var StockEntity $stock */
        $stock = $builder->getQuery()->getOneOrNullResult(\Doctrine\ORM\AbstractQuery::HYDRATE_OBJECT);

        try {
            $res = $stockManager->syncByArticleDetails($detail, $shop, $stock, true);
            $logger->info('Stock auto update', ['number' => $detail->getNumber(), 'res' => (int)$res]);
        } catch (Exception $e) {
            $logger->error('Error on stock sync', ['number' => $detail->getNumber(), 'exception' => $e]);
        }
    }
    
    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @throws Exception
     */
    public function onDetailsStockUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Detail $details */
        $details = $args->get('entity');

        $this->updateStock($details);
    }
}
