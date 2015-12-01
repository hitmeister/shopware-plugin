<?php

namespace ShopwarePlugins\HmMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;
use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\Constants;
use Shopware\Models\Order\Detail;
use Shopware\Models\Order\Order;

class Ordering implements SubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Shopware\Models\Order\Detail::postUpdate' => 'onDetailsStatusUpdate',
            'Shopware\Models\Order\Detail::preRemove' => 'onDetailsStatusUpdate',
            'Shopware\Models\Order\Order::postUpdate' => 'onStatusUpdate',
        );
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onDetailsStatusUpdate(\Enlight_Event_EventArgs $args)
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
    public function onStatusUpdate(\Enlight_Event_EventArgs $args)
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
}