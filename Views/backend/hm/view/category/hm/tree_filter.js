//{namespace name=backend/hm/view/category}
Ext.define('Shopware.apps.Hm.view.category.hm.TreeFilter', {
    extend: 'Ext.AbstractPlugin',
    alias: 'plugin.hm-category-hm-tree-filter',

    tree: null,

    init: function (tree) {
        var me = this;
        me.tree = tree;
        tree.filter = Ext.Function.bind(me.filter, me);
        tree.clearFilter = Ext.Function.bind(me.clearFilter, me);
    },

    filter: function (value, property) {
        var me = this,
            tree = me.tree,
            view = tree.getView(),
            root = tree.getRootNode(),
            nodesAndParents = [],
            viewNode;

        property = property || 'text';

        me.clearFilter();

        // Reset
        if (Ext.isEmpty(value)) {
            tree.collapseAll();
            root.expandChildren();
            return;
        }

        // Find the nodes which match the search term, expand them.
        root.cascadeBy(function(node){
            if (node && node.get(property)) {
                if (node.get(property).toString().toLowerCase().indexOf(value.toLowerCase()) > -1) {
                    tree.expandPath(node.getPath());

                    while(node.parentNode) {
                        nodesAndParents.push(node.id);
                        node = node.parentNode;
                    }
                }
            }
        });

        // Hide all of the nodes which aren't in nodesAndParents
        root.cascadeBy(function(node){
            viewNode = Ext.fly(view.getNode(node));
            if(viewNode && !Ext.Array.contains(nodesAndParents, node.id)) {
                viewNode.setDisplayed(false);
            }
        });
    },

    clearFilter: function () {
        var me = this,
            tree = me.tree,
            view = tree.getView(),
            root = tree.getRootNode(),
            viewNode;

        root.cascadeBy(function (node) {
            viewNode = Ext.fly(view.getNode(node));
            if (viewNode) {
                viewNode.show();
            }
        });
    }
});
