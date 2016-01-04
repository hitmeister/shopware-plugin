//{namespace name=backend/hm/view/category}
Ext.define('Shopware.apps.Hm.view.category.hm.Tree', {
    extend: 'Ext.tree.Panel',
    alias: 'widget.hm-category-hm-tree',
    itemId: 'hmTree',

    rootVisible: false,

    selModel: {
        selType: 'rowmodel'
    },

    initComponent: function () {
        var me = this;

        me.store = Ext.create('Ext.data.TreeStore', {
            model: 'Shopware.apps.Hm.model.hm.Tree',
            root: {
                id: 0
            }
        });

        me.columns = [{
            xtype: 'treecolumn',
            text: '{s name=hm/category/hm/column/title}{/s}',
            sortable: false,
            menuDisabled: true,
            flex: 1,
            dataIndex: 'title'
        }];

        me.plugins = [{
            ptype: 'hm-category-hm-tree-filter',
            allowParentFolders: true
        }];

        me.addEvents('addMap');

        me.dockedItems = [{
            xtype: 'toolbar',
            ui: 'shopware-ui',
            items: [
                {
                    xtype: 'button',
                    text: '{s name=hm/category/hm/toolbar/button/map_selected}{/s}',
                    iconCls: 'sprite-plus-circle-frame',
                    disabled: true,
                    itemId: 'addMap',
                    handler: function() {
                        var panel = this.up('panel'),
                            sm = panel.getSelectionModel(),
                            node;

                        if (sm.hasSelection()) {
                            node = sm.getSelection()[0];
                            me.fireEvent('addMap', node.data);
                        } else {
                            Ext.MessageBox.alert('Error', '{s name=hm/category/alert/not_selected}{/s}');
                        }
                    }
                },
                '->',
                {
                    xtype: 'textfield',
                    emptyText: '{s name=hm/category/toolbar/search_empty}Search...{/s}',
                    cls: 'searchfield',
                    enableKeyEvents: true,
                    checkChangeBuffer: 200,
                    listeners: {
                        'change': function (field, value) {
                            var searchString = Ext.String.trim(value);
                            me.filter(searchString, 'title');
                        }
                    }
                }
            ]
        }];

        me.listeners = {
            'itemclick': me.itemClicked
        };

        me.callParent(arguments);
    },

    itemClicked: function(view) {
        var p = view.up('panel'),
            b = p.down('toolbar > button[itemId=addMap]');

        b.setDisabled(false);
    }
});
