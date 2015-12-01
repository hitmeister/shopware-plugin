<?php

use ShopwarePlugins\HmMarketplace\Bootstrap\Attributes;
use ShopwarePlugins\HmMarketplace\Bootstrap\Form;
use ShopwarePlugins\HmMarketplace\Subscriber\Backend;
use ShopwarePlugins\HmMarketplace\Subscriber\ControllerPath;
use ShopwarePlugins\HmMarketplace\Subscriber\Resources;
use ShopwarePlugins\HmMarketplace\Subscriber\Stock;

class Shopware_Plugins_Backend_HmMarketplace_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
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
    public function install()
    {
        $info = $this->getPluginJson();

        if (!$this->assertMinimumVersion($info['compatibility']['minimumVersion'])) {
            throw new Exception(sprintf('This plugin requires Shopware %s or a later version', $info['compatibility']['minimumVersion']));
        }

        Attributes::create();

        $this->createEvents();
        $this->createConfiguration();
        $this->createMenuEntry();

        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall()
    {
        return array('success' => true, 'invalidateCache' => array('backend', 'proxy'));
    }

    /**
     * {@inheritDoc}
     */
    public function afterInit()
    {
        $this->Application()->Loader()->registerNamespace('ShopwarePlugins\HmMarketplace', $this->Path());
        $this->Application()->Loader()->registerNamespace('Hitmeister\Component\Api', $this->Path() . 'Lib/Api/');
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

        $subscribers = array(
            new ControllerPath($path),
            new Resources($this->Config()),
            new Stock()
        );

        /** @var $subject \Enlight_Controller_Action */
        $subject = $args->get('subject');
        $request = $subject->Request();

        if ($request->getModuleName() == 'backend') {
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
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt. Sed nec pretium massa, et pharetra purus. Nunc rhoncus porta est sit amet accumsan. Cras quam metus, interdum vel ornare at, cursus ut risus. Etiam neque neque, dictum vel elit vitae, sagittis imperdiet purus. Suspendisse nec risus eget ante facilisis commodo. Etiam consectetur luctus rutrum. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ultricies elit lacus, non vestibulum felis ullamcorper id. Quisque mi dolor, mollis sit amet blandit vel, eleifend at ante.',
                ),
                'openForm' => array(
                    'label' => 'New customer?',
                ),
                'clientKey' => array(
                    'label' => 'API: Client key',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'secretKey' => array(
                    'label' => 'API: Secret key',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'apiUrl' => array(
                    'label' => 'API: URL',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'defaultDelivery' => array(
                    'label' => 'Stock: Default delivery time',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'defaultCondition' => array(
                    'label' => 'Stock: Default article condition',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'defaultDeliveryMethod' => array(
                    'label' => 'Orders: Default delivery method',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'defaultPaymentMethod' => array(
                    'label' => 'Orders: Default payment method',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
                ),
                'defaultShop' => array(
                    'label' => 'Orders: Default shop',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
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
            'class' => 'hitmeister-icon',
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