Ext.define('Shopware.apps.Hm', {
    extend: 'Enlight.app.SubApplication',

    loadPath: '{url action=load}',
    bulkLoad: true,

    controllers: [
        'Main',
        'Stock',
        'Category',
        'Export',
        'Notification'
    ],

    stores: [
        'Stock',
        'Export',
        'Notification'
    ],

    models: [
        'local.Stock',
        'local.Tree',
        'hm.Tree',
        'hm.Export',
        'hm.Notification'
    ],

    views: [
        'main.Window',
        'stock.Grid',
        'stock.Panel',
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
        console.log('launch');
        return mainController.mainWindow;
    }
});