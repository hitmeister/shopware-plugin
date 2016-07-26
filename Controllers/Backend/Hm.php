<?php

use Hitmeister\Component\Api\Client;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;

class Shopware_Controllers_Backend_Hm extends Shopware_Controllers_Backend_ExtJs
{
    public function checkConfigAction()
    {
        try {
            $result = $this->getApiClient()->status()->ping();
            $this->View()->assign(array('success' => !empty($result->message)));
        } catch (Exception $e) {
            $this->View()->assign(array('success' => false, 'message' => $e->getMessage()));
        }
    }

    public function getActiveShopsAction()
    {
        $shops = Shop::getActiveShops();
        if(count($shops)){
            /** @var $namespace Enlight_Components_Snippet_Namespace */
            $namespace = Shopware()->Snippets()->getNamespace('backend/hm/view/stock');
            $defaultShop = array(array(
              'id'    => 0,
              'name'  => $namespace->get('hm/stock/grid/toolbar/combo/filter_shop_all')

            ));
            $shops = array_merge($defaultShop, $shops);
            $this->View()->assign(array(
              'success' => true,
              'data' => array_map(function ($item) {return array('id' => $item['id'], 'name' => $item['name']);}, $shops)
            ));
        }else{
            $this->View()->assign(array(
              'success' => false,
              'data' => array()
            ));
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