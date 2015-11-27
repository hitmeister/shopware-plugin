//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.controller.Stock', {
    extend: 'Ext.app.Controller',

    refs: [
        {
            ref: 'grid',
            selector: 'hm-stock-grid'
        }
    ],

    waitingText: '{s name=waiting_working}Working...{/s}',
    alertText: '{s name=alert_title}Alert!{/s}',
    infoText: '{s name=controller/stock/sync_all/info/text}You can use the <b><i>Cancel process</i></b> button the cancel the process. Depending on the amount of the data set, this process might take a while.{/s}',

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
            msg = Ext.MessageBox.wait(me.waitingText);

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=changeStatusById}',
            params: {
                id: id,
                status: status
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
            msg = Ext.MessageBox.wait(me.waitingText);

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=syncStockById}',
            params: {
                id: id
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
        var me = this;

        if (success) {
            var res = Ext.decode(response.responseText);
            if (res.message != undefined && res.message != '') {
                Ext.Msg.alert(me.alertText, res.message);
            }
        }
    },

    onSyncAll: function () {
        var me = this,
            msg = Ext.MessageBox.wait(me.waitingText);

        Ext.Ajax.request({
            url: '{url controller=HmArticles action=readyForSync}',
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
                    title: '{s name=controller/stock/sync_all/title}Synchronise stock for ALL articles{/s}',
                    configure: function () {
                        return {
                            infoText: me.infoText,
                            tasks: [{
                                event: 'hm-sync-stock',
                                text: '{s name=controller/stock/sync_all/article_n_of_m}Article [0] of [1]{/s}',
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
        Ext.Ajax.request({
            url: '{url controller=HmArticles action=syncStockById}',
            params: {
                id: record.id
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
