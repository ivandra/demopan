# Соответствие разделов и вёрстки

Разделы без собственного материала оставлены пустыми и отключены. Назначенный макет — основа для последующего заполнения через компоненты; собственное содержимое в черновиках не создано.

| Раздел | Статус | Макет Netcat | Исходник |
| --- | --- | --- | --- |
| `/` — Жизнь подростка | Готовое наполнение | Главная | index.html |
| `/o-nas/` — О проекте | Готовое наполнение | О проекте | about.html |
| `/blog/` — Блог | Готовое наполнение | Блог и статья | blog.html + article.html |
| `/kontakty/` — Контакты | Готовое наполнение | Контакты | contacts.html |
| `/o-nas/spetsialisty/` — Специалисты | Готовое наполнение | Специалисты и карточка | specialists.html + specialist.html |
| `/o-nas/foto/` — Фотогалерея | Готовое наполнение | Фотогалерея | gallery.html |
| `/o-nas/otzyvy/` — Отзывы | Готовое наполнение | Общий | index.html → .reviews |
| `/o-nas/voprosy-i-otvety/` — Вопросы и ответы | Готовое наполнение | Общий | contacts.html → .faq-block |
| `/o-nas/sertifikaty/` — Дипломы и сертификаты | Готовое наполнение | Общий | index.html → .certificates |
| `/regions/` — Регионы | Служебный | Общий | общий макет + текстовый/списочный компонент |
| `/zayavki/` — Заявки | Служебный | Общий | общий макет + текстовый/списочный компонент |
| `/politika-konfidentsialnosti/` — Политика конфиденциальности | Готовое наполнение | Общий | исходная БД → текстовый компонент |
| `/policy/` — Согласие на обработку персональных данных | Готовое наполнение | Общий | исходная БД → текстовый компонент |
| `/404/` — Страница не найдена | Готовое наполнение | Общий | общий макет + текстовый/списочный компонент |
| `/podrostkam/` — Подросткам | Пустой черновик | Услуга | service.html |
| `/podrostkam/trevoga-i-perezhivaniya/` — Тревога и переживания | Готовое наполнение | Услуга | service.html |
| `/podrostkam/trevoga-i-perezhivaniya/trevozhnye-mysli-i-strahi/` — Тревожные мысли и страхи | Пустой черновик | Услуга | service.html |
| `/podrostkam/trevoga-i-perezhivaniya/stress-i-silnye-perezhivaniya/` — Стресс и сильные переживания | Пустой черновик | Услуга | service.html |
| `/podrostkam/neuverennost-v-sebe/` — Неуверенность в себе | Пустой черновик | Услуга | service.html |
| `/podrostkam/neuverennost-v-sebe/sravnenie-sebya-s-drugimi/` — Сравнение себя с другими | Пустой черновик | Услуга | service.html |
| `/podrostkam/neuverennost-v-sebe/strah-neudachi-i-oshibok/` — Страх неудачи и ошибок | Пустой черновик | Услуга | service.html |
| `/podrostkam/neuverennost-v-sebe/rabota-nad-samootsenkoy/` — Работа над самооценкой | Пустой черновик | Услуга | service.html |
| `/podrostkam/otnosheniya-s-roditelyami/` — Отношения с родителями | Пустой черновик | Услуга | service.html |
| `/podrostkam/otnosheniya-s-roditelyami/konflikty-i-sposoby-ih-resheniya/` — Конфликты и способы их решения | Пустой черновик | Услуга | service.html |
| `/podrostkam/otnosheniya-s-roditelyami/kak-govorit-o-slozhnom/` — Как говорить о сложном | Пустой черновик | Услуга | service.html |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/` — Эмоциональные перепады и трудности с управлением эмоциями | Пустой черновик | Услуга | service.html |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/raspoznavanie-svoih-emotsiy/` — Распознавание своих эмоций | Пустой черновик | Услуга | service.html |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/tehniki-samoregulyatsii/` — Техники саморегуляции | Пустой черновик | Услуга | service.html |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/kogda-stoit-obratitsya-za-podderzhkoy/` — Когда стоит обратиться за поддержкой | Пустой черновик | Услуга | service.html |
| `/podrostkam/ucheba-i-motivatsiya/` — Учёба и мотивация | Пустой черновик | Услуга | service.html |
| `/podrostkam/poisk-sebya/` — Поиск себя | Пустой черновик | Услуга | service.html |
| `/roditelyam/` — Родителям | Пустой черновик | Услуга | service.html |
| `/spetsialistam/` — Специалистам | Пустой черновик | Услуга | service.html |

Подробная статья: `article.html` → `life_articles/RecordTemplateFull`. Подробный специалист: `specialist.html` → `life_specialists/RecordTemplateFull`. Окно консультации: `popup.html` → `life_popup/FormPrefix`.

Пустые разделы не содержат текстового инфоблока с одним заголовком. Их наполнение и публикация выполняются позже через админку. Готовый пример для макета «Услуга» — `/podrostkam/trevoga-i-perezhivaniya/`.
