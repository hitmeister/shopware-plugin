//{namespace name=backend/hm/view/stock}
Ext.define('Shopware.apps.Hm.view.stock.Grid', {
    extend: 'Ext.grid.Panel',
    alias: 'widget.hm-stock-grid',

    // statuses
    StatusNew: 'new',
    StatusBlocked: 'blocked',
    StatusNotFound: 'not_found_on_hm',
    StatusSynchronizing: 'synchronizing',

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

        me.addEvents('block', 'unblock', 'sync', 'sync_all');

        me.store = Ext.create('Shopware.apps.Hm.store.Stock');
        me.columns = me.getCreateColumns();
        me.dockedItems = [
            me.getCreateToolbar(),
            me.getCreatePaging()
        ];

        me.callParent(arguments);
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
                width: 120
            },
            {
                text: '{s name=hm/stock/grid/column/status/title}{/s}',
                dataIndex: 'hm_status',
                menuDisabled: true,
                width: 110,
                renderer: function (value, metaData, record) {
                    var status = record.get('hm_status');
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
                            var status = record.get('hm_status');
                            return status == 'blocked' ? 'x-grid-icon' : 'x-hidden';
                        }
                    },
                    {
                        iconCls: 'sprite-minus-circle-frame',
                        tooltip: '{s name=hm/stock/grid/column/options/block/title}{/s}',
                        handler: function (grid, rowIndex) {
                            var record = grid.getStore().getAt(rowIndex);
                            me.fireEvent('block', record)
                        },
                        getClass: function (value, metaData, record) {
                            var status = record.get('hm_status');
                            return status != 'blocked' ? 'x-grid-icon' : 'x-hidden';
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

                            var status = record.get('hm_status');
                            return status != 'blocked' ? 'x-grid-icon' : 'x-hidden';
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
                {
                    xtype: 'button',
                    text: '{s name=hm/stock/grid/toolbar/button/sync_stock_listed}{/s}',
                    iconCls: 'sprite-arrow-circle-045-left',
                    handler: function () {
                        me.fireEvent('sync_all')
                    }
                },
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