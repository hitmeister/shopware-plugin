//{namespace name=backend/hm/view/notifications}
Ext.define('Shopware.apps.Hm.view.notifications.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-notifications-grid',

    // shop filter
    ShopFilter: Ext.create('Ext.form.field.ComboBox', {
        store: Ext.create('Shopware.apps.Hm.store.Shop').load(),
        queryMode: 'local',
        editable: false,
        valueField: 'id',
        displayField: 'name'
    }),

    ButtonEnableAll: Ext.create('Ext.button.Button', {
        text: '{s name=hm/notifications/grid/toolbar/button/enable_all}{/s}',
        iconCls: 'sprite-plus-circle-frame'
    }),

    ButtonDisableAll: Ext.create('Ext.button.Button', {
        text: '{s name=hm/notifications/grid/toolbar/button/disable_all}{/s}',
        iconCls: 'sprite-minus-circle-frame'
    }),

    initComponent: function () {
        var me = this;

        me.addEvents('enable_all', 'disable_all', 'enable', 'disable');

        me.store = Ext.create('Shopware.apps.Hm.store.Notification');
        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        // reset default value
        me.ShopFilter.getStore().removeAt(0);
        me.ShopFilter.select(me.ShopFilter.getStore().getAt(0));
        me.setGridStoreShopFilter();

        // add filter event
        me.ShopFilter.on('change', function(){
            var shopId = me.getShopFilterValue();
            if(shopId){
                me.ButtonEnableAll.enable();
                me.ButtonDisableAll.enable();
            }else{
                me.ButtonEnableAll.disable();
                me.ButtonDisableAll.disable();
            }
            me.setGridStoreShopFilter();

        });

        me.ButtonEnableAll.on('click', function(){
            me.fireEvent('enable_all')
        });

        me.ButtonDisableAll.on('click', function(){
            me.fireEvent('disable_all')
        });

        me.callParent(arguments);
    },

    getCreateColumns: function() {
        var me = this;

        return [
            {
                text: '{s name=hm/notifications/grid/column/event_name/title}{/s}',
                dataIndex: 'event_name',
                menuDisabled: true,
                sortable: false,
                flex: 1
            },
            {
                xtype: 'booleancolumn',
                text: '{s name=hm/notifications/grid/column/active/title}{/s}',
                dataIndex: 'is_active',
                menuDisabled: true,
                sortable: false,
                width: 50,
                renderer: function (value) {
                    var checked = 'sprite-ui-check-box-uncheck';
                    if (value == true) {
                        checked = 'sprite-ui-check-box';
                    }
                    return '<span style="display:block; margin: 0 auto; height:16px; width:16px;" class="' + checked + '"></span>';
                }
            },
            {
                text: '{s name=hm/notifications/grid/column/callback_url/title}{/s}',
                dataIndex: 'callback_url',
                menuDisabled: true,
                sortable: false,
                flex: 2
            },
            {
                text: '{s name=hm/notifications/grid/column/fallback_email/title}{/s}',
                dataIndex: 'fallback_email',
                menuDisabled: true,
                sortable: false,
                flex: 1
            },
            {
                xtype: 'actioncolumn',
                text: '{s name=hm/notifications/grid/column/options/title}{/s}',
                menuDisabled: true,
                width: 50,
                items: [
                    {
                        iconCls: 'sprite-plus-circle-frame',
                        tooltip: '{s name=hm/notifications/grid/column/options/activate}{/s}',
                        handler: function(grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('enable', record)
                        },
                        getClass: function(value, metaData, record) {
                            return record.get('is_active') == false ? 'x-grid-icon' : 'x-hidden';
                        }
                    },
                    {
                        iconCls: 'sprite-minus-circle-frame',
                        tooltip: '{s name=hm/notifications/grid/column/options/stop}{/s}',
                        handler: function(grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('disable', record)
                        },
                        getClass: function(value, metaData, record) {
                            return record.get('is_active') == true ? 'x-grid-icon' : 'x-hidden';
                        }
                    }
                ]
            }
        ];
    },

    getCreatePaging: function() {
        var me = this;

        return Ext.create('Ext.toolbar.Paging', {
            store: me.store,
            dock: 'bottom',
            displayInfo: true
        })
    },

    getCreateToolbar: function() {
        var me = this;

        return {
            xtype: 'toolbar',
            ui: 'shopware-ui',
            items: [
                me.ShopFilter,
                '-',
                me.ButtonEnableAll,
                me.ButtonDisableAll,
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
        var me = this;
        shopId = me.getShopFilterValue();
        if(shopId){
            me.store.getProxy().extraParams = {
                shopId: shopId
            };
            me.store.reload();
        }
    },
});
