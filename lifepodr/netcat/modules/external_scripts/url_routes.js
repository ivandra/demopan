urlDispatcher.addRoutes({
    "module.external_scripts": NETCAT_PATH + "modules/external_scripts/admin.php?site_id=%1",
    "module.external_scripts.add": NETCAT_PATH + "modules/external_scripts/admin.php?phase=add&site_id=%1",
    "module.external_scripts.add.type": NETCAT_PATH + "modules/external_scripts/admin.php?phase=add&site_id=%1&type_id=%2",
    "module.external_scripts.edit": NETCAT_PATH + "modules/external_scripts/admin.php?phase=edit&site_id=%1&snippet_id=%2",

    // Реестр доменных провайдеров (карты/видео) для авто-блокировки до
    // cookie-согласия — общий для всех сайтов, без site_id.
    "module.external_scripts.providers": NETCAT_PATH + "modules/external_scripts/admin.php?phase=providers",
    "module.external_scripts.providers.add": NETCAT_PATH + "modules/external_scripts/admin.php?phase=provider_add",
    "module.external_scripts.providers.edit": NETCAT_PATH + "modules/external_scripts/admin.php?phase=provider_edit&provider_id=%1",

    1: ""
});
