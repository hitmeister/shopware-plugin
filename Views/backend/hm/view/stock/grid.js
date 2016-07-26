//{namespace name=backend/hm/view/stock}
Ext.define('Shopware.apps.Hm.view.stock.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-stock-grid',

    // statuses
    StatusNew: 'new',
    StatusBlocked: 'blocked',
    StatusNotFound: 'not_found_on_hm',
    StatusSynchronizing: 'synchronizing',

    // shop filter
    ShopFilter: Ext.create('Ext.form.field.ComboBox', {
        store: Ext.create('Shopware.apps.Hm.store.Shop').load(),
        queryMode: 'local',
        editable: false,
        valueField: 'id',
        displayField: 'name',
        emptyText: 'Shop Filter',
    }),

    ButtonSyncAll: Ext.create('Ext.button.Button', {
        text: '{s name=hm/stock/grid/toolbar/button/sync_stock_listed}{/s}',
        iconCls: 'sprite-arrow-circle-045-left',
        disabled: true
    }),

    ButtonBlockAll: Ext.create('Ext.button.Button', {
        text: '{s name=hm/stock/grid/toolbar/button/stop_sync_stock_listed}{/s}',
        iconCls: 'sprite-cross-circle',
        disabled: true
    }),

    ButtonDeleteAll: Ext.create('Ext.button.Button', {
        text: '{s name=hm/stock/grid/toolbar/button/delete_stock_listed}{/s}',
        iconCls: 'sprite-minus-circle-frame',
        disabled: true
    }),



    initComponent: function () {
        var me = this;

        me.viewConfig = {
            getRowClass: function(record) {
                var status = record.get('hm_status');
                switch (status) {
                    case me.StatusBlocked:
                        return 'grid-row-stock-status-blocked';
                    case me.StatusNotFound:
                        return 'grid-row-stock-status-not-found';
                }
                return '';
            }
        };

        me.addEvents('block', 'unblock', 'sync', 'sync_all', 'block_all', 'delete_all');

        me.store = Ext.create('Shopware.apps.Hm.store.Stock');
        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        // add filter event
        me.ShopFilter.on('change', function(field, value){
            shopId = me.getShopFilterValue();
            if(shopId){
                me.ButtonSyncAll.enable();
                me.ButtonBlockAll.enable();
                me.ButtonDeleteAll.enable();
                me.store.clearFilter(true);
                me.store.filter([
                    { property: 'shopId', value: shopId }
                ]);
            }else{
                me.ButtonSyncAll.disable();
                me.ButtonBlockAll.disable();
                me.ButtonDeleteAll.disable();
                me.store.clearFilter();
            }

        });

        me.ButtonSyncAll.on('click', function(field, value){
            me.fireEvent('sync_all')
        });

        me.ButtonBlockAll.on('click', function(){
            me.fireEvent('block_all')
        });

        me.ButtonDeleteAll.on('click', function(){
            me.fireEvent('delete_all')
        });

        me.callParent(arguments);
    },

    getShopFilterValue: function() {
        var me = this;
        var shopId = me.ShopFilter.getValue();
        shopId = Ext.util.Format.number(shopId, '0/i');
        if (shopId > 0) {
            return shopId;
        }

        return false;
    },

    getCreateColumns: function () {
        var me = this;

        return [
            {
                text: '{s name=hm/stock/grid/column/article/title}{/s}',
                dataIndex: 'ordernumber',
                menuDisabled: true,
                flex: 1
            },
            {
                text: '{s name=hm/stock/grid/column/ean/title}{/s}',
                dataIndex: 'ean',
                menuDisabled: true,
                width: 120,
                renderer: function (value, metaData) {
                    if ('' == Ext.String.trim(value)) {
                        metaData.tdCls = 'grid-cell-empty-ean';
                        return '{s name=hm/stock/grid/column/ean/required}{/s}';
                    }
                    return value;
                }
            },
            {
                text: '{s name=hm/stock/grid/column/name/title}{/s}',
                dataIndex: 'name',
                menuDisabled: true,
                flex: 2
            },
            {
                text: '{s name=hm/stock/grid/column/is_stock/title}{/s}',
                dataIndex: 'instock',
                menuDisabled: true,
                width: 40
            },
            {
                xtype: 'datecolumn',
                text: '{s name=hm/stock/grid/column/last_access_date/title}{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'hm_last_access_date',
                menuDisabled: true,
                width: 120,
                renderer: function (value, metaData, record) {
                    var shopFilter = me.getShopFilterValue();
                    if(!shopFilter){
                        return '';
                    }
                    return value;
                }
            },
            {
                text: '{s name=hm/stock/grid/column/status/title}{/s}',
                dataIndex: 'hm_status',
                menuDisabled: true,
                width: 110,
                renderer: function (value, metaData, record) {
                    var status = record.get('hm_status');
                    var shopFilter = me.getShopFilterValue();
                    if(!shopFilter){
                        return ''
                    }
                    switch (status) {
                        case null:
                        case '':
                        case me.StatusNew:
                            return '{s name=hm/stock/grid/column/status/value/new}{/s}';
                        case me.StatusBlocked:
                            return '{s name=hm/stock/grid/column/status/value/blocked}{/s}';
                        case me.StatusNotFound:
                            return '{s name=hm/stock/grid/column/status/value/not_found}{/s}';
                        case me.StatusSynchronizing:
                            return '{s name=hm/stock/grid/column/status/value/synchronizing}{/s}';
                    }
                }
            },
            {
                xtype: 'actioncolumn',
                text: '{s name=hm/stock/grid/column/options/title}{/s}',
                menuDisabled: true,
                width: 60,
                items: [
                    {
                        iconCls: 'sprite-plus-circle-frame',
                        tooltip: '{s name=hm/stock/grid/column/options/unblock/title}{/s}',
                        handler: function (grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('unblock', record)
                        },
                        getClass: function (value, metaData, record) {
                            var shopFilter = me.getShopFilterValue();
                            var status = record.get('hm_status');
                            return status == 'blocked' && shopFilter ? 'x-grid-icon' : 'x-hidden';
                        }
                    },
                    {
                        iconCls: 'sprite-cross-circle',
                        tooltip: '{s name=hm/stock/grid/column/options/block/title}{/s}',
                        handler: function (grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('block', record)
                        },
                        getClass: function (value, metaData, record) {
                            var shopFilter = me.getShopFilterValue();
                            var status = record.get('hm_status');
                            return status != 'blocked' && shopFilter ? 'x-grid-icon' : 'x-hidden';
                        }
                    },
                    {
                        iconCls: 'sprite-arrow-circle-045-left',
                        tooltip: '{s name=hm/stock/grid/column/options/sync_stock/title}{/s}',
                        handler: function (grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('sync', record)
                        },
                        getClass: function (value, metaData, record) {
                            // synchronization is not possible when ean is empty
                            if ('' == Ext.String.trim(record.get('ean'))) {
                                return 'x-hidden';
                            }
                            var shopFilter = me.getShopFilterValue();
                            var status = record.get('hm_status');
                            return status != 'blocked' && shopFilter ? 'x-grid-icon' : 'x-hidden';
                        }
                    }
                ]
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
                me.ButtonSyncAll,
                me.ButtonBlockAll,
                me.ButtonDeleteAll,
                '->',
                {
                    xtype: 'textfield',
                    emptyText: '{s name=hm/stock/grid/toolbar/search_empty}{/s}',
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
    }
});