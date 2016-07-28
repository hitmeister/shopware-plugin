<?php

use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\Constants;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;

class Shopware_Controllers_Backend_HmNotifications extends Shopware_Controllers_Backend_ExtJs
{
    public function getListAction()
    {
        $limit = $this->Request()->getParam('limit', 100);
        $offset = ($this->Request()->getParam('page', 1) - 1) * $limit;

        try {
            $cursor = $this->getApiClient()
                ->subscriptions()
                ->find(null, $limit, $offset);

            $this->View()->assign(array(
                'success' => true, 'total' => $cursor->total(), 'data' => iterator_to_array($cursor),
            ));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function changeStatusByIdAction()
    {
        $notificationId = $this->Request()->getParam('id', null);
        if (empty($notificationId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No notification id passed!'));
        }

        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id is passed!'));
        }

        $status = $this->Request()->getParam('status', 0);

        try {
            $res = $status ? $this->enableById($notificationId) : $this->disableById($notificationId);
            $this->View()->assign(array('success' => $res));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function enableAllAction()
    {
        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id is passed!'));
        }

        try {
            $cursor = $this->getApiClient()
                ->subscriptions()
                ->find();

            $exclude = array();
            foreach ($cursor as $subscription) {
                if (!$subscription->is_active) {
                    $this->enableById($subscription->id_subscription);
                }

                $exclude[] = $subscription->event_name;
            }

            $this->subscribeToAll($exclude, $shopId);

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function disableAllAction()
    {
        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            return $this->View()->assign(array('success' => false, 'message' => 'No shop id is passed!'));
        }

        try {
            $cursor = $this->getApiClient()
                ->subscriptions()
                ->find();

            foreach ($cursor as $subscription) {
                if ($subscription->is_active) {
                    $this->disableById($subscription->id_subscription);
                }
            }

            $this->View()->assign(array('success' => true));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    /**
     * @param array $excludeNames
     * @param int $shopId
     * @return bool
     */
    private function subscribeToAll($excludeNames = array(), $shopId)
    {
        $listEvents = array(
            Constants::EVENT_NAME_ORDER_NEW,
            Constants::EVENT_NAME_ORDER_UNIT_NEW,
            Constants::EVENT_NAME_ORDER_UNIT_STATUS_CHANGED,
        );

        $shopUrl = HmShop::getShopUrl($shopId);
        $callback = $shopUrl . "Hm/notifications";

        /** @var \Shopware_Components_Config $config */
        $config = $this->get('config');
        $email = $config->getByNamespace('MasterData', 'mail');

        foreach ($listEvents as $eventName) {
            if (in_array($eventName, $excludeNames)) {
                continue;
            }
            $this->getApiClient()->subscriptions()->post($eventName, $callback, $email);
        }
    }

    /**
     * @param int $hmId
     * @return bool
     */
    private function enableById($hmId)
    {
        return $this->getApiClient()->subscriptions()->update($hmId, null, null, null, true);
    }

    /**
     * @param int $hmId
     * @return bool
     */
    private function disableById($hmId)
    {
        return $this->getApiClient()->subscriptions()->update($hmId, null, null, null, false);
    }

    /**
     * @return Client
     */
    private function getApiClient()
    {
        return $this->get('HmApi');
    }
}