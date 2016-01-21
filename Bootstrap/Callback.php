<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

use GuzzleHttp\Ring\Client\CurlMultiHandler;
use GuzzleHttp\Ring\Future\FutureArrayInterface;

class Callback
{
    /**
     * @param string $version
     */
    public static function install($version)
    {
        if ('production' != Shopware()->Environment()) {
            return;
        }
        static::call(static::collectData('install', $version));
    }

    /**
     * @param string $currentVersion
     * @param string $previousVersion
     */
    public static function update($currentVersion, $previousVersion)
    {
        if ('production' != Shopware()->Environment()) {
            return;
        }
        static::call(static::collectData('update', $currentVersion, $previousVersion));
    }

    /**
     * @param string $version
     */
    public static function uninstall($version)
    {
        if ('production' != Shopware()->Environment()) {
            return;
        }
        static::call(static::collectData('uninstall', $version));
    }

    /**
     * @param array $data
     */
    private static function call(array $data)
    {
        $request = array(
            'http_method' => 'POST',
            'scheme' => 'https',
            'uri' => '/notifications/shopware/',
            'headers' => array(
                'Host' => array('www.hitmeister.de'),
            ),
            'body' => json_encode($data),
            'client' => array(
                'connect_timeout' => 5,
                'timeout' => 5,
                'verify' => false,
            ),
        );

        try {
            $handler = new CurlMultiHandler();
            $result = $handler($request);

            while ($result instanceof FutureArrayInterface) {
                $result = $result->wait();
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $action
     * @param string $currentVersion
     * @param string $previousVersion
     * @return array
     */
    private static function collectData($action, $currentVersion, $previousVersion = null)
    {
        return array(
            'action' => $action,
            'version_current' => $currentVersion,
            'version_previous' => $previousVersion ?: $currentVersion,
            'version_sw' => \Shopware::VERSION,
            'version_php' => PHP_VERSION,
            'url' => Shopware()->Front()->Router()->assemble(),
        );
    }
}