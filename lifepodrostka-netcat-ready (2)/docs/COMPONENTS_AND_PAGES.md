# Компоненты и поля

| Компонент | Ключ | Поля |
|---|---|---|
| Карточки и преимущества | `zhp_cards` | `name` — Название, `text` — Описание, `img` — Изображение или иконка, `img_mobile` — Мобильное изображение, `link` — Ссылка |
| Баннер страницы | `zhp_banner` | `name` — Заголовок, `label` — Надзаголовок, `text` — Описание, `img` — Основное изображение, `button` — Текст первой кнопки, `link` — Ссылка первой кнопки, `button2` — Текст второй кнопки, `link2` — Ссылка второй кнопки |
| Основатель проекта | `zhp_founder` | `fio` — ФИО, `position` — Должность / подпись, `img` — Фотография, `bio` — Описание, `achievements` — Достижения |
| Видео | `zhp_video` | `name` — Заголовок, `text` — Описание, `link` — Ссылка на видео |
| Программа | `zhp_program` | `name` — Заголовок, `label` — Надзаголовок, `text` — Вступление, `directions_title` — Заголовок направлений, `directions` — Направления программы, `support_title` — Заголовок сопровождения, `support` — Содержание сопровождения |
| Содержание услуги | `zhp_program_service` | `name` — Заголовок, `label` — Надзаголовок, `text` — Вступление, `directions_title` — Заголовок направлений, `directions` — Направления программы, `support_title` — Заголовок сопровождения, `support` — Содержание сопровождения |
| Группа для родителей | `zhp_parent_group` | `name` — Заголовок, `text` — Описание |
| Как связаться | `zhp_first_step` | `name` — Заголовок, `text` — Описание |
| Социальные сети | `zhp_socials` | `name` — Заголовок, `text` — Описание |
| Специалисты | `zhp_specialists` | `fio` — ФИО, `position` — Должность / специализация, `img` — Фотография, `intro` — Краткое описание, `bio` — О специалисте, `personalquote` — Цитата, `stats` — Город, формат и опыт, `details` — Сведения о приёме, `methods` — Методы и подходы, `education` — Образование, `diploms` — Документы и сертификаты, `category` — Категории фильтра (через пробел) |
| Статьи | `zhp_articles` | `name` — Заголовок, `intro` — Анонс, `text` — Текст статьи, `img` — Изображение, `date` — Дата публикации, `readtime` — Время чтения, `category` — Категория (teens / parents / specialists) |
| Отзывы | `zhp_reviews` | `name` — Имя, `role` — Подпись, `text` — Текст отзыва, `img` — Аватар |
| Вопросы и ответы | `zhp_faq` | `question` — Вопрос, `answer` — Ответ |
| Дипломы и сертификаты | `zhp_certificates` | `name` — Название документа, `img` — Изображение документа, `file` — Файл для скачивания |
| Фотогалерея | `zhp_gallery` | `name` — Подпись фотографии, `img` — Фотография, `category` — Категория (classes / events / team / more) |
| Текстовый блок | `zhp_text` | `name` — Заголовок, `text` — Текст |
| Контакты | `zhp_contacts` | `name` — Заголовок, `text` — Вступление |
| Калькулятор стоимости | `zhp_calculator` | `name` — Заголовок, `text` — Описание, `days_min` — Минимум суток, `days_max` — Максимум суток, `days` — Суток по умолчанию, `rate_min` — Минимальная ставка, `rate_max` — Максимальная ставка, `rate` — Ставка по умолчанию |
| Вопросы квиза | `zhp_quiz` | `question` — Вопрос, `answers` — Варианты ответа (по одному на строку) |
| Подписка на рассылку | `zhp_subscription` | `name` — Заголовок, `text` — Описание |
| Регионы | `zhp_regions` | `main_domain` — Домен без протокола и пути, `city` — Город, `inCity` — Город: где? (в Москве), `cityRP` — Город: откуда? (Москвы), `cityDP` — Город: кому? (Москве), `address` — Адрес, `phone` — Телефон, `email` — Email для посетителей, `regim` — Время работы, `max` — MAX, `telegram` — Telegram, `whatsapp` — WhatsApp, `vk` — VK, `youtube` — YouTube, `instagram` — Instagram, `mapcode` — Код карты, `email_to` — Получатель заявок, `email_from` — Отправитель писем, `email_enabled` — Отправлять заявки на email, `demo` — Демонстрационные контакты |
| Заявки | `zhp_leads` | `name` — Имя, `phone` — Телефон, `email` — Email, `contact` — Контакт для связи, `message` — Сообщение, `form_type` — Источник формы, `page_url` — Страница, `domain` — Домен, `utm` — UTM-метки, `answers` — Ответы квиза, `request_key` — Ключ отправки, `delivery` — Статус письма, `consent` — Согласие на обработку данных |
| Как нас найти | `zhp_location` | `name` — Заголовок, `text` — Описание |

## Штатные шаблоны компонентов

| Шаблон | Базовый компонент |
|---|---|
| Для кого (`zhp_cards_whom`) | `zhp_cards` |
| Этапы (`zhp_cards_steps`) | `zhp_cards` |
| Что предлагает проект (`zhp_cards_offer`) | `zhp_cards` |
| Ценности проекта (`zhp_cards_values`) | `zhp_cards` |
| Подросткам и родителям (`zhp_cards_peoples`) | `zhp_cards` |
| Как проходит работа (`zhp_cards_way`) | `zhp_cards` |
| Причины трудностей (`zhp_cards_reasons`) | `zhp_cards` |
| Как мы помогаем (`zhp_cards_help`) | `zhp_cards` |
| Главный баннер (`zhp_banner_home`) | `zhp_banner` |
| О проекте — первый экран (`zhp_banner_about`) | `zhp_banner` |
| О проекте — вступление (`zhp_banner_about_text`) | `zhp_banner` |
| Заголовок списка специалистов (`zhp_banner_specialists`) | `zhp_banner` |
| Заголовок галереи (`zhp_banner_gallery`) | `zhp_banner` |
| Баннер услуги (`zhp_banner_service`) | `zhp_banner` |
| Специалисты — слайдер (`zhp_specialists_home`) | `zhp_specialists` |
| Специалисты — команда (`zhp_specialists_team`) | `zhp_specialists` |
| Статьи — главная (`zhp_articles_home`) | `zhp_articles` |
| Рекомендуемая статья (`zhp_articles_featured`) | `zhp_articles` |
| Отзывы — другие страницы (`zhp_reviews_home`) | `zhp_reviews` |
| Вопросы и ответы — другие страницы (`zhp_faq_contacts`) | `zhp_faq` |
| Дипломы и сертификаты — другие страницы (`zhp_certificates_home`) | `zhp_certificates` |

## Порядок инфоблоков

| URL | Инфоблоки |
|---|---|
| `/` | Верхний баннер, Основатель проекта, Видео о проекте, Программа, Группа для родителей, Калькулятор стоимости, Вопросы квиза, Как связаться, Для кого, Этапы, Что предлагает проект, Специалисты, Отзывы, Дипломы и сертификаты, Статьи, Социальные сети, Подписка на рассылку |
| `/o-nas/` | О проекте — первый экран, О проекте — вступление, Ценности проекта, Подросткам и родителям, Как проходит работа, Команда |
| `/blog/` | Рекомендуемая статья, Статьи |
| `/kontakty/` | Контакты, Вопросы и ответы, Как нас найти |
| `/o-nas/spetsialisty/` | Заголовок страницы, Специалисты |
| `/o-nas/foto/` | Заголовок галереи, Фотографии |
| `/o-nas/otzyvy/` | Отзывы |
| `/o-nas/voprosy-i-otvety/` | Вопросы и ответы |
| `/o-nas/sertifikaty/` | Дипломы и сертификаты |
| `/regions/` |  |
| `/zayavki/` |  |
| `/nastroiki-proekta/` |  |
| `/politika-konfidentsialnosti/` | Текст страницы |
| `/policy/` | Текст страницы |
| `/404/` | Текст страницы |
| `/podrostkam/` | Текст страницы |
| `/podrostkam/trevoga-i-perezhivaniya/` | Баннер услуги, Содержание услуги, Причины трудностей, Как мы помогаем, Как проходит работа, Специалисты |
| `/podrostkam/trevoga-i-perezhivaniya/trevozhnye-mysli-i-strahi/` | Текст страницы |
| `/podrostkam/trevoga-i-perezhivaniya/stress-i-silnye-perezhivaniya/` | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/` | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/sravnenie-sebya-s-drugimi/` | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/strah-neudachi-i-oshibok/` | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/rabota-nad-samootsenkoy/` | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/` | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/konflikty-i-sposoby-ih-resheniya/` | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/kak-govorit-o-slozhnom/` | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/` | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/raspoznavanie-svoih-emotsiy/` | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/tehniki-samoregulyatsii/` | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/kogda-stoit-obratitsya-za-podderzhkoy/` | Текст страницы |
| `/podrostkam/ucheba-i-motivatsiya/` | Текст страницы |
| `/podrostkam/poisk-sebya/` | Текст страницы |
| `/roditelyam/` | Текст страницы |
| `/spetsialistam/` | Текст страницы |
| `/materialy/` | Текст страницы |
| `/testy/` | Текст страницы |
| `/vebinary/` | Текст страницы |
