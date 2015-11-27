//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.controller.Main', {
    extend: 'Ext.app.Controller',

    mainWindow: null,

    init: function () {
        var me = this;

        me.performCheckConfig(function(){
            me.mainWindow = me.getView('main.Window').create();
            me.mainWindow.show();
        });

        this.callParent(arguments);
    },

    /**
     * Checks configuration of Hitmeister.de plugin
     * @param callback
     */
    performCheckConfig: function(callback) {
        Ext.Ajax.request({
            url: '{url controller=Hm action=checkConfig}',
            method: 'POST',
            success: function (operation, opts) {
                var response = Ext.decode(operation.responseText);

                Shopware.app.Application.hitmeisterAvailable = response.success;

                if (!Shopware.app.Application.hitmeisterAvailable) {
                    Shopware.Notification.createStickyGrowlMessage({
                        text: '{s name="hitmeister_not_available"}Hitmeister.de plugin is not available. You have to specify correct client key and secret in the plugin configuration.{/s}',
                        log: false,
                        btnDetail: {
                            text: '{s name="open_basic_settings"}Open basic settings{/s}',
                            callback: function () {
                                Shopware.app.Application.addSubApplication({
                                    name: 'Shopware.apps.Config'
                                });
                            }
                        }
                    });
                } else {
                    callback();
                }
            }
        });
    }
});