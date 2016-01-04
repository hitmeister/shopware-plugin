//{namespace name=backend/hm/view/export}
Ext.define('Shopware.apps.Hm.view.export.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-export-panel',

    layout: 'fit',

    title: '{s name=hm/export/panel/title}{/s}',

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
                        xtype: 'hm-export-grid',
                        flex: 1
                    },
                    {
                        xtype: 'container',
                        html: '{s name=hm/export/panel/description}{/s}'
                    }
                ]
            }
        ];

        me.callParent(arguments);
    }
});
