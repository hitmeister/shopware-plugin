<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Doctrine\DBAL\Connection;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Exceptions\ResourceNotFoundException;
use Hitmeister\Component\Api\Transfers\Constants;
use Hitmeister\Component\Api\Transfers\UnitAddTransfer;
use Hitmeister\Component\Api\Transfers\UnitUpdateTransfer;
use Shopware\Models\Article\Detail;
use Shopware\CustomModels\HitmeMarketplace\Stock;
use Shopware\Models\Shop\Shop;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;


class StockManagement
{
    // length = 20 chars
    const STATUS_NEW = 'new';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_NOT_FOUND = 'not_found_on_hm';
    const STATUS_SYNCHRONIZING = 'synchronizing';

    /** @var Client */
    private $apiClient;

    /** @var Connection */
    private $connection;

    /** @var string */
    private $defaultDelivery = Constants::DELIVERY_TIME_H;

    /** @var string */
    private $defaultCondition = Constants::CONDITION_NEW;

    /**
     * @param Client $apiClient
     * @param Connection $connection
     * @param string $defaultDelivery
     * @param string $defaultCondition
     */
    public function __construct(Client $apiClient, Connection $connection, $defaultDelivery, $defaultCondition)
    {
        $this->apiClient = $apiClient;
        $this->connection = $connection;
        $this->defaultDelivery = $defaultDelivery;
        $this->defaultCondition = $defaultCondition;
    }

    /**
     * @param int $id
     * @param int $shopId
     */
    public function blockByDetailId($id, $shopId)
    {
        $q = sprintf('SELECT `unit_id` FROM `s_plugin_hitme_stock` WHERE `article_detail_id` = %d AND `shop_id` = %d LIMIT 1', $id, $shopId);
        $stmt = $this->connection->executeQuery($q);
        $hmUnitId = $stmt->fetchColumn(0);

        if ($hmUnitId) {
            $this->apiClient->units()->delete($hmUnitId);
        }
    }

    /**
     * @param int $shopId
     */
    public function flushInventory($shopId)
    {
        $shopUrl = HmShop::getShopUrl($shopId,true);
        $callback = $shopUrl . "?sViewport=Hm&sAction=flushCommand";
        $this->apiClient->importFiles()->post($callback);
    }

    /**
     * @param Detail $detail
     * @param bool $forceNotFound
     * @param Stock $stock
     * @param Shop $shop
     * @return bool
     * @throws \Exception
     */
    public function syncByArticleDetails(Detail $detail, $forceNotFound = false, Stock $stock, Shop $shop)
    {
        $attr = $detail->getAttribute();

        // For some reason there may be no stock object
        if ($stock == null) {
            $stock = new Stock();
            $stock->setArticleDetailId($detail);
            $stock->setShopId($shop);
            $stock->setStatus(self::STATUS_NEW);

            Shopware()->Models()->persist($stock);
            Shopware()->Models()->flush($stock);
        }

        $hmUnitId = $stock->getUnitId();

        if ('' == $detail->getEan()) {
            // Delete unit if it was here before
            if ($hmUnitId) {
                $this->deleteUnit($stock);
            }

            // If article had non new status, clean it up
            if (self::STATUS_NEW != $stock->getStatus()) {
                $this->updateStatus($stock, self::STATUS_NEW);
            }

            throw new \Exception('Ean is missing.');
        }

        // If user doesn't want sync
        if (self::STATUS_BLOCKED == $stock->getStatus()) {
            if ($hmUnitId) {
                $this->deleteUnit($stock);
            }
            return false;
        }

        // If article was not found on Hm before, lets skip it, and then check it again in couple of days
        if (self::STATUS_NOT_FOUND == $stock->getStatus() && !$forceNotFound) {
            $last = $stock->getLastAccessDate();
            if ($last && strtotime($last) > strtotime('-3 days')) {
                return false;
            }
        }

        // Update last access date
        $this->updateLastAccess($stock);

        // Delete if article is not active
        if (!$detail->getActive() && $hmUnitId) {
            return $this->deleteUnit($stock);
        }

        // Look for price
        $price = $this->getPrice($detail,$shop);

        // Items in stock
        $inStock = $detail->getInStock();

        switch (true) {
            // Dummy
            case !$hmUnitId && !$inStock:
                $this->updateStatus($stock, self::STATUS_SYNCHRONIZING);
                break;

            // Create stock
            case !$hmUnitId && $inStock:
                return $this->postUnit($detail, $price, $stock);

            // Update stock
            case $hmUnitId && $inStock:
                return $this->updateUnit($hmUnitId, $detail, $price, $stock);

            // Delete from stock
            case $hmUnitId && !$inStock:
                return $this->deleteUnit($stock);
        }

        return true;
    }


    /**
     * @param Detail $detail
     * @param int $price
     * @param Stock $stock
     * @return bool
     * @throws \Exception
     */
    private function postUnit(Detail $detail, $price, Stock $stock)
    {
        $transfer = new UnitAddTransfer();
        $transfer->ean = $detail->getEan();
        $transfer->condition = $this->defaultCondition;
        $transfer->listing_price = $price;
        $transfer->minimum_price = $price;
        $transfer->amount = $detail->getInStock();
        $transfer->id_offer = $detail->getNumber();
        $transfer->note = $detail->getAdditionalText();
        $transfer->delivery_time = $this->getDeliveryTimeByDays($detail->getShippingTime());
        $transfer->shipping_group = $this->getShippingGroup($stock);

        try {
            $hmUnitId = $this->apiClient->units()->post($transfer);
        } // Not possible to sell this item (EAN not found)
        catch (ResourceNotFoundException $e) {
            $this->updateStatus($stock, self::STATUS_NOT_FOUND);
            return false;
        }

        if (!$hmUnitId) {
            throw new \Exception('Error on post article on Hitmeister. Got empty unit id.');
        }

        $stock->setStatus(self::STATUS_SYNCHRONIZING);
        $stock->setUnitId($hmUnitId);
        Shopware()->Models()->flush($stock);

        return true;
    }

    /**
     * @param string $hmUnitId
     * @param Detail $detail
     * @param int $price
     * @param Stock $stock
     * @return bool
     */
    private function updateUnit($hmUnitId, Detail $detail, $price, Stock $stock)
    {
        $transfer = new UnitUpdateTransfer();
        $transfer->condition = $this->defaultCondition;
        $transfer->listing_price = $price;
        $transfer->minimum_price = $price;
        $transfer->amount = $detail->getInStock();
        $transfer->id_offer = $detail->getNumber();
        $transfer->note = $detail->getAdditionalText();
        $transfer->delivery_time = $this->getDeliveryTimeByDays($detail->getShippingTime());
        $transfer->shipping_group = $this->getShippingGroup($stock);

        $res = $this->apiClient->units()->update($hmUnitId, $transfer);

        // Not found, lets post it again
        if (false === $res) {
            $res = $this->postUnit($detail, $price, $stock);
        }

        return $res;
    }

    private function deleteUnit(Stock $stock)
    {
        $res = $this->apiClient->units()->delete($stock->getUnitId());

        Shopware()->Models()->remove($stock);
        Shopware()->Models()->flush($stock);

        return $res;
    }

    /**
     * @param Detail $detail
     * @param Stock $stock
     * @param Shop $shop
     * @param string $shippinggroup
     * @return bool
     * @throws \Exception
     */
    public function updateShippinggroup(Detail $detail, Stock $stock, Shop $shop, $shippinggroup)
    {
        // For some reason there may be no stock object
        if ($stock == null) {
            $stock = new Stock();
            $stock->setArticleDetailId($detail);
            $stock->setShopId($shop);
            $stock->setShippinggroup($shippinggroup);
            Shopware()->Models()->persist($stock);
        }else{
            $stock->setShippinggroup($shippinggroup);

        }
        Shopware()->Models()->flush($stock);

        return true;
    }

    /**
     * @param Stock $stock
     */
    private function updateLastAccess(Stock $stock)
    {
        $stock->setLastAccessDate(date('Y-m-d H:i:s'));
        Shopware()->Models()->flush($stock);
    }

    /**
     * @param Stock $stock
     * @param string $status
     */
    private function updateStatus($stock, $status)
    {
        $stock->setStatus($status);
        Shopware()->Models()->flush($stock);
    }

    /**
     * @param mixed $days
     * @return string
     */
    private function getDeliveryTimeByDays($days)
    {
        if (null == $days || '' == $days) {
            return $this->defaultDelivery;
        }
        $days = (int)$days;

        switch (true) {
            case $days <= 1:
                return Constants::DELIVERY_TIME_A;
            case $days > 1 && $days <= 3:
                return Constants::DELIVERY_TIME_B;
            case $days > 4 && $days <= 6:
                return Constants::DELIVERY_TIME_C;
            case $days > 7 && $days <= 10:
                return Constants::DELIVERY_TIME_D;
            case $days > 11 && $days <= 14:
                return Constants::DELIVERY_TIME_E;
            case $days > 14 && $days <= 28:
                return Constants::DELIVERY_TIME_F;
            case $days > 28 && $days <= 49:
                return Constants::DELIVERY_TIME_G;
            case $days > 49 && $days <= 70:
                return Constants::DELIVERY_TIME_I;
        }

        return $this->defaultDelivery;
    }

    /**
     * @param Detail $detail
     * @return int
     * @throws \Exception
     */
    private function getPrice(Detail $detail,Shop $shop)
    {
        $pricegroup = $shop->getCustomerGroup()->getKey();
        $q = sprintf('SELECT `price` FROM `s_articles_prices` WHERE `articledetailsID` = %d AND `from` = 1 AND `pricegroup` = ? ORDER BY `price` ASC LIMIT 1', $detail->getId());
        $stmt = $this->connection->executeQuery($q, array($pricegroup));

        if (!($price = $stmt->fetchColumn(0)) && $pricegroup != 'EK') {
            $pricegroup = 'EK';
            $stmt = $this->connection->executeQuery($q, array($pricegroup));
        }

        if (!($price = $stmt->fetchColumn(0))) {
            throw new \Exception('There is no price for article details.');
        }

        if ($tax = $detail->getArticle()->getTax()) {
            $price = round((float)$price * (100 + (float)$tax->getTax()) / 100, 2);
        }

        return round($price * 100);
    }

    private function getShippingGroup(Stock $stock)
    {
        $shippingGroup = $stock->getShippinggroup();
        if(empty($shippingGroup)){
            $shopConfig = HmShop::getShopConfigByShopId($stock->getShopId());
            $shippingGroup = $shopConfig->get('defaultShippingGroup');
        }

        return $shippingGroup;
    }

}