<?php

use Hitmeister\Component\Api\Transfers\Constants;
use ShopwarePlugins\HmMarketplace\Components\Exporter;
use ShopwarePlugins\HmMarketplace\Components\Ordering;

class Shopware_Controllers_Frontend_Hm extends Enlight_Controller_Action
{
    /**
     * {@inheritDoc}
     */
    public function init()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        $this->Front()->Plugins()->JsonRequest()->setParseInput();
        $this->Front()->setParam('disableOutputBuffering', true);
    }

    /**
     * Process event notifications
     */
    public function notificationsAction()
    {
        if ($this->Request()->isGet()) {
            $this->confirmSubscription();
        } else {
            $this->processNotification();
        }
    }

    /**
     * Notification subscription confirmation
     */
    protected function confirmSubscription()
    {
        if ('subscribe' !== $this->Request()->get('mode') || !$this->Request()->has('challenge')) {
            $this->Response()->setHttpResponseCode(400);
            return;
        }

        // Reply on challenge
        $this->Response()->setBody($this->Request()->get('challenge'));
    }

    /**
     * Process notification
     */
    protected function processNotification()
    {
        //$messageId = $this->Request()->getParam('id_message');
        $resource = $this->Request()->getParam('resource');
        $eventName = $this->Request()->getParam('event_name');

        switch($eventName) {
            case Constants::EVENT_NAME_ORDER_NEW:
                if (!preg_match('~/orders/(.*)/~', $resource, $matches)) {
                    $this->Response()->setHttpResponseCode(400);
                    return;
                }
                $resourceId = $matches[1];

                try {
                    $this->getOrderService()->process($resourceId);
                } catch(Exception $e) {
                    $this->Response()->setHttpResponseCode(500);
                }
                break;

            case Constants::EVENT_NAME_ORDER_UNIT_NEW:
                break;

            case Constants::EVENT_NAME_ORDER_UNIT_STATUS_CHANGED:
                break;
        }
    }

    public function exportAction()
    {
        if (!$this->Request()->getParam('plain')) {
            $this->Response()->setHeader('Content-Type', 'text/csv;charset=utf-8');
        } else {
            $this->Response()->setHeader('Content-Type', 'text/plain;charset=utf-8');
        }

        $id = $this->Request()->getParam('id');
        if (!$id) {
            $id = 'all';
        }

        // Flush before
        if ($this->Request()->getParam('force')) {
            $this->getExporterService()->flushCache($id);
        }

        $feedFile = $this->getExporterService()->getFeed($id);
        if (!$feedFile) {
            $this->Response()->clearHeaders()->setHttpResponseCode(204);
            return;
        }

        $this->Response()->setHeader('Content-Disposition', sprintf('attachment; filename="%s"', basename($feedFile)));

        $this->Response()->sendHeaders();
        readfile($feedFile);
    }

    /**
     * @return Exporter
     */
    private function getExporterService()
    {
        return $this->get('HmExporter');
    }

    /**
     * @return Ordering
     */
    public function getOrderService()
    {
        return $this->get('HmOrdering');
    }
}