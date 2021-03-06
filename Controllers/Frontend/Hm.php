<?php

use Hitmeister\Component\Api\Transfers\Constants;
use Psr\Log\LoggerInterface;
use Shopware\Components\CSRFWhitelistAware;
use ShopwarePlugins\HitmeMarketplace\Components\Exporter;
use ShopwarePlugins\HitmeMarketplace\Components\Ordering;

/**
 * Class Shopware_Controllers_Frontend_Hm
 */
class Shopware_Controllers_Frontend_Hm extends Enlight_Controller_Action implements CSRFWhitelistAware
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
     * @throws Exception
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
            $this->processResponse(400, 'Unexpected mode or empty challenge string');

            return;
        }

        // Reply on challenge
        $this->processResponse(200, $this->Request()->get('challenge'));
    }

    /**
     * @param int $code
     * @param string $body
     */
    private function processResponse($code, $body)
    {
        $this->Response()->setHttpResponseCode($code);
        $this->Response()->setBody($body);
    }

    /**
     * Process notification
     * @throws Exception
     */
    protected function processNotification()
    {
        $resource = $this->Request()->getParam('resource');
        $eventName = $this->Request()->getParam('event_name');

        if (empty($resource) || empty($eventName)) {
            $this->processResponse(400, 'Resource or event name is empty');

            return;
        }

        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');

        switch ($eventName) {
            case Constants::EVENT_NAME_ORDER_NEW:
                if (!preg_match('~/orders/(.*)/~', $resource, $matches)) {
                    $this->processResponse(400, sprintf('Order ID not found in "%s"', $resource));

                    return;
                }
                $resourceId = $matches[1];

                try {
                    $this->getOrderService()->process($resourceId);
                } catch (Exception $e) {
                    $logger->error('Error on `order_new` event processing', ['exception' => $e]);
                    $this->processResponse(500, $e->getMessage());
                }
                break;

            case Constants::EVENT_NAME_ORDER_UNIT_NEW:
                break;

            case Constants::EVENT_NAME_ORDER_UNIT_STATUS_CHANGED:
                if (!preg_match('~/order-units/(.*)/~', $resource, $matches)) {
                    $this->processResponse(400, sprintf('Order unit ID not found in "%s"', $resource));

                    return;
                }
                $resourceId = $matches[1];

                try {
                    $this->getOrderService()->cancelOrderUnit($resourceId);
                } catch (Exception $e) {
                    $logger->error('Error on `order_unit_status_changed` event processing', ['exception' => $e]);
                    $this->processResponse(500, $e->getMessage());
                }

                break;

            default:
                $this->processResponse(400, sprintf('Unsupported event "%s"', $eventName));
                break;
        }
    }

    /**
     * @return Ordering
     */
    public function getOrderService()
    {
        return $this->get('HmOrdering');
    }

    /**
     * Export products
     * @throws Exception
     */
    public function exportAction()
    {
        if (!$this->Request()->getParam('plain')) {
            $this->Response()->setHeader('Content-Type', 'text/csv;charset=utf-8');
        } else {
            $this->Response()->setHeader('Content-Type', 'text/plain;charset=utf-8');
        }

        $id = $this->Request()->getParam('id') ?: 'all';

        // Flush before
        if ($this->Request()->getParam('force')) {
            $this->getExporterService()->flushCache($id);
        }

        $shopId = $this->Request()->getParam('sid') ?: 1;
        $feedFile = $this->getExporterService()->getFeed($id, $shopId);
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
     * Export products
     */
    public function flushCommandAction()
    {
        $this->Response()->setHeader('Content-Type', 'text/csv;charset=utf-8');
        $command = "FLUSH;\n";

        $this->Response()->setHeader('Content-Disposition', 'attachment; filename=flush.csv');
        $this->Response()->sendHeaders();

        echo $command;
    }

    /**
     * Show versions info
     */
    public function versionAction()
    {
        /** @var Shopware_Plugins_Backend_HitmeMarketplace_Bootstrap $plugin */
        $plugin = Shopware()->Plugins()->get('Backend')->get('HitmeMarketplace');
        $this->processResponse(200, json_encode([
            'version_plugin' => $plugin->getVersion(),
            'version_sw' => \Shopware::VERSION,
            'version_php' => PHP_VERSION,
        ]));
    }

    /**
     * Whitelist notify- and webhook-actions
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'notifications',
            'processNotification',
            'export',
            'flushCommand',
            'version'
        ];
    }
}
