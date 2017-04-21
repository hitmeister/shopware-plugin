<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\Constants;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Psr\Log\LoggerInterface;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;

/**
 * Class Ordering
 *
 * @package ShopwarePlugins\HitmeMarketplace\Subscriber
 */
class Ordering implements SubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Order\Detail::postUpdate' => 'onDetailsUpdate',
            'Shopware\Models\Order\Order::postUpdate' => 'onOrderUpdate'
        ];
    }
    
    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @throws \Exception
     */
    public function onDetailsUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Detail $detail */
        $detail = $args->get('entity');
        /** @var \Shopware\Models\Attribute\OrderDetail $attribute */
        $attribute = $detail->getAttribute();
 
        if ($attribute === null || !$attribute->getHmOrderUnitId()) {
            return;
        }
        
        // 2 = canceled
        if (2 === $detail->getStatus()->getId() && 'canceled' !== $attribute->getHmStatus()) {
            $this->cancelOrderDetails($detail);
        }
    }
    
    /**
     * @param Detail $detail
     *
     * @throws \Exception
     */
    private function cancelOrderDetails(Detail $detail)
    {
        /** @var Client $api */
        $api = Shopware()->Container()->get('HmApi');
        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');
        
        $attribute = $detail->getAttribute();
        if ($attribute === null) {
            return;
        }
        
        $hmOrderUnitId = $attribute->getHmOrderUnitId();
        try {
            $api->orderUnits()
                ->cancel($hmOrderUnitId, Constants::REASON_GENERAL_ADJUSTMENT);
            
            Shopware()->Db()->executeUpdate(
                'UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?',
                ['canceled', $detail->getId()]
            );
        } catch (\Exception $e) {
            $logger->error(
                'Error on cancelOrderDetails',
                ['HmOrderUnitId' => $hmOrderUnitId, 'exception' => $e->getMessage()]
            );
        }
    }
    
    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @throws \Exception
     */
    public function onOrderUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Order $order */
        $order = $args->get('entity');
        $attribute = $order->getAttribute();
        
        if ($attribute === null || !$attribute->getHmOrderId()) {
            return;
        }
        
        // -1 = canceled, 4 = canceled/rejected
        if (in_array($order->getOrderStatus()->getId(), [-1, 4], true)) {
            foreach ($order->getDetails() as $detail) {
                /** @var Detail $detail */
                if ('canceled' !== $detail->getAttribute()->getHmStatus()) {
                    $this->cancelOrderDetails($detail);
                }
            }
        }
        
        // shipping // 2 = complete, 7 = completely_delivered
        $allowedCarriers = [
            Constants::CARRIER_CODE_OTHER,
            Constants::CARRIER_CODE_DEUTSCHE_POST
        ];
        $shopCarrier = $this->getShopCarrier($order->getShop()->getId());
        $newCarries = ($order->getOrderStatus()->getId() === 7 && in_array($shopCarrier, $allowedCarriers, true));
        
        if ($newCarries || $order->getTrackingCode()) {
            foreach ($order->getDetails() as $detail) {
                /** @var Detail $detail */
                if (!in_array($detail->getAttribute()->getHmStatus(), ['canceled', 'sent'], true)) {
                    $this->sendOrderDetails($detail, $order->getTrackingCode(), $shopCarrier);
                }
            }
        }
    }
    
    /**
     * Get Default Carrier per Shop
     *
     * @param $shopId
     *
     * @return mixed
     */
    private function getShopCarrier($shopId)
    {
        $shopConfig = HmShop::getShopConfigByShopId($shopId);
        
        return $shopConfig->get('defaultCarrier');
    }
    
    /**
     * @param Detail $detail
     * @param        $trackingCode
     * @param        $shopCarrier
     *
     * @throws \Exception
     */
    private function sendOrderDetails(Detail $detail, $trackingCode, $shopCarrier)
    {
        /** @var Client $api */
        $api = Shopware()->Container()->get('HmApi');
        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');
        
        $attribute = $detail->getAttribute();
        if ($attribute === null) {
            return;
        }
        
        $hmOrderUnitId = $attribute->getHmOrderUnitId();
        try {
            $api->orderUnits()
                ->send(
                    $hmOrderUnitId,
                    $shopCarrier,
                    $trackingCode
                );
            
            Shopware()->Db()->executeUpdate(
                'UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?',
                ['sent', $detail->getId()]
            );
        } catch (\Exception $e) {
            $logger->error(
                'Error on sendOrderDetails',
                ['HmOrderUnitId' => $hmOrderUnitId, 'exception' => $e->getMessage()]
            );
        }
    }
}
