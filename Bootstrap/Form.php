<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

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
        $this->form->setDescription('<p>Hitmeister ist eines der größten deutschen Online-Shopping-Portale mitten im Herzen von Köln. 100% sicheres, einfaches, günstiges und persönliches Einkaufs- und Verkaufserlebnis. Die Zahlungsabwicklung und auch sämtliche Marketingmaßnahmen werden von Hitmeister übernommen. Angebote werden anhand der EAN eingestellt, die Abrechnung erfolgt anhand eines einfachen Gebührenmodells. Bei Fragen steht Ihnen die Händlerbetreuung telefonisch unter <b>+49-221-975979-79</b> oder per E-Mail an <b>partnermanagement@hitmeister.de</b> gerne zur Verfügung.</p><p>Um zu starten, bitten wir Sie die unten abgefragten Informationen zu hinterlegen, damit die Abwicklung zwischen Ihrem System und Hitmeister reibungslos funktioniert.  Einige der Informationen finden Sie in Ihrem Hitmeister-Versandpartner Account unter Shopeinstellungen, daher bitten wir Sie, sich parallel in Ihrem Hitmeister-Account einzuloggen.</p>');

        $this->form->setElement('button', 'openForm', array(
            'label' => 'Jetzt registrieren!',
            'handler' => 'function () { window.open("https://www.hitmeister.de/versandpartner/online-marktplatz/"); }',
        ));

        // Api settings
        $this->form->setElement('text', 'clientKey', array(
            'itemId' => 'hmClientKey',
            'label' => 'API: Client key',
            'description' => 'Diese Information finden Sie im Hitmeister Account unter Shopseinstellungen; API.',
            'required' => true,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        $this->form->setElement('text', 'secretKey', array(
            'itemId' => 'hmSecretKey',
            'label' => 'API: Secret key',
            'description' => 'Diese Information finden Sie im Hitmeister-Account unter Shopseinstellungen; API.',
            'required' => true,
            'afterSubTpl' => '<h2>Ablauf Plugininstallation</h2>
            <ul>
            <li>* Installation</li>
            <li>* Api-Credetials eintragen</li>
            <li>* speichern</li>
            <li>* aktivieren</li>
            <li>* Shippinggroups auswählen</li>
            <li>* speichern</li>
            <li>* installation abgeschlossen</li>
            </ul>',
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        $this->form->setElement('text', 'apiUrl', array(
            'label' => 'API: URL',
            'description' => 'Welche API Version nutzen Sie',
            'value' => 'https://www.hitmeister.de/api/v1/',
            'required' => true,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        // Stock management
        $this->form->setElement('select', 'syncStatus', array(
          'label' => 'Stock: Sync status',
          'description' => 'Bitte legen Sie den globalen Sync Status für den Subshop fest. ACHTUNG: Wenn sie Artikel eines Shops bei Hitmeister blocken oder löschen wollen, dann müssen Sie den entsprechenden Aufruf vorher im Hitmeister Modul starten.',
          'value' => 0,
          'required' => true,
          'store' => array(
            array(1, 'Aktiviert'),
            array(0, 'Deaktiviert'),
          ),
          'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        $this->form->setElement('select', 'defaultDelivery', array(
            'label' => 'Stock: Default delivery time',
            'description' => 'Sollten Sie bei Artikeln keine Lieferzeit hinterlegt haben, dann wird diese hier eingetragene Lieferzeit automatisch hinterlegt.',
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
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        $this->form->setElement('select', 'defaultCondition', array(
            'label' => 'Stock: Default article condition',
            'description' => 'Bitte legen Sie den globalen Artikelzustand fest. Diese Einstellung wird für alle auf Hitmeister angebotenen Artikel übernommen.',
            'value' => Constants::CONDITION_NEW,
            'required' => true,
            'store' => array(
                array(Constants::CONDITION_NEW, Constants::CONDITION_NEW),
                array(Constants::CONDITION_USED_AS_NEW, Constants::CONDITION_USED_AS_NEW),
                array(Constants::CONDITION_USED_VERY_GOOD, Constants::CONDITION_USED_VERY_GOOD),
                array(Constants::CONDITION_USED_GOOD, Constants::CONDITION_USED_GOOD),
                array(Constants::CONDITION_USED_ACCEPTABLE, Constants::CONDITION_USED_ACCEPTABLE),
            ),
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        // Shipping Group
        $this->form->setElement('combo', 'defaultShippingGroup', array(
          'itemId' => 'defaultShippingGroup',
          'label' => 'Shipping Group: Default shipping code',
          'description' => 'Bitte legen Sie die globale Shipping Group fest. Diese Einstellung wird für alle auf Hitmeister angebotenen Artikel übernommen.',
          'value' => '',
          'valueField'=>'name',
          'displayField'=>'name',
          'queryMode' => 'remote',
          'queryCaching' => 'false',
          'store' => 'new Ext.data.Store({
                        parent: this,
                        fields: [ "name" ],
                        proxy : {
                         type : "ajax",
                         api : {
                             read: document.location.pathname + \'Hm/getShippingGroups\',
                         },
                         reader : {
                             type : "json",
                             root : "data"
                         },
                         extraParams : {
                            field_name: me.name
                         },
                        },
                        listeners : {
                            beforeload: function(store, operation, options){
                                var combo = store.parent,
                                    comboName = combo.getName(),
                                    regExpMatch = comboName.match(/values\[(\d+)\]\[(\d+)\]/),
                                    tabIndex = regExpMatch[1],
                                    queryClientKeys = Ext.ComponentQuery.query("[itemId=hmClientKey]"),
                                    querySecretKeys = Ext.ComponentQuery.query("[itemId=hmSecretKey]"),
                                    clientKeys = [],
                                    secretKeys = [];

                                    Ext.each(queryClientKeys, function(query, index) {
                                        var clientKeyValueMatch = query.getName().match(/values\[(\d+)\]\[(\d+)\]/),
                                            indexClientKeyValue = clientKeyValueMatch[1];
                                            clientKeys[indexClientKeyValue] = query;
                                    });

                                    Ext.each(querySecretKeys, function(query, index) {
                                        var secretKeyValueMatch = query.getName().match(/values\[(\d+)\]\[(\d+)\]/),
                                            indexSecretKeyValue = secretKeyValueMatch[1];
                                            secretKeys[indexSecretKeyValue] = query;
                                    });

                                    var clientKey = clientKeys[tabIndex].getValue(),
                                    secretKey = secretKeys[tabIndex].getValue();

                                if(clientKey.length > 0 && secretKey.length > 0){
                                    return true;
                                }else{
                                    return false;
                                }
                            }
                        }
                    })',
          'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        // Order
        $deliveryMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_premium_dispatch', array(), \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultDeliveryMethod', array(
            'label' => 'Orders: Default delivery method',
            'description' => 'Bitte verknüpfen Sie Ihre Shopware Versandart.',
            'required' => true,
            'value' => !empty($deliveryMethods) ? $deliveryMethods[0][0] : '',
            'store' => $deliveryMethods,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        $paymentMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_core_paymentmeans', array(), \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultPaymentMethod', array(
            'label' => 'Orders: Default payment method',
            'description' => 'Bitte wählen Sie eine Bezahlmethode, die Ihrem System mitteilt, dass der Kauf bereits bezahlt ist, da Hitmeister die Zahlungsabwicklung für Sie übernimmt.',
            'required' => true,
            'value' => !empty($paymentMethods) ? $paymentMethods[0][0] : '',
            'store' => $paymentMethods,
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
        ));

        // Delivery
        $this->form->setElement('select', 'defaultCarrier', array(
            'label' => 'Shipping: Default carrier',
            'description' => 'Welchen Versanddienstleister nutzen Sie?',
            'value' => Constants::CARRIER_CODE_DHL,
            'required' => true,
            'store' => array(
                array(Constants::CARRIER_CODE_OTHER, Constants::CARRIER_CODE_OTHER),
                array(Constants::CARRIER_CODE_COMPUTERUNIVERSE, Constants::CARRIER_CODE_COMPUTERUNIVERSE),
                array(Constants::CARRIER_CODE_DHL, Constants::CARRIER_CODE_DHL),
                array(Constants::CARRIER_CODE_DHL_2_MH, Constants::CARRIER_CODE_DHL_2_MH),
                array(Constants::CARRIER_CODE_DHL_FREIGHT, Constants::CARRIER_CODE_DHL_FREIGHT),
                array(Constants::CARRIER_CODE_DTL, Constants::CARRIER_CODE_DTL),
                array(Constants::CARRIER_CODE_DPD, Constants::CARRIER_CODE_DPD),
                array(Constants::CARRIER_CODE_DEUTSCHE_POST, Constants::CARRIER_CODE_DEUTSCHE_POST),
                array(Constants::CARRIER_CODE_DACHSER, Constants::CARRIER_CODE_DACHSER),
                array(Constants::CARRIER_CODE_EMONS, Constants::CARRIER_CODE_EMONS),
                array(Constants::CARRIER_CODE_FEDEX, Constants::CARRIER_CODE_FEDEX),
                array(Constants::CARRIER_CODE_GLS, Constants::CARRIER_CODE_GLS),
                array(Constants::CARRIER_CODE_GEL, Constants::CARRIER_CODE_GEL),
                array(Constants::CARRIER_CODE_HERMES, Constants::CARRIER_CODE_HERMES),
                array(Constants::CARRIER_CODE_HERMES_2_MH, Constants::CARRIER_CODE_HERMES_2_MH),
                array(Constants::CARRIER_CODE_HELLMANN, Constants::CARRIER_CODE_HELLMANN),
                array(Constants::CARRIER_CODE_ILOXX, Constants::CARRIER_CODE_ILOXX),
                array(Constants::CARRIER_CODE_KUEHNE_NAGEL, Constants::CARRIER_CODE_KUEHNE_NAGEL),
                array(Constants::CARRIER_CODE_RHENUS, Constants::CARRIER_CODE_RHENUS),
                array(Constants::CARRIER_CODE_SCHENKER, Constants::CARRIER_CODE_SCHENKER),
                array(Constants::CARRIER_CODE_SPEDITION_GUETTLER, Constants::CARRIER_CODE_SPEDITION_GUETTLER),
                array(Constants::CARRIER_CODE_TNT, Constants::CARRIER_CODE_TNT),
                array(Constants::CARRIER_CODE_TRANS_O_FLEX, Constants::CARRIER_CODE_TRANS_O_FLEX),
                array(Constants::CARRIER_CODE_UPS, Constants::CARRIER_CODE_UPS),
            ),
            'scope' => \Shopware\Models\Config\Element::SCOPE_SHOP,
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