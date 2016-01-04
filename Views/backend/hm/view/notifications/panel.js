//{namespace name=backend/hm/view/notifications}
Ext.define('Shopware.apps.Hm.view.notifications.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-notifications-panel',

    layout: 'fit',

    title: '{s name=hm/notifications/panel/title}{/s}',

    treeLocal: null,
    treeHm: null,

    initComponent: function () {
        var me = this;

        me.items = [
            {
                xtype: 'container',
                flex: 1,
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                },
                items: [
                    {
                        xtype: 'hm-notifications-grid',
                        flex: 1
                    },
                    {
                        xtype: 'container',
                        html: '{s name=hm/notifications/panel/description}{/s}'
                    }
                ]
            }
        ];

        me.callParent(arguments);
    }
});
