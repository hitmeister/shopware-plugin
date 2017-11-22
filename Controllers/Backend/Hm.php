<?php

use Hitmeister\Component\Api\Client;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;
use Shopware\Components\CSRFWhitelistAware;

/**
 * Class Shopware_Controllers_Backend_Hm
 */
class Shopware_Controllers_Backend_Hm extends Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware
{
    public function checkConfigAction()
    {
        try {
            $shops = Shop::getActiveShops();
            $message = false;
            if (count($shops)) {
                foreach ($shops as $shop) {
                    $this->resetApiClient();
                    $this->Request()->setParam('shopId', $shop['id']);
                    $shopConfig = $shop['hm_config'];
                    $defaultShippingGroup = $shopConfig->get('defaultshippinggroup');
                    if ($this->getApiClient() !== false) {
                        $result = $this->getApiClient()->status()->ping();
                        if (empty($result->message) ||
                            empty($defaultShippingGroup)
                        ) {
                            $message = false;
                            break;
                        } else {
                            $message = true;
                        }
                    } else {
                        $message = false;
                        break;
                    }
                }
            }
            $this->View()->assign(['success' => $message]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    public function getActiveShopsAction()
    {
        $shops = Shop::getActiveShops();
        $setDefaultShop = $this->Request()->getParam('setDefaultShop', 0);
        if (count($shops)) {
            if ($setDefaultShop == 1) {
                /** @var $namespace Enlight_Components_Snippet_Namespace */
                $namespace = Shopware()->Snippets()->getNamespace('backend/hm/view/stock');
                $defaultShop = [[
                    'id' => 0,
                    'name' => $namespace->get('hm/stock/grid/toolbar/combo/filter_shop_all'),
                    'category_id' => 0
                
                ]];
                $shops = array_merge($defaultShop, $shops);
            }
            $shops = array_values($shops);
            $this->View()->assign([
                'success' => true,
                'data' => array_map(function ($item) {
                    return ['id' => $item['id'], 'name' => $item['name'], 'category_id' => $item['category_id']];
                }, $shops)
            ]);
        } else {
            $this->View()->assign([
                'success' => false,
                'data' => []
            ]);
        }
    }
    
    public function getShippingGroupsAction()
    {
        $shopId = $this->Request()->getParam('shopId');
        if (empty($shopId)) {
            $fieldName = $this->Request()->getParam('field_name');
            if (empty($fieldName)) {
                return $this->View()->assign(['success' => false, 'message' => 'Wrong config parameter!']);
            }
            preg_match("/values\[(\d*)\]\[.*\]/", $fieldName, $fieldMatch);
            $shopId = (int)$fieldMatch[1];
            
            if (empty($shopId)) {
                return $this->View()->assign(['success' => false, 'message' => 'No shop id is passed!']);
            }
        }
        
        $this->Request()->setParam('shopId', $shopId);
        
        try {
            $cursor = $this->getApiClient()
                ->shippingGroups()
                ->find();
            
            $this->View()->assign(['success' => true, 'data' => iterator_to_array($cursor)]);
        } catch (Exception $e) {
            $this->View()->assign(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * @return Client
     */
    private function getApiClient()
    {
        return $this->get('HmApi');
    }
    
    private function resetApiClient()
    {
        Shopware()->Container()->reset('HmApi');
    }
    
    /**
     * Whitelist notify- and webhook-actions
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'checkConfig',
            'getActiveShops',
            'getShippingGroups'
        ];
    }
}
