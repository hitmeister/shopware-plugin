//{namespace name=backend/hm/controller/export}
Ext.define('Shopware.apps.Hm.controller.Export', {
    extend: 'Ext.app.Controller',

    refs: [
        {
            ref: 'grid',
            selector: 'hm-export-grid'
        }
    ],

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
            msg = Ext.MessageBox.wait('{s name=hm/export/working}{/s}');

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
        if (success) {
            var res = Ext.decode(response.responseText);
            if (res.message != undefined && res.message != '') {
                Ext.Msg.alert('{s name=hm/export/alert/title}Alert!{/s}', res.message);
            }
        }
    }
});
