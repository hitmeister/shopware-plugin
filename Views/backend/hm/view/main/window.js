//{namespace name=backend/hm/view/main}
Ext.define('Shopware.apps.Hm.view.main.Window', {
    extend: 'Enlight.app.Window',

    layout: 'fit',
    autoShow: false,

    title: '{s name="main_title"}{/s}',

    // Components
    tabPanel: null,

    initComponent: function () {
        var me = this;

        me.items = [
            me.getCreatePanel()
        ];

        me.callParent(arguments);
    },

    getCreatePanel: function () {
        var me = this;

        me.tabPanel = Ext.create('Ext.tab.Panel', {
            region: 'center',
            items: [
                {
                    xtype: 'hm-stock-panel'
                },
                {
                    xtype: 'hm-category-panel'
                },
                {
                    xtype: 'hm-export-panel'
                },
                {
                    xtype: 'hm-notifications-panel'
                }
            ]
        });

        return me.tabPanel;
    }
});