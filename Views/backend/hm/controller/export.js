//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.controller.Export', {
    extend: 'Ext.app.Controller',

    refs: [
        {
            ref: 'grid',
            selector: 'hm-export-grid'
        }
    ],

    waitingText: '{s name=waiting_working}Working...{/s}',
    alertText: '{s name=alert_title}Alert!{/s}',

    init: function () {
        var me = this;

        me.control({
            'hm-export-grid': {
                'export': me.onExport
            }
        });

        this.callParent(arguments);
    },

    onExport: function() {
        var me = this,
            msg = Ext.MessageBox.wait(me.waitingText);

        Ext.Ajax.request({
            url: '{url controller=HmExports action=export}',
            callback: function(opts, success, response) {
                msg.hide();
                me.getGrid().getStore().reload();
                me.riseMessage(success, response);
            }
        });
    },

    riseMessage: function(success, response) {
        var me = this;

        if (success) {
            var res = Ext.decode(response.responseText);
            if (res.message != undefined && res.message != '') {
                Ext.Msg.alert(me.alertText, res.message);
            }
        }
    }
});
