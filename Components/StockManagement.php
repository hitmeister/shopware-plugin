<?php

namespace ShopwarePlugins\HitmeMarketplace\Components;

use Doctrine\DBAL\Connection;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Exceptions\ResourceNotFoundException;
use Hitmeister\Component\Api\Transfers\Constants;
use Hitmeister\Component\Api\Transfers\UnitAddTransfer;
use Hitmeister\Component\Api\Transfers\UnitUpdateTransfer;
use Shopware\Models\Article\Detail;

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
     */
    public function blockByDetailId($id)
    {
        $q = sprintf('SELECT `hm_unit_id` FROM `s_articles_attributes` WHERE `articledetailsID` = %d LIMIT 1', $id);
        $stmt = $this->connection->executeQuery($q);
        $hmUnitId = $stmt->fetchColumn(0);

        if ($hmUnitId) {
            $this->apiClient->units()->delete($hmUnitId);
        }
    }

    /**
     * @param Detail $detail
     * @param bool $forceNotFound
     * @return bool
     * @throws \Exception
     */
    public function syncByArticleDetails(Detail $detail, $forceNotFound = false)
    {
        $attr = $detail->getAttribute();
        $hmUnitId = $attr->getHmUnitId();

        if ('' == $detail->getEan()) {
            // Delete unit if it was here before
            if ($hmUnitId) {
                $this->deleteUnit($hmUnitId, $detail);
            }

            // If article had non new status, clean it up
            if (self::STATUS_NEW != $attr->getHmStatus()) {
                $this->updateStatus($detail->getId(), self::STATUS_NEW);
            }

            throw new \Exception('Ean is missing.');
        }

        // If user doesn't want sync
        if (self::STATUS_BLOCKED == $attr->getHmStatus()) {
            if ($hmUnitId) {
                $this->deleteUnit($hmUnitId, $detail);
            }
            return false;
        }

        // If article was not found on Hm before, lets skip it, and then check it again in couple of days
        if (self::STATUS_NOT_FOUND == $attr->getHmStatus() && !$forceNotFound) {
            $last = $attr->getHmLastAccessDate();
            if ($last && strtotime($last) > strtotime('-3 days')) {
                return false;
            }
        }

        // Update last access date
        $this->updateLastAccess($detail->getId());

        // Delete if article is not active
        if (!$detail->getActive() && $hmUnitId) {
            return $this->deleteUnit($hmUnitId, $detail);
        }

        // Look for price
        $price = $this->getPrice($detail);

        // Items in stock
        $inStock = $detail->getInStock();

        switch (true) {
            // Dummy
            case !$hmUnitId && !$inStock:
                $this->updateStatus($detail->getId(), self::STATUS_SYNCHRONIZING);
                break;

            // Create stock
            case !$hmUnitId && $inStock:
                return $this->postUnit($detail, $price);

            // Update stock
            case $hmUnitId && $inStock:
                return $this->updateUnit($hmUnitId, $detail, $price);

            // Delete from stock
            case $hmUnitId && !$inStock:
                return $this->deleteUnit($hmUnitId, $detail);
        }

        return true;
    }

    /**
     * @param Detail $detail
     * @param int $price
     * @return bool
     * @throws \Exception
     */
    private function postUnit(Detail $detail, $price)
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

        try {
            $hmUnitId = $this->apiClient->units()->post($transfer);
        } // Not possible to sell this item (EAN not found)
        catch (ResourceNotFoundException $e) {
            $this->updateStatus($detail->getId(), self::STATUS_NOT_FOUND);
            return false;
        }

        if (!$hmUnitId) {
            throw new \Exception('Error on post article on Hitmeister. Got empty unit id.');
        }

        // Perfect
        $this->connection->update(
            's_articles_attributes',
            array('hm_unit_id' => $hmUnitId, 'hm_status' => self::STATUS_SYNCHRONIZING),
            array('articledetailsID' => (int)$detail->getId())
        );

        return true;
    }

    /**
     * @param string $hmUnitId
     * @param Detail $detail
     * @param int $price
     * @return bool
     */
    private function updateUnit($hmUnitId, Detail $detail, $price)
    {
        $transfer = new UnitUpdateTransfer();
        $transfer->condition = $this->defaultCondition;
        $transfer->listing_price = $price;
        $transfer->minimum_price = $price;
        $transfer->amount = $detail->getInStock();
        $transfer->id_offer = $detail->getNumber();
        $transfer->note = $detail->getAdditionalText();
        $transfer->delivery_time = $this->getDeliveryTimeByDays($detail->getShippingTime());

        $res = $this->apiClient->units()->update($hmUnitId, $transfer);

        // Not found, lets post it again
        if (false === $res) {
            $res = $this->postUnit($detail, $price);
        }

        return $res;
    }

    private function deleteUnit($hmUnitId, Detail $detail)
    {
        $res = $this->apiClient->units()->delete($hmUnitId);

        // Perfect
        $this->connection->update(
            's_articles_attributes',
            array('hm_unit_id' => null),
            array('articledetailsID' => (int)$detail->getId()),
            array('hm_unit_id' => \PDO::PARAM_NULL)
        );

        return $res;
    }

    /**
     * @param int $id
     */
    private function updateLastAccess($id)
    {
        $this->connection->update(
            's_articles_attributes',
            array('hm_last_access_date' => date('Y-m-d H:i:s')),
            array('articledetailsID' => (int)$id)
        );
    }

    /**
     * @param int $id
     * @param string $status
     */
    private function updateStatus($id, $status)
    {
        $this->connection->update(
            's_articles_attributes',
            array('hm_status' => $status),
            array('articledetailsID' => (int)$id)
        );
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
    private function getPrice(Detail $detail)
    {
        $q = sprintf('SELECT `price` FROM `s_articles_prices` WHERE `articledetailsID` = %d AND `from` = 1 AND `pricegroup` = "EK" ORDER BY `price` ASC LIMIT 1', $detail->getId());
        $stmt = $this->connection->executeQuery($q);

        if (!($price = $stmt->fetchColumn(0))) {
            throw new \Exception('There is no price for article details.');
        }

        if ($tax = $detail->getArticle()->getTax()) {
            $price = round((float)$price * (100 + (float)$tax->getTax()) / 100, 2);
        }

        return round($price * 100);
    }
}