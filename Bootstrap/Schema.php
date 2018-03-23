<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use Psr\Log\LoggerInterface;
use Shopware\CustomModels\HitmeMarketplace\Stock;

/**
 * Class Schema
 * @package ShopwarePlugins\HitmeMarketplace\Bootstrap
 */
class Schema
{
    /**
     * Schema constructor.
     */
    public function __construct()
    {
        $hitmeMarketplace = Shopware()->Plugins()->Backend()->HitmeMarketplace();
        $hitmeMarketplace->registerCustomModels();
    }
    
    public static function create()
    {
        
        $em = Shopware()->Models();
        $tool = new SchemaTool($em);
        
        $classes = [
            $em->getClassMetadata(Stock::class)
        ];
        
        try {
            $tool->dropSchema($classes);
        } catch (Exception $e) {
            //ignore
        }
        $tool->createSchema($classes);
    }

    public static function drop()
    {
        /** @var LoggerInterface $logger */
        $logger = Shopware()->Container()->get('pluginlogger');
        
        $em = Shopware()->Models();
        $tool = new SchemaTool($em);
        
        $classes = [
            $em->getClassMetadata(Stock::class)
        ];
        
        try {
            $tool->dropSchema($classes);
        } catch (Exception $e) {
            $logger->error('Error on `dropSchema` with param:'.$classes.'', ['exception' => $e]);
        }
    }
}
