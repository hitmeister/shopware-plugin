<?php

namespace ShopwarePlugins\HitmeMarketplace\Bootstrap;

use Hitmeister\Component\Api\Transfers\Constants;
use Shopware\Models\Config\Element;
use Shopware\Models\Config\ElementTranslation;
use Shopware\Models\Config\Form as ConfigForm;
use Shopware\Models\Config\FormTranslation;
use Shopware\Models\Shop\Locale;
use ShopwarePlugins\HitmeMarketplace\Components\Shop as HmShop;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Models\Shop\Shop as SwShop;

/**
 * Class Form
 * @package ShopwarePlugins\HitmeMarketplace\Bootstrap
 */
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
     * @throws \Exception
     */
    public static function updateForm()
    {
        $hmShops = HmShop::getActiveShops();
        /** @var InstallerService $pluginManager */
        $pluginManager = Shopware()->Container()->get('shopware.plugin_Manager');
        $plugin = $pluginManager->getPluginByName('HitmeMarketplace');

        foreach ($hmShops as $hmShop) {
            $shop = Shopware()->Models()->getRepository(SwShop::class)->find($hmShop['id']);
            $pluginManager->saveConfigElement($plugin, 'apiUrl', 'https://www.real.de/api/v1/', $shop);
        }
    }

    /**
     *
     */
    public function create()
    {
        $this->form->setDescription('<p>real.de ist ein offener Marktplatz, der Händlern in über 5.000 Kategorien einen attraktiven Vertriebskanal für über 30 unterschiedliche Versandländer bietet. Die Internetpräsenz der real,- SB-Warenhaus GmbH vereint eine hohe Markenbekanntheit mit gezieltem Multi-Channel-Marketing und bietet Ihnen eine optimale Reichweitenstärke für Ihre Angebote.</p><br /><p>real.de - Attraktiv für Händler:</p><ul><li>Markenbekanntheit durch 285 SB-Warenhäuser und 23 Millionen Handzettel pro Woche</li><li>Komfortables Einstellen anhand der EAN/GTIN</li><li>Kostenfreie Bewerbung Ihrer Artikel in Preissuchmaschinen, SEO und SEA</li><li>Direkter und persönlicher Service</li><li>Verkaufserlöse direkt von real.de</li><li>Keine Vertragslaufzeiten</li><li>Transparentes Kostenmodell</li></ul><p>real.de - Attraktiv für Endkunden:</p><ul><li>Sammeln und Einlösen von Payback-Punkten</li><li>Über 10 verschiedene Bezahlmethoden</li> <li>0%-Finanzierung: Von Zuhause mittels Online-Ident-Verfahren</li></ul>');

        $this->form->setElement('button', 'openForm', [
            'label' => 'Jetzt registrieren!',
            'handler' => 'function () { window.open("https://www.real.de/versandpartner/software-schnittstellen/"); }'
        ]);

        // Api settings
        $this->form->setElement('text', 'clientKey', [
            'itemId' => 'hmClientKey',
            'label' => 'API: Client key',
            'description' => 'Diese Information finden Sie im real.de Account unter Shopseinstellungen; API.',
            'required' => true,
            'scope' => Element::SCOPE_SHOP
        ]);

        $this->form->setElement('text', 'secretKey', [
            'itemId' => 'hmSecretKey',
            'label' => 'API: Secret key',
            'description' => 'Diese Information finden Sie im real.de Account unter Shopseinstellungen; API.',
            'required' => true,
            'afterSubTpl' => '<h2>Ablauf Plugininstallation</h2>
            <ul>
            <li>* Installation</li>
            <li>* Api-Credentials eintragen</li>
            <li>* speichern</li>
            <li>* aktivieren</li>
            <li>* Shippinggroups auswählen</li>
            <li>* Stock: Sync-Status aktivieren</li>
            <li>* speichern</li>
            <li>* installation abgeschlossen</li>
            </ul>',
            'scope' => Element::SCOPE_SHOP
        ]);

        $this->form->setElement('text', 'apiUrl', [
            'label' => 'API: URL',
            'description' => 'Welche API Version nutzen Sie',
            'value' => 'https://www.real.de/api/v1/',
            'required' => true,
            'scope' => Element::SCOPE_SHOP
        ]);

        // Stock management
        $this->form->setElement('select', 'syncStatus', [
            'label' => 'Stock: Sync status',
            'description' => 'Bitte legen Sie den globalen Sync Status für den Subshop fest. ACHTUNG: Wenn sie Artikel eines Shops bei real.de blocken oder löschen wollen, dann müssen Sie den entsprechenden Aufruf vorher im real.de Modul starten.',
            'value' => 0,
            'required' => true,
            'store' => [
                [1, 'Aktiviert'],
                [0, 'Deaktiviert']
            ],
            'scope' => Element::SCOPE_SHOP
        ]);

        $this->form->setElement('select', 'defaultDelivery', [
            'label' => 'Stock: Default delivery time',
            'description' => 'Sollten Sie bei Artikeln keine Lieferzeit hinterlegt haben, dann wird diese hier eingetragene Lieferzeit automatisch hinterlegt.',
            'value' => Constants::DELIVERY_TIME_H,
            'required' => true,
            'store' => [
                [Constants::DELIVERY_TIME_H, 'When it\'s available or no shipping estimate possible'],
                [Constants::DELIVERY_TIME_A, 'Ships within 24 hours'],
                [Constants::DELIVERY_TIME_B, 'Ships in 1-3 days'],
                [Constants::DELIVERY_TIME_C, 'Ships in 4-6 days'],
                [Constants::DELIVERY_TIME_D, 'Ships in 7-10 days'],
                [Constants::DELIVERY_TIME_E, 'Ships in 11-14 days'],
                [Constants::DELIVERY_TIME_F, 'Ships in 3-4 weeks'],
                [Constants::DELIVERY_TIME_G, 'Ships in 5-7 weeks'],
                [Constants::DELIVERY_TIME_I, 'Ships in 8-10 weeks']
            ],
            'scope' => Element::SCOPE_SHOP
        ]);

        $this->form->setElement('select', 'defaultCondition', [
            'label' => 'Stock: Default article condition',
            'description' => 'Bitte legen Sie den globalen Artikelzustand fest. Diese Einstellung wird für alle auf real.de angebotenen Artikel übernommen.',
            'value' => Constants::CONDITION_NEW,
            'required' => true,
            'store' => [
                [Constants::CONDITION_NEW, Constants::CONDITION_NEW],
                [Constants::CONDITION_USED_AS_NEW, Constants::CONDITION_USED_AS_NEW],
                [Constants::CONDITION_USED_VERY_GOOD, Constants::CONDITION_USED_VERY_GOOD],
                [Constants::CONDITION_USED_GOOD, Constants::CONDITION_USED_GOOD],
                [Constants::CONDITION_USED_ACCEPTABLE, Constants::CONDITION_USED_ACCEPTABLE]
            ],
            'scope' => Element::SCOPE_SHOP
        ]);

        // Shipping Group
        $this->form->setElement('combo', 'defaultShippingGroup', [
            'itemId' => 'defaultShippingGroup',
            'label' => 'Shipping Group: Default shipping code',
            'description' => 'Bitte legen Sie die globale Shipping Group fest. Diese Einstellung wird für alle auf real.de angebotenen Artikel übernommen.',
            'value' => '',
            'valueField' => 'name',
            'displayField' => 'name',
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
            'scope' => Element::SCOPE_SHOP
        ]);

        // Order
        $deliveryMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_premium_dispatch', [], \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultDeliveryMethod', [
            'label' => 'Orders: Default delivery method',
            'description' => 'Bitte verknüpfen Sie Ihre Shopware Versandart.',
            'required' => true,
            'queryCaching' => 'false',
            'value' => !empty($deliveryMethods) ? $deliveryMethods[0][0] : '',
            'store' => $deliveryMethods,
            'scope' => Element::SCOPE_SHOP
        ]);

        $paymentMethods = Shopware()->Db()->fetchAll('SELECT id, name FROM s_core_paymentmeans', [], \PDO::FETCH_NUM);
        $this->form->setElement('select', 'defaultPaymentMethod', [
            'label' => 'Orders: Default payment method',
            'description' => 'Bitte wählen Sie eine Bezahlmethode, die Ihrem System mitteilt, dass der Kauf bereits bezahlt ist, da Real die Zahlungsabwicklung für Sie übernimmt.',
            'required' => true,
            'queryCaching' => 'false',
            'value' => !empty($paymentMethods) ? $paymentMethods[0][0] : '',
            'store' => $paymentMethods,
            'scope' => Element::SCOPE_SHOP
        ]);

        // Delivery
        $this->form->setElement('select', 'defaultCarrier', [
            'label' => 'Shipping: Default carrier',
            'description' => 'Welchen Versanddienstleister nutzen Sie?',
            'value' => Constants::CARRIER_CODE_DHL,
            'required' => true,
            'store' => [
                [Constants::CARRIER_CODE_OTHER, Constants::CARRIER_CODE_OTHER],
                [Constants::CARRIER_CODE_COMPUTERUNIVERSE, Constants::CARRIER_CODE_COMPUTERUNIVERSE],
                [Constants::CARRIER_CODE_DHL, Constants::CARRIER_CODE_DHL],
                [Constants::CARRIER_CODE_DHL_2_MH, Constants::CARRIER_CODE_DHL_2_MH],
                [Constants::CARRIER_CODE_DHL_FREIGHT, Constants::CARRIER_CODE_DHL_FREIGHT],
                [Constants::CARRIER_CODE_DTL, Constants::CARRIER_CODE_DTL],
                [Constants::CARRIER_CODE_DPD, Constants::CARRIER_CODE_DPD],
                [Constants::CARRIER_CODE_DEUTSCHE_POST, Constants::CARRIER_CODE_DEUTSCHE_POST],
                [Constants::CARRIER_CODE_DACHSER, Constants::CARRIER_CODE_DACHSER],
                [Constants::CARRIER_CODE_EMONS, Constants::CARRIER_CODE_EMONS],
                [Constants::CARRIER_CODE_FEDEX, Constants::CARRIER_CODE_FEDEX],
                [Constants::CARRIER_CODE_GLS, Constants::CARRIER_CODE_GLS],
                [Constants::CARRIER_CODE_GEL, Constants::CARRIER_CODE_GEL],
                [Constants::CARRIER_CODE_HERMES, Constants::CARRIER_CODE_HERMES],
                [Constants::CARRIER_CODE_HERMES_2_MH, Constants::CARRIER_CODE_HERMES_2_MH],
                [Constants::CARRIER_CODE_HELLMANN, Constants::CARRIER_CODE_HELLMANN],
                [Constants::CARRIER_CODE_ILOXX, Constants::CARRIER_CODE_ILOXX],
                [Constants::CARRIER_CODE_KUEHNE_NAGEL, Constants::CARRIER_CODE_KUEHNE_NAGEL],
                [Constants::CARRIER_CODE_RHENUS, Constants::CARRIER_CODE_RHENUS],
                [Constants::CARRIER_CODE_SCHENKER, Constants::CARRIER_CODE_SCHENKER],
                [Constants::CARRIER_CODE_SPEDITION_GUETTLER, Constants::CARRIER_CODE_SPEDITION_GUETTLER],
                [Constants::CARRIER_CODE_TNT, Constants::CARRIER_CODE_TNT],
                [Constants::CARRIER_CODE_TRANS_O_FLEX, Constants::CARRIER_CODE_TRANS_O_FLEX],
                [Constants::CARRIER_CODE_UPS, Constants::CARRIER_CODE_UPS]
            ],
            'scope' => Element::SCOPE_SHOP
        ]);

        // Custom article attributes
        $this->form->setElement('combo', 'customArticleAttributes', [
            'itemId' => 'customArticleAttributes',
            'label' => 'Article attributes',
            'valueField' => 'name',
            'displayField' => 'name',
            'description' => 'Select the articles attributes you want to export',
            'value' => '',
            'emptyText' => 'Select articles attributes',
            'multiSelect' => true,
            'editable' => false,
            'queryMode' => 'local',
            'queryCaching' => 'false',
            'store' => 'new Ext.data.Store({
                        parent: this,
                        fields: [
                            { name: "name", type: "string", useNull: false },
                            { name: "label", type: "string", useNull: false }
                        ],
                        proxy : {
                         type : "ajax",
                         api : {
                             read: document.location.pathname + \'HmArticlesAttributes/getList\',
                         },
                         reader : {
                             type : "json",
                             root : "data",
                             totalProperty: "total"
                         }
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
            'scope' => Element::SCOPE_SHOP
        ]);
    }

    /**
     * @param array $translations
     */
    public function translateForm(array $translations)
    {
        $shopRepository = Shopware()->Models()->getRepository('\Shopware\Models\Shop\Locale');

        foreach ($translations as $locale => $snippets) {
            /** @var Locale $localeModel */
            if (null === ($localeModel = $shopRepository->findOneBy(['locale' => $locale]))) {
                continue;
            }

            // Translation for form description
            if ($snippets['plugin_form']) {
                $formTranslation = null;

                /* @var FormTranslation $translation */
                foreach ($this->form->getTranslations() as $translation) {
                    if ($translation->getLocale()->getLocale() === $locale) {
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
                    if ($translation->getLocale()->getLocale() === $locale) {
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
