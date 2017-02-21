Ext.define('Shopware.apps.Hm', {
    extend: 'Enlight.app.SubApplication',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [
        'Main',
        'Stock',
        'Shippinggroup',
        'Category',
        'Export',
        'Notification'
    ],

    stores: [
        'Stock',
        'Shippinggroup',
        'Shippinggrouptree',
        'Export',
        'Notification'
    ],

    models: [
        'local.Stock',
        'local.Shippinggroup',
        'local.Shippinggrouptree',
        'local.Tree',
        'hm.Tree',
        'hm.Export',
        'hm.Notification',
        'hm.Shippinggroup'
    ],

    views: [
        'main.Window',
        'stock.Grid',
        'stock.Panel',
        'shippinggroup.Grid',
        'shippinggroup.Panel',
        'shippinggroup.Tree',
        'category.Panel',
        'category.local.Tree',
        'category.hm.Tree',
        'category.hm.TreeFilter',
        'export.Grid',
        'export.Panel',
        'notifications.Grid',
        'notifications.Panel'
    ],

    launch: function () {
        var me = this,
            mainController = me.getController('Main');
        return mainController.mainWindow;
    }
});