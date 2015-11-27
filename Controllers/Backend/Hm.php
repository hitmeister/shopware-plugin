<?php

use Hitmeister\Component\Api\Client;

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

    /**
     * @return Client
     */
    private function getApiClient()
    {
        return $this->get('HmApi');
    }
}