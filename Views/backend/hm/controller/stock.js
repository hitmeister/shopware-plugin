//{namespace name=backend/hm/controller/stock}
Ext.define('Shopware.apps.Hm.controller.Stock', {
    extend: 'Ext.app.Controller',

    refs: [
        {
            ref: 'grid',
            selector: 'hm-stock-grid'
        }
    ],

    init: function () {
        var me = this;

        me.control({
            'hm-stock-grid': {
                'unblock': me.onUnBlock,
                'block': me.onBlock,
                'sync': me.onSync,
                'sync_all': me.onSyncAll
            }
        });

        // Global event
        Shopware.app.Application.on('hm-sync-stock', me.onBatchProcess);

        this.callParent(arguments);
    },

    onUnBlock: function (record) {
        var me = this,
            id = record.get('id');

        me.changeStatusById(id, me.getGrid().StatusNew);
    },

    onBlock: function (record) {
        var me = this,
            id = record.get('id');

        me.changeStatusById(id, me.getGrid().StatusBlocked);
    },

    changeStatusById: function (id, status) {
        var me = this,
            msg = Ext.MessageBox.wait('{s name=hm/stock/working}{/s}'),
            shopId = me.getGrid().getShopFilterValue();

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=changeStatusById}',
            params: {
                id: id,
                status: status,
                shopId: shopId
            },
            callback: function (opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    onSync: function (record) {
        var me = this,
            id = record.get('id'),
            shopId = me.getGrid().getShopFilterValue(),
            msg = Ext.MessageBox.wait('{s name=hm/stock/working}{/s}');

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=syncStockById}',
            params: {
                id: id,
                shopId: shopId
            },
            timeout: 60000,
            callback: function (opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    riseMessage: function (success, response) {
        if (success) {
            var res = Ext.decode(response.responseText);
            if (res.message != undefined && res.message != '') {
                Ext.Msg.alert('{s name=hm/stock/alert/title}{/s}', res.message);
            }
        }
    },

    onSyncAll: function () {
        var me = this,
            msg = Ext.MessageBox.wait('{s name=hm/stock/working}{/s}'),
            shopId = me.getGrid().getShopFilterValue();

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=readyForSync}',
            params: {
                shopId: shopId
            },
            callback: function () {
                msg.hide();
            },
            success: function (response) {
                var res = Ext.decode(response.responseText);
                if (!res.success) {
                    me.riseMessage(true, response);
                    return;
                }

                var process = Ext.create('Shopware.window.Progress', {
                    title: '{s name=hm/stock/sync_all/title}Synchronise stock for ALL articles{/s}',
                    configure: function () {
                        return {
                            infoText: '{s name=hm/stock/sync_all/info_text}{/s}',
                            tasks: [{
                                event: 'hm-sync-stock',
                                text: '{s name=hm/stock/sync_all/article_n_of_m}{/s}',
                                data: res.data
                            }]
                        }
                    }
                });

                process.on('destroy', function () {
                    me.getGrid().getStore().reload();
                });

                process.show();
            }
        });
    },

    onBatchProcess: function (task, record, callback) {
        var componentQuery = Ext.ComponentQuery.query('hm-stock-grid')
            shopId,
            hmStockGrid;

        if(componentQuery[0]){
            hmStockGrid = componentQuery[0];
            shopId = hmStockGrid.getShopFilterValue();
        }

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=syncStockById}',
            params: {
                id: record.id,
                shopId: shopId
            },
            timeout: 60000,
            callback: function (opts, success, response) {
                var res = success ? Ext.decode(response.responseText) : { message: 'System error' };

                callback(success, {
                    wasSuccessful: function () {
                        return success && res.success;
                    },
                    getError: function () {
                        if (res.message != undefined && res.message != '') {
                            return res.message;
                        }
                        return '';
                    },
                    request: {
                        url: 'syncStockById/'+record.id
                    }
                });
            }
        });
    }
});
