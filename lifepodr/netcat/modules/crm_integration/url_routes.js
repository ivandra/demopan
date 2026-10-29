urlDispatcher.addRoutes({
    'module.crm_integration': NETCAT_PATH + 'modules/crm_integration/admin.php'
})
.addPrefixRouter('module.crm_integration.', function(path, params) {
    var parts = path.substr(23).split('(');
    var view = parts[0];
    var url = NETCAT_PATH + "modules/crm_integration/admin.php?phase=" + view;
    if (params) {
        url += "&" + params;
    }
    mainView.loadIframe(url);
});
