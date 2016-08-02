//{namespace name=backend/hm/view/shippinggroup}
Ext.define('Shopware.apps.Hm.view.shippinggroup.Tree', {
    extend: 'Ext.tree.Panel',
    alias: 'widget.hm-shippinggroup-local-tree',
    itemId: 'localShippinggroupTree',

    rootVisible: false,

    selModel: {
        selType: 'rowmodel'
    },

    initComponent: function () {
        var me = this;

        me.store = Ext.create('Shopware.apps.Hm.store.Shippinggrouptree');

        me.columns = [{
            xtype: 'treecolumn',
            sortable: false,
            menuDisabled: true,
            flex: 1,
            dataIndex: 'description',
            renderer: me.categoryFolderRenderer
        }];

        me.listeners = {
            'itemclick': me.itemClicked
        };

        me.on("beforeload", function( treeStore, operation, eOpts){

            var mainGrid = me.getMainGrid();
            if(mainGrid){
                var shopId = mainGrid.getShopFilterValue(),
                    shopData = mainGrid.ShopFilter.getStore().findRecord('id',shopId),
                    nodeId = shopData.get('category_id'),
                    reload = mainGrid.reloadTree;

                if(reload){
                    operation.params.node = nodeId;
                    operation.params.id = nodeId;
                    mainGrid.reloadTree = false;
                }

            }

        });

        me.callParent(arguments);
    },

    categoryFolderRenderer: function (value, metaData, record) {
        var style = '';

        if (!record.get('active')) {
            style = 'opacity: 0.4;';
        }

        metaData.tdAttr = 'style="'+style+'"';
        return value;
    },

    itemClicked: function(view, record) {
        var me = this;
        me.getMainGrid().reloadGridStore([
            { property: 'categoryId', value: record.internalId },
            { property: 'shopId', value: me.getMainGrid().getShopFilterValue() }
        ]);
    },

    getCurrentSelectedItem: function() {
        var me = this,
            sm = me.getSelectionModel();

        if (sm.hasSelection()) {
            return sm.getSelection()[0];
        }

        return null;
    },

    getMainGrid: function() {
        var subApps = Shopware.app.Application.subApplications,
            hmApp = subApps.findBy(function(item) {
                if(item.$className == 'Shopware.apps.Hm') {
                    return true;
                }
            });

        return hmApp.getController('Shippinggroup').getGrid();
    }
});
