Ext.define('Shopware.apps.Hm.model.hm.Notification', {
    extend: 'Ext.data.Model',
    idProperty:'id_subscription',
    fields: [
        { name: 'id_subscription', type: 'int', useNull: true },
        { name: 'callback_url', type: 'string' },
        { name: 'fallback_email', type: 'string' },
        { name: 'event_name', type: 'string' },
        { name: 'is_active', type: 'bool' }
    ],
    proxy: {
        type: 'ajax',
        api: {
            read: '{url controller=HmNotifications action=getList}'
        },
        reader: {
            type: 'json',
            root: 'data',
            totalProperty: 'total'
        }
    }
});
