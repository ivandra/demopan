// $Id: url_routes.js 6207 2012-02-10 10:14:50Z denis $

urlDispatcher.addRoutes( {
    'module.logging': NETCAT_PATH + 'modules/logging/admin.php',
    // второй ряд вкладок: журнал согласий 152-ФЗ (задаётся в admin.php через $UI_CONFIG->tabs)
    'module.logging.consent': NETCAT_PATH + 'modules/logging/admin.php?phase=10',
    // выбор в окне cookie-согласия (docs/proposals/cookie-consent-log.md)
    'module.logging.cookie': NETCAT_PATH + 'modules/logging/admin.php?phase=12'
} );
