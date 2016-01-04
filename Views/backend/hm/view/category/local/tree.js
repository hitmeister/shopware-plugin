//{namespace name=backend/hm/view/category}
Ext.define('Shopware.apps.Hm.view.category.local.Tree', {
    extend: 'Ext.tree.Panel',
    alias: 'widget.hm-category-local-tree',
    itemId: 'localTree',

    rootVisible: false,

    selModel: {
        selType: 'rowmodel'
    },

    initComponent: function () {
        var me = this;

        me.store = Ext.create('Ext.data.TreeStore', {
            model: 'Shopware.apps.Hm.model.local.Tree'
        });

        me.columns = [{
            xtype: 'treecolumn',
            text: '{s name=hm/category/local/column/title}{/s}',
            sortable: false,
            menuDisabled: true,
            flex: 1,
            dataIndex: 'description',
            renderer: me.categoryFolderRenderer
        }];

        me.dockedItems = [{
            xtype: 'toolbar',
            ui: 'shopware-ui',
            items: [
                {
                    xtype: 'tbtext',
                    text: '{s name=hm/category/local/toolbar/mapped/title}{/s}'
                },
                {
                    xtype: 'tbtext',
                    text: '{s name=hm/category/local/toolbar/mapped/none}{/s}',
                    itemId: 'mappedTo'
                },
                '->',
                {
                    xtype: 'button',
                    text: '{s name=hm/category/local/toolbar/button/remove}Remove{/s}',
                    iconCls: 'sprite-minus-circle-frame',
                    disabled: true,
                    itemId: 'removeMap',
                    handler: function() {
                        var panel = this.up('panel'),
                            node = panel.getCurrentSelectedItem(),
                            t = panel.down('toolbar > tbtext[itemId=mappedTo]'),
                            b = panel.down('toolbar > button[itemId=removeMap]');

                        if (node) {
                            // Remove data
                            node.unMap();
                            node.save({
                                success: function() {
                                    b.setDisabled(true);
                                    t.setText('{s name=hm/category/local/toolbar/mapped/none}{/s}');
                                    panel.getSelectionModel().deselectAll();
                                    panel.getSelectionModel().select(node)
                                },
                                failure: function() {
                                    Ext.MessageBox.alert('Error','{s name=hm/category/local/alert/error}{/s}');
                                }
                            });
                        } else {
                            Ext.MessageBox.alert('Error','{s name=hm/category/alert/not_selected}{/s}');
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

    categoryFolderRenderer: function (value, metaData, record) {
        var style = '';

        if (!record.get('active')) {
            style = 'opacity: 0.4;';
        }
        if (record.get('hm_category_id')) {
            style += 'font-weight: bold;';
        }

        metaData.tdAttr = 'style="'+style+'"';
        return value;
    },

    itemClicked: function(view, record) {
        var p = view.up('panel'),
            t = p.down('toolbar > tbtext[itemId=mappedTo]'),
            b = p.down('toolbar > button[itemId=removeMap]');

        if (record.get('hm_category_id')) {
            b.setDisabled(false);
            t.setText(record.data.hm_category_title);
        } else {
            b.setDisabled(true);
            t.setText('{s name=hm/category/local/toolbar/mapped/none}{/s}')
        }
    },

    getCurrentSelectedItem: function() {
        var me = this,
            sm = me.getSelectionModel();

        if (sm.hasSelection()) {
            return sm.getSelection()[0];
        }

        return null;
    }
});
