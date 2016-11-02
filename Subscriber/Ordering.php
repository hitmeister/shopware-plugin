<?php

namespace ShopwarePlugins\HitmeMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\Constants;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;
use Psr\Log\LoggerInterface;
use Shopware\Models\Shop\Shop;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;

class Ordering implements SubscriberInterface
{

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Shopware\Models\Order\Detail::postUpdate' => 'onDetailsUpdate',
            'Shopware\Models\Order\Order::postUpdate' => 'onOrderUpdate',
        );
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onDetailsUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Detail $detail */
        $detail = $args->get('entity');

        if (!$detail->getAttribute()->getHmOrderUnitId()) {
            return;
        }

        // 2 = canceled
        if (2 == $detail->getStatus()->getId() && 'canceled' != $detail->getAttribute()->getHmStatus()) {
            $this->cancelOrderDetails($detail);
        }
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onOrderUpdate(\Enlight_Event_EventArgs $args)
    {
        /** @var Order $order */
        $order = $args->get('entity');

        if (!$order->getAttribute()->getHmOrderId()) {
            return;
        }

        // -1 = canceled, 4 = canceled/rejected
        if (in_array($order->getOrderStatus()->getId(), array(-1, 4))) {
            foreach ($order->getDetails() as $detail) {
                /** @var Detail $detail */
                if ('canceled' != $detail->getAttribute()->getHmStatus()) {
                    $this->cancelOrderDetails($detail);
                }
            }
        }

        // shipping
        if ($order->getTrackingCode()) {
            foreach ($order->getDetails() as $detail) {
                /** @var Detail $detail */
                if (!in_array($detail->getAttribute()->getHmStatus(), array('canceled', 'sent'))) {
                    $this->sendOrderDetails($detail, $order->getTrackingCode(), $order->getShop());
                }
            }
        }
    }

    /**
     * @param Detail $detail
     * @throws \Exception
     */
    private function cancelOrderDetails(Detail $detail)
    {
        /** @var Client $api */
        $api = Shopware()->Container()->get('HmApi');

        try {
            $api->orderUnits()
                ->cancel($detail->getAttribute()->getHmOrderUnitId(), Constants::REASON_GENERAL_ADJUSTMENT);

            Shopware()->Db()->executeUpdate(
                'UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?',
                array('canceled', $detail->getId())
            );
        } catch (\Exception $e) {}
    }

    /**
     * @param Detail $detail
     * @param $trackingCode
     * @param Shop $shop
     * @throws \Exception
     */
    private function sendOrderDetails(Detail $detail, $trackingCode, Shop $shop)
    {
        /** @var Client $api */
        $api = Shopware()->Container()->get('HmApi');

        try {
            $api->orderUnits()
                ->send($detail->getAttribute()->getHmOrderUnitId(), $this->getShopCarrier($shop->getId()), $trackingCode);

            Shopware()->Db()->executeUpdate(
                'UPDATE s_order_details_attributes SET hm_status = ? WHERE detailID = ?',
                array('sent', $detail->getId())
            );
        } catch (\Exception $e) {}
    }


    /**
     * Get Default Carrier per Shop
     * @return mixed
     */
    private function getShopCarrier($shopId) {

        $shopConfig = HmShop::getShopConfigByShopId($shopId);
        $defaultCarrier = $shopConfig->get('defaultCarrier');

        return $defaultCarrier;
    }
}