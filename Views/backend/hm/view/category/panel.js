//{namespace name=backend/hm/view/category}
Ext.define('Shopware.apps.Hm.view.category.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-category-panel',

    layout: 'fit',

    title: '{s name=hm/category/panel/title}{/s}',

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
                        xtype: 'container',
                        flex: 1,
                        layout: {
                            type: 'hbox',
                            align: 'stretch'
                        },
                        items: [
                            {
                                xtype: 'hm-category-local-tree',
                                flex: 1
                            },
                            {
                                xtype: 'hm-category-hm-tree',
                                flex: 1
                            }
                        ]
                    },
                    {
                        xtype: 'container',
                        html: '{s name=hm/category/panel/description}{/s}'
                    }
                ]
            }
        ];

        me.callParent(arguments);
    }
});
