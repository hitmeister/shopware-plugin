//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.view.stock.Panel', {
    extend: 'Ext.panel.Panel',
    alias: 'widget.hm-stock-panel',

    layout: 'fit',

    title: '{s name=view/stock/panel/title_synchronization}Stock synchronization{/s}',

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
                        html: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer convallis elit at ligula vehicula, eu tempor arcu tincidunt. Sed nec pretium massa, et pharetra purus. Nunc rhoncus porta est sit amet accumsan. Cras quam metus, interdum vel ornare at, cursus ut risus. Etiam neque neque, dictum vel elit vitae, sagittis imperdiet purus. Suspendisse nec risus eget ante facilisis commodo. Etiam consectetur luctus rutrum. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris ultricies elit lacus, non vestibulum felis ullamcorper id. Quisque mi dolor, mollis sit amet blandit vel, eleifend at ante.'
                    }
                ]
            }
        ];

        me.callParent(arguments);
    }
});
