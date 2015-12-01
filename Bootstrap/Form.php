<?php

namespace ShopwarePlugins\HmMarketplace\Bootstrap;

use Hitmeister\Component\Api\Transfers\Constants;
use Shopware\Models\Config\Element;
use Shopware\Models\Config\ElementTranslation;
use Shopware\Models\Config\Form as ConfigForm;
use Shopware\Models\Config\FormTranslation;
use Shopware\Models\Shop\Locale;

class Form
{
    /**
     * @var ConfigForm $form
     */
    private $form;

    /**
     * @param ConfigForm $form
     */
    public function __construct(ConfigForm $form)
    {
        $this->form = $form;
    }

    /**
     *
     */
    public function create()
    {
        $this->form->setDescription('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt. Sed nec pretium massa, et pharetra purus. Nunc rhoncus porta est sit amet accumsan. Cras quam metus, interdum vel ornare at, cursus ut risus. Etiam neque neque, dictum vel elit vitae, sagittis imperdiet purus. Suspendisse nec risus eget ante facilisis commodo. Etiam consectetur luctus rutrum. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ultricies elit lacus, non vestibulum felis ullamcorper id. Quisque mi dolor, mollis sit amet blandit vel, eleifend at ante.');

        $this->form->setElement('button', 'openForm', array(
            'label' => 'New customer?',
            'handler' => 'function () { window.open("https://www.hitmeister.de/versandpartner/online-marktplatz/"); }',
        ));

        // Api settings
        $this->form->setElement('text', 'clientKey', array(
            'label' => 'API: Client key',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'value' => '51ffffffffffffffffffffffffffffff',
            'required' => true,
        ));

        $this->form->setElement('text', 'secretKey', array(
            'label' => 'API: Secret key',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'value' => '0000000000000000000000000000000000000000000000000000000000000000',
            'required' => true,
        ));

        $this->form->setElement('text', 'apiUrl', array(
            'label' => 'API: URL',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            //'value' => 'https://www.hitmeister.de/api/v1/',
            'value' => 'https://www.maksim-naumov.hitmeister.dev/api/v1/',
            'required' => true,
        ));

        // Stock management
        $this->form->setElement('select', 'defaultDelivery', array(
            'label' => 'Stock: Default delivery time',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'value' => Constants::DELIVERY_TIME_H,
            'required' => true,
            'store' => array(
                array(Constants::DELIVERY_TIME_H, 'When it\'s available or no shipping estimate possible'),
                array(Constants::DELIVERY_TIME_A, 'Ships within 24 hours'),
                array(Constants::DELIVERY_TIME_B, 'Ships in 1-3 days'),
                array(Constants::DELIVERY_TIME_C, 'Ships in 4-6 days'),
                array(Constants::DELIVERY_TIME_D, 'Ships in 7-10 days'),
                array(Constants::DELIVERY_TIME_E, 'Ships in 11-14 days'),
                array(Constants::DELIVERY_TIME_F, 'Ships in 3-4 weeks'),
                array(Constants::DELIVERY_TIME_G, 'Ships in 5-7 weeks'),
                array(Constants::DELIVERY_TIME_I, 'Ships in 8-10 weeks'),
            ),
        ));

        $this->form->setElement('select', 'defaultCondition', array(
            'label' => 'Stock: Default article condition',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'value' => Constants::CONDITION_NEW,
            'required' => true,
            'store' => array(
                array(Constants::CONDITION_NEW, Constants::CONDITION_NEW),
                array(Constants::CONDITION_USED_AS_NEW, Constants::CONDITION_USED_AS_NEW),
                array(Constants::CONDITION_USED_VERY_GOOD, Constants::CONDITION_USED_VERY_GOOD),
                array(Constants::CONDITION_USED_GOOD, Constants::CONDITION_USED_GOOD),
                array(Constants::CONDITION_USED_ACCEPTABLE, Constants::CONDITION_USED_ACCEPTABLE),
            ),
        ));

        // Order
        $deliveryMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_premium_dispatch', array(), \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultDeliveryMethod', array(
            'label' => 'Orders: Default delivery method',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'required' => true,
            'value' => !empty($deliveryMethods) ? $deliveryMethods[0][0] : '',
            'store' => $deliveryMethods,
        ));

        $paymentMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_core_paymentmeans', array(), \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultPaymentMethod', array(
            'label' => 'Orders: Default payment method',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'required' => true,
            'value' => !empty($paymentMethods) ? $paymentMethods[0][0] : '',
            'store' => $paymentMethods,
        ));

        $shops = Shopware()->Db()->fetchAll('SELECT id, name FROM s_core_shops', array(), \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultShop', array(
            'label' => 'Orders: Default shop',
            'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt.',
            'required' => true,
            'value' => !empty($shops) ? $shops[0][0] : '',
            'store' => $shops,
        ));
    }

    /**
     * @param array $translations
     */
    public function translateForm(array $translations)
    {
        $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');

        foreach ($translations as $locale => $snippets) {
            /** @var Locale $localeModel */
            if (null === ($localeModel = $shopRepository->findOneBy(array('locale' => $locale)))) {
                continue;
            }

            // Translation for form description
            if ($snippets['plugin_form']) {
                $formTranslation = null;

                /* @var FormTranslation $translation */
                foreach ($this->form->getTranslations() as $translation) {
                    if ($translation->getLocale()->getLocale() == $locale) {
                        $formTranslation = $translation;
                    }
                }

                if (!$formTranslation) {
                    $formTranslation = new FormTranslation();
                    $formTranslation->setLocale($localeModel);
                    $this->form->addTranslation($formTranslation);
                }

                if (!empty($snippets['plugin_form']['label'])) {
                    $formTranslation->setLabel($snippets['plugin_form']['label']);
                }
                if (!empty($snippets['plugin_form']['description'])) {
                    $formTranslation->setDescription($snippets['plugin_form']['description']);
                }

                unset($snippets['plugin_form']);
            }

            foreach ($snippets as $element => $snippet) {
                if (null === ($elementModel = $this->form->getElement($element))) {
                    continue;
                }

                $translationModel = null;

                foreach ($elementModel->getTranslations() as $translation) {
                    if ($translation->getLocale()->getLocale() == $locale) {
                        $translationModel = $translation;
                        break;
                    }
                }

                if (!$translationModel) {
                    $translationModel = new ElementTranslation();
                    $translationModel->setLocale($localeModel);
                    $elementModel->addTranslation($translationModel);
                }

                if (!empty($snippet['label'])) {
                    $translationModel->setLabel($snippet['label']);
                }
                if (!empty($snippet['description'])) {
                    $translationModel->setDescription($snippet['description']);
                }
            }
        }
    }
}