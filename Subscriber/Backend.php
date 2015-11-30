<?php

namespace ShopwarePlugins\HmMarketplace\Subscriber;

use Enlight\Event\SubscriberInterface;

class Backend implements SubscriberInterface
{
    /**
     * @var string
     */
    private $bootstrapPath;

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
        return array(
            // Menu icon
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Index' => 'onPostDispatchIndex',
        );
    }

    /**
     * Provides the Hitmeister logo in the backend
     *
     * @param \Enlight_Event_EventArgs $args
     */
    public function onPostDispatchIndex(\Enlight_Event_EventArgs $args)
    {
        /* @var \Enlight_Controller_Action $subject */
        $subject = $args->get('subject');
        $view = $subject->View();

        $view->addTemplateDir($this->bootstrapPath . 'Views/backend/');
        $view->extendsTemplate('hm/menu_entry.tpl');
    }
}