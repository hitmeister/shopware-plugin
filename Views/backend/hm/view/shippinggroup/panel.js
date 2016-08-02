//{namespace name=backend/hm/view/shippinggroup}
Ext.define('Shopware.apps.Hm.view.shippinggroup.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-shippinggroup-panel',

    layout: 'fit',

    title: '{s name=hm/shippinggroup/panel/title}{/s}',

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
                                xtype: 'hm-shippinggroup-local-tree',
                                width: 210
                            },
                            {
                                xtype: 'hm-shippinggroup-grid',
                                flex: 1
                            }
                        ]
                    },
                    {
                        xtype: 'container',
                        html: '{s name=hm/shippinggroup/panel/description}{/s}'
                    }
                ]
            }
        ];

        // update tree
        me.on('render', function(panel, value){
            var subApps = Shopware.app.Application.subApplications,
                hmApp = subApps.findBy(function(item) {
                    if(item.$className == 'Shopware.apps.Hm') {
                        return true;
                    }
                });

            hmApp.getController('Shippinggroup').getTree().getStore().load();
        });

        me.callParent(arguments);
    }
});
