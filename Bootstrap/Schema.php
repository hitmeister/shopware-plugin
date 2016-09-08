<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

class Schema
{

    public function __construct()
    {
        $hitmeMarketplace = Shopware()->Plugins()->Backend()->HitmeMarketplace();
        $hitmeMarketplace->registerCustomModels();
    }

    public static function create()
    {

        $em = Shopware()->Models();
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = array(
          $em->getClassMetadata('Shopware\CustomModels\HitmeMarketplace\Stock')
        );

        try {
            $tool->dropSchema($classes);
        } catch (Exception $e) {
            //ignore
        }
        $tool->createSchema($classes);


    }


    public static function drop()
    {

        $em = Shopware()->Models();
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);

        $classes = array(
          $em->getClassMetadata('Shopware\CustomModels\HitmeMarketplace\Stock')
        );

        try {
            $tool->dropSchema($classes);
        } catch (Exception $e) {
            //ignore
        }


    }
}