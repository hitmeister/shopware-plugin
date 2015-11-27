<?php

namespace ShopwarePlugins\HmMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;

class ControllerPath implements SubscriberInterface
{
    /**
     * @var string
     */
    private $bootstrapPath;

    /**
     * @var string
     */
    private $regexp = '/Enlight_Controller_Dispatcher_ControllerPath_(Frontend|Backend)_Hm(.*)/';

    /**
     * @param string $bootstrapPath
     */
    public function __construct($bootstrapPath)
    {
        $this->bootstrapPath = $bootstrapPath;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array_fill_keys(
            array(
                'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Hm',

                'Enlight_Controller_Dispatcher_ControllerPath_Backend_Hm',
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_HmArticles',
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_HmCategories',
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_HmExports',
                'Enlight_Controller_Dispatcher_ControllerPath_Backend_HmNotifications',
            ),
            'onGetControllerPath');
    }

    public function onGetControllerPath(\Enlight_Event_EventArgs $arguments)
    {
        Shopware()->Template()->addTemplateDir($this->bootstrapPath . 'Views');

        if (preg_match($this->regexp, $arguments->getName(), $m)) {
            $path = sprintf('%sControllers/%s/Hm%s.php', $this->bootstrapPath, $m[1], $m[2]);
            return $path;
        }
    }
}