//{namespace name=backend/hm/view/export}
Ext.define('Shopware.apps.Hm.view.export.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-export-grid',

    // shop filter
    ShopFilter: Ext.create('Ext.form.field.ComboBox', {
        store: Ext.create('Shopware.apps.Hm.store.Shop').load(),
        queryMode: 'local',
        editable: false,
        valueField: 'id',
        displayField: 'name',
    }),

    ButtonExport: Ext.create('Ext.button.Button', {
        text: '{s name=hm/stock/grid/toolbar/button/export}{/s}',
        iconCls: 'sprite-plus-circle-frame'
    }),

    initComponent: function () {
        var me = this;

        me.addEvents('export');

        me.viewConfig = {
            getRowClass: function(record) {
                var status = record.get('status'),
                    failed = ['CHECKING_FAILED', 'DOWNLOADING_FAILED', 'IMPORTING_FAILED', 'PREPARING_FAILED', 'PREPROCESSING_FAILED'];
                if (failed.indexOf(status) >= 0) {
                    return 'grid-row-export-status-failed';
                }
                return '';
            }
        };

        me.store = Ext.create('Shopware.apps.Hm.store.Export');

        // reset default value
        me.ShopFilter.getStore().removeAt(0);
        me.ShopFilter.select(me.ShopFilter.getStore().getAt(0));
        me.setGridStoreShopFilter();

        // add filter event
        me.ShopFilter.on('change', function(){
            var shopId = me.getShopFilterValue();
            if(shopId){
                me.ButtonExport.enable();
            }else{
                me.ButtonExport.disable();
            }
            me.setGridStoreShopFilter();

        });

        me.ButtonExport.on('click', function(){
            me.fireEvent('export')
        });

        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        me.callParent(arguments);
    },

    getCreateColumns: function() {
        return [
            {
                text: '{s name=hm/stock/grid/column/id/title}{/s}',
                dataIndex: 'id_import_file',
                menuDisabled: true,
                sortable: false,
                width: 70
            },
            {
                text: '{s name=hm/stock/grid/column/status/title}{/s}',
                dataIndex: 'status',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                text: '{s name=hm/stock/grid/column/total_lines/title}{/s}',
                dataIndex: 'total_lines',
                menuDisabled: true,
                sortable: false,
                width: 40
            },
            {
                text: '{s name=hm/stock/grid/column/error_count/title}{/s}',
                dataIndex: 'error_count',
                menuDisabled: true,
                sortable: false,
                width: 40
            },
            {
                xtype: 'datecolumn',
                text: '{s name=hm/stock/grid/column/ts_created_date/title}{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'ts_created',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                xtype: 'datecolumn',
                text: '{s name=hm/stock/grid/column/ts_completed/title}{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'ts_completed',
                menuDisabled: true,
                sortable: false,
                width: 120
            },
            {
                text: '{s name=hm/stock/grid/column/note/title}{/s}',
                dataIndex: 'note',
                menuDisabled: true,
                sortable: false,
                flex: 1
            },
            {
                xtype: 'actioncolumn',
                text: '',
                menuDisabled: true,
                width: 20,
                items: [
                    {
                        iconCls: 'sprite-lightning',
                        tooltip: '{s name=hm/stock/grid/column/open_link/title}{/s}',
                        handler: function(grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            window.open(record.get('uri'));
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
                me.ButtonExport,
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
