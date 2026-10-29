ПАК ФАВИКОНА МедЭльта (новый знак — сердце с пальмой)

КУДА ЗАЛИТЬ (FileZilla):
  Всю папку favicon/ — в КОРЕНЬ сайта, т.е. чтобы было  /favicon/favicon.ico  и т.д.
  (внутренние пути в манифесте и сниппете — корневые: /favicon/...)

В <head> (Template.html и Header.html):
  Вставьте блок из файла  ВСТАВИТЬ-В-HEAD.html,
  удалив старый блок фавикона (старые <link rel="icon" ...>, apple-touch, manifest, mask-icon, msapplication).

После заливки:
  Обновите страницу с Ctrl+F5. Вкладка/иконка браузера кэшируются надолго —
  иногда новый фавикон появляется не сразу (можно проверить в режиме инкогнито).

Состав: favicon.ico (16/32/48), favicon.svg, favicon-16/32/48/96, apple-touch-icon (180),
android-chrome 192/512 (any) + maskable 192/512, mstile-150, safari-pinned-tab.svg,
site.webmanifest, browserconfig.xml.
