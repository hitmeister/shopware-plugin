//{namespace name=backend/hm/translation}
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
                text: '{s name=view/stock/grid/column/article_num_title}Article number{/s}',
                dataIndex: 'ordernumber',
                menuDisabled: true,
                flex: 1
            },
            {
                text: '{s name=view/stock/grid/column/ean_title}Ean{/s}',
                dataIndex: 'ean',
                menuDisabled: true,
                width: 120,
                renderer: function (value, metaData) {
                    if ('' == Ext.String.trim(value)) {
                        metaData.tdCls = 'grid-cell-empty-ean';
                        return '{s name=view/stock/grid/column/ean/required}** Empty but required **{/s}';
                    }
                    return value;
                }
            },
            {
                text: '{s name=view/stock/grid/column/name_variant_title}Name / Variant{/s}',
                dataIndex: 'name',
                menuDisabled: true,
                flex: 2
            },
            {
                text: '{s name=view/stock/grid/column/in_stock_title}Stock{/s}',
                dataIndex: 'instock',
                menuDisabled: true,
                width: 40
            },
            {
                xtype: 'datecolumn',
                text: '{s name=view/stock/grid/column/hm_last_access_date_title}Last update date{/s}',
                format: 'Y-m-d H:i:s',
                dataIndex: 'hm_last_access_date',
                menuDisabled: true,
                width: 120
            },
            {
                text: '{s name=view/stock/grid/column/hm_status_title}Status{/s}',
                dataIndex: 'hm_status',
                menuDisabled: true,
                width: 110,
                renderer: function (value, metaData, record) {
                    var status = record.get('hm_status');
                    switch (status) {
                        case me.StatusNew:
                            return '{s name=view/stock/grid/column/status/new_value}New{/s}';
                        case me.StatusBlocked:
                            return '{s name=view/stock/grid/column/status/blocked_value}Not for sale{/s}';
                        case me.StatusNotFound:
                            return '{s name=view/stock/grid/column/status/not_found_value}Not found on HM{/s}';
                        case me.StatusSynchronizing:
                            return '{s name=view/stock/grid/column/status/synchronizing_value}Synchronizing{/s}';
                    }
                }
            },
            {
                xtype: 'actioncolumn',
                text: '{s name=view/stock/grid/column/options_title}Options{/s}',
                menuDisabled: true,
                width: 60,
                items: [
                    {
                        iconCls: 'sprite-plus-circle-frame',
                        tooltip: '{s name=view/stock/grid/column/options/list_title}Unblock synchronization with Hitmeister.de{/s}',
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
                        tooltip: '{s name=view/stock/grid/column/options/remove_title}Block synchronization with Hitmeister.de{/s}',
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
                        tooltip: '{s name=view/stock/grid/column/options/sync_title}Sync stock{/s}',
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
                    text: '{s name=view/stock/grid/toolbar/button_sync_stock_listed}Sync stock for ALL articles{/s}',
                    iconCls: 'sprite-arrow-circle-045-left',
                    handler: function () {
                        me.fireEvent('sync_all')
                    }
                },
                '->',
                {
                    xtype: 'textfield',
                    emptyText: '{s name=view/stock/grid/toolbar/search_empty}Search...{/s}',
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