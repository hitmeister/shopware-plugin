//{namespace name=backend/hm/translation}
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
            text: '{s name=view/category/hm/column_title}Hitmeister categories{/s}',
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
                    text: '{s name=view/category/hm/button_map_selected}Map selected{/s}',
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
                            Ext.MessageBox.alert('Error', '{s name=view/category/hm/alert_not_selected}Please, select item first!{/s}');
                        }
                    }
                },
                '->',
                {
                    xtype: 'textfield',
                    emptyText: '{s name=view/category/hm/search_empty}Search...{/s}',
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

    itemClicked: function(view, record) {
        var p = view.up('panel'),
            b = p.down('toolbar > button[itemId=addMap]');

        b.setDisabled(false);
    }
});
