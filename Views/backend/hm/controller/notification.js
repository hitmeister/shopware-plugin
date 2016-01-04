//{namespace name=backend/hm/controller/notifications}
Ext.define('Shopware.apps.Hm.controller.Notification', {
    extend: 'Ext.app.Controller',

    refs: [
        {
            ref: 'grid',
            selector: 'hm-notifications-grid'
        }
    ],

    init: function () {
        var me = this;

        me.control({
            'hm-notifications-grid': {
                'enable_all': me.onEnableAll,
                'disable_all': me.onDisableAll,
                'enable': me.onEnableItem,
                'disable': me.onDisableItem
            }
        });

        this.callParent(arguments);
    },

    onEnableAll: function() {
        var me = this,
            msg = Ext.MessageBox.wait('{s name=hm/notifications/working}Working...{/s}');

        Ext.Ajax.request({
            url: '{url controller=HmNotifications action=enableAll}',
            callback: function(opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    onDisableAll: function() {
        var me = this,
            msg = Ext.MessageBox.wait('{s name=hm/notifications/working}Working...{/s}');

        Ext.Ajax.request({
            url: '{url controller=HmNotifications action=disableAll}',
            callback: function(opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    onEnableItem: function(record) {
        var me = this,
            id = record.get('id_subscription');

        me.changeStatusById(id, 1);
    },

    onDisableItem: function(record) {
        var me = this,
            id = record.get('id_subscription');

        me.changeStatusById(id, 0);
    },

    changeStatusById: function(id, status) {
        var me = this,
            msg = Ext.MessageBox.wait('{s name=hm/notifications/working}Working...{/s}');

        Ext.Ajax.request({
            url: '{url controller=HmNotifications action=changeStatusById}',
            params: {
                id: id,
                status: status
            },
            callback: function(opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    riseMessage: function(success, response) {
        if (success) {
            var res = Ext.decode(response.responseText);
            if (res.message != undefined && res.message != '') {
                Ext.Msg.alert('{s name=hm/notifications/alert/title}Alert!{/s}', res.message);
            }
        }
    }
});
