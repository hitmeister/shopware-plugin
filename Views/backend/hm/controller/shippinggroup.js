//{namespace name=backend/hm/controller/shippinggroup}
Ext.define('Shopware.apps.Hm.controller.Shippinggroup', {
    extend: 'Ext.app.Controller',

    refs: [
        { ref: 'grid', selector: 'hm-shippinggroup-grid' },
        { ref: 'tree', selector: 'hm-shippinggroup-local-tree' }
    ],

    init: function () {
        var me = this;

        me.control({
            'hm-shippinggroup-grid': {
                'onSyncSelected': me.onSyncSelected
            }
        });

        // Global event
        Shopware.app.Application.on('hm-sync-shippinggroup', me.onBatchProcess);

        this.callParent(arguments);
    },

    onSyncSelected: function (records, shippinggroup, shopId) {
        var me = this,
            process = Ext.create('Shopware.window.Progress', {
            title: '{s name=hm/shippinggroup/sync_selected/title}Synchronise stock for ALL articles{/s}',
            configure: function () {
                return {
                    infoText: '{s name=hm/shippinggroup/sync_selected/info_text}{/s}',
                    tasks: [{
                        event: 'hm-sync-shippinggroup',
                        text: '{s name=hm/shippinggroup/sync_selected/article_n_of_m}{/s}',
                        data: records,
                        shippinggroup: shippinggroup,
                        shopId: shopId
                    }]
                }
            }
        });

        process.on('destroy', function () {
            me.getGrid().getStore().reload();
        });

        process.show();
    },

    onBatchProcess: function (task, record, callback) {
        Ext.Ajax.request({
            url: '{url controller=HmArticles action=changeShippinggroupById}',
            params: {
                id: record.get('id'),
                shippinggroup: task.shippinggroup,
                shopId: task.shopId
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
                        url: 'changeShippinggroupById/'+record.get('id')
                    }
                });
            }
        });
    }
});
