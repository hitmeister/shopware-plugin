<?php

use ShopwarePlugins\HitmeMarketplace\Bootstrap\Attributes;
use ShopwarePlugins\HitmeMarketplace\Bootstrap\Callback;
use ShopwarePlugins\HitmeMarketplace\Bootstrap\Form;
use ShopwarePlugins\HitmeMarketplace\Bootstrap\Schema;
use ShopwarePlugins\HitmeMarketplace\Subscriber\Backend;
use ShopwarePlugins\HitmeMarketplace\Subscriber\ControllerPath;
use ShopwarePlugins\HitmeMarketplace\Subscriber\Ordering;
use ShopwarePlugins\HitmeMarketplace\Subscriber\Resources;
use ShopwarePlugins\HitmeMarketplace\Subscriber\Stock;
use ShopwarePlugins\HitmeMarketplace\Components\Shop;

class Shopware_Plugins_Backend_HitmeMarketplace_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    /**
     * {@inheritDoc}
     */
    public function getCapabilities()
    {
        return array(
          'install' => true,
          'enable' => true,
          'update' => true,
          'secureUninstall' => true
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion()
    {
        $info = $this->getPluginJson();
        return $info['currentVersion'];
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel()
    {
        $info = $this->getPluginJson();
        return $info['label']['de'];
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo()
    {
        $info = $this->getPluginJson();
        return array_merge(
            parent::getInfo(), array(
                'author' => $info['author'],
                'copyright' => $info['copyright'],
                'license' => $info['license'],
                'support' => $info['support'],
                'link' => $info['link'],
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function enable()
    {
        $shops = Shopware()->Models()->getRepository(
          'Shopware\Models\Shop\Shop'
        )->getActiveShops();

        $result = false;
        foreach($shops as $shop){
            $shopConfig = Shop::getShopConfigByShopId($shop->getId());
            $clientKey = $shopConfig->get('clientKey');
            $secretKey = $shopConfig->get('secretKey');
            if(!empty($clientKey) && !empty($secretKey)){
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function install()
    {
        $info = $this->getPluginJson();

        if (!$this->assertMinimumVersion($info['compatibility']['minimumVersion'])) {
            throw new Exception(sprintf('This plugin requires Shopware %s or a later version', $info['compatibility']['minimumVersion']));
        }

        Schema::create();
        Attributes::create();

        $this->createEvents();
        $this->createConfiguration();
        $this->createMenuEntry();

        Callback::install($this->getVersion());

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * {@inheritDoc}
     */
    public function update($version)
    {
        try{
            if ($version == '1.0.0') {
                Attributes::fixDev2210();
            }

            if($this->getVersion() == '2.0.0'){
                Schema::create();
                Attributes::copyAttributesInSchemaV200();
            }

            Callback::update($this->getVersion(), $version);

            return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
        }catch (Exception $e){
            return array('success' => false, 'message' => $e->getMessage());
        }

    }

    /**
     * {@inheritDoc}
     */
    public function uninstall()
    {
        $this->secureUninstall();
        Callback::uninstall($this->getVersion());
        Schema::drop();

        // delete Menu Snippet
        Shopware()->Db()->exec('DELETE FROM s_core_snippets WHERE `name` = "Hm" AND `namespace` = "backend/index/view/main";');

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * {@inheritDoc}
     */
    public function afterInit()
    {
        $this->Application()->Loader()->registerNamespace('ShopwarePlugins\HitmeMarketplace', $this->Path());
        $this->registerCustomModels();

        // API SDK
        $sdkPath = ('production' != Shopware()->Environment()) ? 'vendor/hitmeister/api-sdk/src/' : 'Lib/Api/';
        $this->Application()->Loader()->registerNamespace('Hitmeister\Component\Api', $this->Path() . $sdkPath);
    }

    /**
     * Will register the DispatchLoopStartup event
     */
    private function createEvents()
    {
        $this->subscribeEvent('Enlight_Controller_Front_DispatchLoopStartup', 'onStartDispatch');
    }

    /**
     * This callback function is triggered at the very beginning of the dispatch process and allows
     * us to register additional events on the fly.
     *
     * @param Enlight_Event_EventArgs $args
     */
    public function onStartDispatch(Enlight_Event_EventArgs $args)
    {
        $path = $this->Path();

        $shop = $args->getShop();
        $config = $this->collection->getConfig($this->name, $shop);

        $subscribers = array(
            new ControllerPath($path),
            new Resources($config),
            new Stock(),
            new Ordering(),
        );

        /** @var $subject \Enlight_Controller_Action */
        $subject = $args->get('subject');
        $request = $subject->Request();

        if ($request->getModuleName() === 'backend') {
            $subscribers[] = new Backend($path);
        }

        foreach ($subscribers as $subscriber) {
            $this->Application()->Events()->addSubscriber($subscriber);
        }
    }

    /**
     * Creates configuration form
     */
    private function createConfiguration()
    {
        $form = new Form($this->Form());
        $form->create();

        $translations = array(
            'en_GB' => array(
                'plugin_form' => array(
                    'description' => '<p>real.de ist eines der größten deutschen Online-Shopping-Portale mitten im Herzen von Köln. 100% sicheres, einfaches, günstiges und persönliches Einkaufs- und Verkaufserlebnis. Die Zahlungsabwicklung und auch sämtliche Marketingmaßnahmen werden von real übernommen. Angebote werden anhand der EAN eingestellt, die Abrechnung erfolgt anhand eines einfachen Gebührenmodells. Bei Fragen steht Ihnen die Händlerbetreuung telefonisch unter <b>+49-221-975979-79</b> oder per E-Mail an <b>partnermanagement@hitmeister.de</b> gerne zur Verfügung.</p><p>Um zu starten, bitten wir Sie die unten abgefragten Informationen zu hinterlegen, damit die Abwicklung zwischen Ihrem System und Hitmeister reibungslos funktioniert.  Einige der Informationen finden Sie in Ihrem real-Versandpartner Account unter Shopeinstellungen, daher bitten wir Sie, sich parallel in Ihrem real-Account einzuloggen.</p>',
                ),
                'openForm' => array(
                    'label' => 'New customer?',
                ),
                'clientKey' => array(
                    'label' => 'API: Client key',
                    'description' => 'Diese Information finden Sie im Hitmeister Account unter Shopseinstellungen; API.',
                ),
                'secretKey' => array(
                    'label' => 'API: Secret key',
                    'description' => 'Diese Information finden Sie im Hitmeister-Account unter Shopseinstellungen; API.',
                ),
                'apiUrl' => array(
                    'label' => 'API: URL',
                    'description' => 'Welche API Version nutzen Sie',
                ),
                'defaultDelivery' => array(
                    'label' => 'Stock: Default delivery time',
                    'description' => 'Sollten Sie bei Artikeln keine Lieferzeit hinterlegt haben, dann wird diese hier eingetragene Lieferzeit automatisch hinterlegt.',
                ),
                'defaultCondition' => array(
                    'label' => 'Stock: Default article condition',
                    'description' => 'Bitte legen Sie den globalen Artikelzustand fest. Diese Einstellung wird für alle auf Hitmeister angebotenen Artikel übernommen.',
                ),
                'defaultDeliveryMethod' => array(
                    'label' => 'Orders: Default delivery method',
                    'description' => 'Bitte verknüpfen Sie Ihre Shopware Versandart.',
                ),
                'defaultPaymentMethod' => array(
                    'label' => 'Orders: Default payment method',
                    'description' => 'Bitte wählen Sie eine Bezahlmethode, die Ihrem System mitteilt, dass der Kauf bereits bezahlt ist, da Hitmeister die Zahlungsabwicklung für Sie übernimmt.',
                ),
                'defaultCarrier' => array(
                    'label' => 'Shipping: Default carrier',
                    'description' => 'Welchen Versanddienstleister nutzen Sie?',
                ),
            )
        );

        $form->translateForm($translations);
    }

    /**
     * Creates the menu entry in the backend
     */
    private function createMenuEntry()
    {
        $this->createMenuItem(array(
            'label' => $this->getMenuLabel(),
            'controller' => 'Hm',
            'action' => 'Index',
            'active' => 1,
            'class' => 'real-icon',
            'parent' => $this->Menu()->findOneBy('label', 'Marketing'),
        ));
    }

    /**
     * @return string
     */
    private function getMenuLabel()
    {
        $info = $this->getPluginJson();
        return $info['menu_label'];
    }

    /**
     * @return array
     */
    private function getPluginJson()
    {
        static $pluginInfo;
        if (null === $pluginInfo) {
            $pluginInfo = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'plugin.json'), true);
        }
        return $pluginInfo;
    }
}