//{namespace name=backend/hm/translation}
Ext.define('Shopware.apps.Hm.view.category.local.Tree', {
    extend: 'Ext.tree.Panel',
    alias: 'widget.hm-category-local-tree',
    itemId: 'localTree',

    rootVisible: false,

    selModel: {
        selType: 'rowmodel'
    },

    initComponent: function () {
        var me = this, loading = me.setLoading(true);

        me.store = Ext.create('Ext.data.TreeStore', {
            model: 'Shopware.apps.Hm.model.local.Tree'
        });

        me.columns = [{
            xtype: 'treecolumn',
            text: '{s name=view/category/local/column_title}Local categories{/s}',
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
                    text: '{s name=view/category/local/mapped_title}Mapped:{/s}'
                },
                {
                    xtype: 'tbtext',
                    text: '{s name=view/category/local/mapped_to_none}none{/s}',
                    itemId: 'mappedTo'
                },
                '->',
                {
                    xtype: 'button',
                    text: '{s name=view/category/local/button_remove}Remove{/s}',
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
                                    t.setText('{s name=view/category/local/mapped_to_none}none{/s}');
                                    panel.getSelectionModel().deselectAll();
                                    panel.getSelectionModel().select(node)
                                },
                                failure: function() {
                                    Ext.MessageBox.alert('Error','{s name=view/category/local/alert_remove_map}Error on remove map operation!{/s}');
                                }
                            });
                        } else {
                            Ext.MessageBox.alert('Error','{s name=view/category/local/alert_not_selected}Please, select item first!{/s}');
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
            t.setText('{s name=view/category/local/mapped_to_none}none{/s}')
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
