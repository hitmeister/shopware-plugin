//{namespace name=backend/hm/view/stock}
Ext.define('Shopware.apps.Hm.view.stock.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-stock-panel',

    layout: 'fit',

    title: '{s name=hm/stock/panel/title}{/s}',

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
                        xtype: 'hm-stock-grid',
                        flex: 1
                    },
                    {
                        xtype: 'container',
                        html: '{s name=hm/stock/panel/description}{/s}'
                    }
                ]
            }
        ];

        me.callParent(arguments);
    }
});
