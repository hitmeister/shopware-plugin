//{namespace name=backend/hm/controller/category}
Ext.define('Shopware.apps.Hm.controller.Category', {
    extend: 'Ext.app.Controller',

    refs: [
        { ref: 'localTree', selector: 'hm-category-local-tree' }
    ],

    init: function () {
        var me = this;

        me.control({
            'hm-category-hm-tree': {
                'addMap': me.onAddMap
            }
        });

        this.callParent(arguments);
    },

    onAddMap: function(data) {
        var me = this,
            panel = me.getLocalTree(),
            node = panel.getCurrentSelectedItem(),
            t = panel.down('toolbar > tbtext[itemId=mappedTo]'),
            b = panel.down('toolbar > button[itemId=removeMap]');

        if (!node) {
            Ext.MessageBox.alert('Error','{s name=hm/category/alert/not_selected}{/s}');
            return;
        }

        node.set('hm_category_id', data.id);
        node.set('hm_category_title', data.title);

        node.save({
            success: function() {
                b.setDisabled(false);
                t.setText(data.title);
                panel.getSelectionModel().deselectAll();
                panel.getSelectionModel().select(node)
            },
            failure: function() {
                Ext.MessageBox.alert('Error','{s name=hm/category/alert/add_map}{/s}');
            }
        });
    }
});
