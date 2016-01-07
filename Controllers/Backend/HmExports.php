<?php

use Hitmeister\Component\Api\Client;
use Hitmeister\Component\Api\Transfers\Constants;
use ShopwarePlugins\HitmeMarketplace\Components\Exporter;

class Shopware_Controllers_Backend_HmExports extends Shopware_Controllers_Backend_ExtJs
{
    public function getListAction()
    {
        $limit = $this->Request()->getParam('limit', 100);
        $offset = ($this->Request()->getParam('page', 1) - 1) * $limit;

        try {
            $cursor = $this->getApiClient()
                ->importFiles()
                ->find(null, Constants::TYPE_PRODUCT_FEED, null, null, 'ts_created:desc', $limit, $offset);

            $this->View()->assign(array(
                'success' => true,
                'total' => $cursor->total(),
                'data' => iterator_to_array($cursor),
            ));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'data' => array(), 'total' => 0, 'message' => $e->getMessage()));
        }
    }

    public function exportAction()
    {
        $callback = $this->Front()->Router()
            ->assemble(array(
                'module' => 'frontend',
                'controller' => 'hm',
                'action' => 'export',
                'id' => date('YmdHis'),
            ));

        try {
            $id = $this->getApiClient()->importFiles()->post($callback, Constants::TYPE_PRODUCT_FEED);
            $res = !empty($id);

            $this->View()->assign(array('success' => $res));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    /**
     * @return Client
     */
    private function getApiClient()
    {
        return $this->get('HmApi');
    }
}