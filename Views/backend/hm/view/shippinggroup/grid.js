//{namespace name=backend/hm/view/shippinggroup}
Ext.define('Shopware.apps.Hm.view.shippinggroup.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-shippinggroup-grid',

    // shop filter
    ShopFilter: Ext.create('Ext.form.field.ComboBox', {
        store: Ext.create('Shopware.apps.Hm.store.Shop').load(),
        queryMode: 'local',
        editable: false,
        valueField: 'id',
        displayField: 'name',
        emptyText: 'Shop Filter',
    }),

    // shipping groups
    ShippinggroupCombo: Ext.create('Ext.form.field.ComboBox', {
        store: Ext.create('Ext.data.Store', { model: 'Shopware.apps.Hm.model.hm.Shippinggroup' }),
        queryMode: 'local',
        editable: false,
        valueField: 'name',
        displayField: 'name',
        forceSelection: true,
        emptyText: '{s name=hm/shippinggroup/grid/toolbar/combobox/choose_shippinggroup}{/s}',
    }),

    EditShippinggroupMenu: Ext.create('Ext.Button',{
        text: '{s name=hm/shippinggroup/grid/toolbar/button/edit_shippinggroup}{/s}',
        iconCls: 'sprite-pencil',
        disabled: true,
        menu: {
            xtype: 'menu',
            plain: true
        }
    }),

    reloadTree: false,

    initComponent: function () {
        var me = this;

        me.addEvents('onSyncSelected');

        me.selModel = me.getGridSelModel();

        me.store = Ext.create('Shopware.apps.Hm.store.Shippinggroup');

        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        // reset default value
        me.ShopFilter.getStore().removeAt(0);
        me.ShopFilter.select(me.ShopFilter.getStore().getAt(0));
        me.setGridStoreShopFilter();
        // after init reload tree
        me.reloadTree = true;

        // add filter event
        me.ShopFilter.on('change', function(){
            me.reloadTree = true;
            me.setGridStoreShopFilter();
        });

        me.ShippinggroupCombo.on('change', function(field, value){
            if(value){
                var selectionModel = me.getSelectionModel(),
                    records = selectionModel.getSelection(),
                    shopId = me.getShopFilterValue();
                if (records.length > 0) {
                    me.fireEvent('onSyncSelected', records, value, shopId);
                }
            }
            field.reset();
        });
        me.ShippinggroupCombo.store.on("beforeload", function( comboStore, operation, eOpts){
            comboStore.loadData([],false);
            operation.params = {
                shopId: me.getShopFilterValue()
            };
        });
        me.ShippinggroupCombo.getStore().load();

        me.EditShippinggroupMenu.menu.add( me.ShippinggroupCombo );

        me.callParent(arguments);
    },

    /**
     * Creates the grid selection model for checkboxes
     *
     * @return [Ext.selection.CheckboxModel] grid selection model
     */
    getGridSelModel: function () {
        var me = this;

        return Ext.create('Ext.selection.CheckboxModel',{
            listeners:{
                // prevent selection of records with no ean
                beforeselect: function(selModel, record, index) {
                    if ((Ext.String.trim( record.get('ean')) == '')) {
                        Ext.Msg.alert('{s name=hm/shippinggroup/grid/column/gridselection/alert/title}{/s}','{s name=hm/shippinggroup/grid/column/gridselection/alert/message}{/s}');
                        return false;
                    }
                },
                selectionchange: function (selModel, selections) {
                    me.EditShippinggroupMenu.setDisabled(selections.length === 0);
                }
            }
        });
    },

    getCreateColumns: function () {
        var me = this;

        return [
            {
                text: '{s namespace=backend/hm/view/stock  name=hm/stock/grid/column/article/title}{/s}',
                dataIndex: 'ordernumber',
                menuDisabled: true,
                flex: 1
            },
            {
                text: '{s namespace=backend/hm/view/stock  name=hm/stock/grid/column/ean/title}{/s}',
                dataIndex: 'ean',
                menuDisabled: true,
                width: 120,
                renderer: function (value, metaData) {
                    if ('' == Ext.String.trim(value)) {
                        metaData.tdCls = 'grid-cell-empty-ean';
                        return '{s namespace=backend/hm/view/stock  name=hm/stock/grid/column/ean/required}{/s}';
                    }
                    return value;
                }
            },
            {
                text: '{s namespace=backend/hm/view/stock  name=hm/stock/grid/column/name/title}{/s}',
                dataIndex: 'name',
                menuDisabled: true,
                flex: 2
            },
            {
                text: '{s namespace=backend/hm/view/stock  name=hm/stock/grid/column/is_stock/title}{/s}',
                dataIndex: 'instock',
                menuDisabled: true,
                width: 40
            },
            {
                text: '{s name=hm/shippinggroup/grid/column/shippinggroup/title}{/s}',
                dataIndex: 'hm_shippinggroup',
                menuDisabled: true,
                width: 110
            }
        ];
    },

    getCreatePaging: function () {
        var me = this;

        return Ext.create('Ext.toolbar.Paging', {
            store: me.store,
            dock: 'bottom',
            displayInfo: true
        })
    },

    getCreateToolbar: function () {
        var me = this,
            store = me.getStore();

        return {
            xtype: 'toolbar',
            ui: 'shopware-ui',
            items: [
                me.ShopFilter,
                '-',
                me.EditShippinggroupMenu,
                '->',
                {
                    xtype: 'textfield',
                    emptyText: '{s namespace=backend/hm/view/stock  name=hm/stock/grid/toolbar/search_empty}{/s}',
                    cls: 'searchfield',
                    enableKeyEvents: true,
                    checkChangeBuffer: 200,
                    listeners: {
                        'change': function (field, value) {
                            var searchString = Ext.String.trim(value);
                            if (searchString == '') {
                                store.clearFilter();
                            } else {
                                store.clearFilter(true);
                                store.filter([
                                    { property: 'ordernumber', value: searchString },
                                    { property: 'name', value: searchString },
                                    { property: 'ean', value: searchString }
                                ]);
                            }
                        }
                    }
                }
            ]
        };
    },

    getShopFilterValue: function() {
        var me = this,
            shopId = me.ShopFilter.getValue();
        shopId = Ext.util.Format.number(shopId, '0/i');
        if (shopId > 0) {
            return shopId;
        }

        return false;
    },

    setGridStoreShopFilter: function() {
        var me = this,
            shopId = me.getShopFilterValue();

        if(shopId){
            me.reloadGridStore([{ property: 'shopId', value: shopId }]);
            var subApps = Shopware.app.Application.subApplications,
                hmApp = subApps.findBy(function(item) {
                    if(item.$className == 'Shopware.apps.Hm') {
                        return true;
                    }
                });
            hmApp.getController('Shippinggroup').getTree().getStore().load();
            me.ShippinggroupCombo.getStore().reload();
        }
    },

    reloadGridStore: function(filter) {

        var me = this;
        me.store.clearFilter(true);
        me.store.filter(filter);
    },

});