# Компоненты, поля и страницы v4

22 базовых компонента, 24 шаблона вывода, 74 поля вместе со служебными полями заявок. Регионы — существующий компонент №223 / инфоблок №57.

| Компонент | Ключ | Поля |
| --- | --- | --- |
| Карточки и преимущества | `zhp_cards` | `name` — Название; `text` — Описание; `img` — Изображение или иконка; `img_mobile` — Мобильное изображение; `link` — Ссылка |
| Баннер страницы | `zhp_banner` | `name` — Заголовок; `text` — Описание; `img` — Основное изображение |
| Основатель проекта | `zhp_founder` | `fio` — ФИО; `img` — Фотография; `bio` — Описание; `achievements` — Достижения |
| ПОЗНАКОМЬТЕСЬ С НАМИ БЛИЖЕ | `zhp_video` | `name` — Заголовок; `text` — Описание; `link` — Ссылка на видео; `img` — Обложка видео |
| Программа | `zhp_program` | `name` — Заголовок; `text` — Вступление; `directions` — Направления программы; `support` — Содержание сопровождения |
| Содержание услуги | `zhp_program_service` | `name` — Заголовок; `text` — Вступление; `directions` — Направления программы; `support` — Содержание сопровождения |
| Группа для родителей | `zhp_parent_group` | Нет: общий блок целиком в префиксе |
| Как связаться | `zhp_first_step` | Нет: общий блок целиком в префиксе |
| Социальные сети | `zhp_socials` | Нет: общий блок целиком в префиксе |
| Специалисты | `zhp_specialists` | `fio` — ФИО; `position` — Должность / специализация; `img` — Фотография; `intro` — Краткое описание; `bio` — О специалисте; `personalquote` — Цитата; `stats` — Город, формат и опыт; `details` — Сведения о приёме; `methods` — Методы и подходы; `education` — Образование; `category` — Категории фильтра (через пробел) |
| Статьи | `zhp_articles` | `name` — Заголовок; `intro` — Анонс; `text` — Текст статьи; `img` — Изображение; `date` — Дата публикации; `readtime` — Время чтения; `category` — Категория (teens / parents / specialists) |
| Отзывы | `zhp_reviews` | `name` — Имя; `role` — Подпись; `text` — Текст отзыва; `img` — Аватар |
| Вопросы и ответы | `zhp_faq` | `question` — Вопрос; `answer` — Ответ |
| Дипломы и сертификаты | `zhp_certificates` | `name` — Название документа; `img` — Изображение документа; `file` — Файл для скачивания |
| Фотогалерея | `zhp_gallery` | `name` — Подпись фотографии; `img` — Фотография; `category` — Категория (classes / events / team / more) |
| Текстовый блок | `zhp_text` | `text` — Текст |
| Контакты | `zhp_contacts` | `name` — Заголовок; `text` — Вступление |
| Калькулятор стоимости | `zhp_calculator` | Нет: общий блок целиком в префиксе |
| Вопросы квиза | `zhp_quiz` | `question` — Вопрос; `answers` — Варианты ответа (по одному на строку) |
| Подписка на рассылку | `zhp_subscription` | Нет: общий блок целиком в префиксе |
| Регионы | `zhp_regions` | `main_domain` — Домен без протокола и пути; `city` — Город; `inCity` — Город: где? (в Москве); `cityRP` — Город: откуда? (Москвы); `cityDP` — Город: кому? (Москве); `address` — Адрес; `phone` — Телефон; `email` — Email для посетителей; `regim` — Время работы; `max` — MAX; `telegram` — Telegram; `whatsapp` — WhatsApp; `vk` — VK; `youtube` — YouTube; `instagram` — Instagram; `mapcode` — Код карты; `email_to` — Получатель заявок; `email_from` — Отправитель писем; `email_enabled` — Отправлять заявки на email |
| Заявки | `zhp_leads` | `name` — Имя; `phone` — Телефон; `email` — Email; `contact` — Контакт для связи; `message` — Сообщение; `form_type` — Источник формы; `page_url` — Страница; `domain` — Домен; `utm` — UTM-метки; `answers` — Ответы квиза; `request_key` — Ключ отправки; `delivery` — Статус письма; `consent` — Согласие на обработку данных |
| Как нас найти | `zhp_location` | `name` — Заголовок; `text` — Описание |

## Инфоблоки страниц

| Путь | Макет | Количество | Инфоблоки по порядку |
| --- | --- | --- | --- |
| `/` | home | 18 | Верхний баннер, Основатель проекта, Видео о проекте, Программа, Группа для родителей, Калькулятор стоимости, Вопросы квиза, Как связаться, Для кого, Этапы, Что предлагает проект, Специалисты, Отзывы, Дипломы и сертификаты, Статьи, Текстовый блок, Социальные сети, Подписка на рассылку |
| `/o-nas/` | inner | 6 | О проекте — первый экран, О проекте — вступление, Ценности проекта, Подросткам и родителям, Как проходит работа, Команда |
| `/blog/` | inner | 3 | Рекомендуемая статья, Статьи, Подписка на рассылку |
| `/kontakty/` | inner | 3 | Контакты, Вопросы и ответы, Как нас найти |
| `/o-nas/spetsialisty/` | inner | 4 | Заголовок страницы, Специалисты, Дипломы и сертификаты, Подписка на рассылку |
| `/o-nas/foto/` | inner | 3 | Заголовок галереи, Фотографии, Подписка на рассылку |
| `/o-nas/otzyvy/` | inner | 1 | Отзывы |
| `/o-nas/voprosy-i-otvety/` | inner | 1 | Вопросы и ответы |
| `/o-nas/sertifikaty/` | inner | 1 | Дипломы и сертификаты |
| `/regions/` | inner | 0 |  |
| `/zayavki/` | success | 0 |  |
| `/nastroiki-proekta/` | inner | 0 |  |
| `/politika-konfidentsialnosti/` | inner | 1 | Текст страницы |
| `/policy/` | inner | 1 | Текст страницы |
| `/404/` | error | 1 | Текст страницы |
| `/podrostkam/` | inner | 1 | Текст страницы |
| `/podrostkam/trevoga-i-perezhivaniya/` | inner | 6 | Баннер услуги, Содержание услуги, Причины трудностей, Как мы помогаем, Как проходит работа, Специалисты |
| `/podrostkam/trevoga-i-perezhivaniya/trevozhnye-mysli-i-strahi/` | inner | 1 | Текст страницы |
| `/podrostkam/trevoga-i-perezhivaniya/stress-i-silnye-perezhivaniya/` | inner | 1 | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/` | inner | 1 | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/sravnenie-sebya-s-drugimi/` | inner | 1 | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/strah-neudachi-i-oshibok/` | inner | 1 | Текст страницы |
| `/podrostkam/neuverennost-v-sebe/rabota-nad-samootsenkoy/` | inner | 1 | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/` | inner | 1 | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/konflikty-i-sposoby-ih-resheniya/` | inner | 1 | Текст страницы |
| `/podrostkam/otnosheniya-s-roditelyami/kak-govorit-o-slozhnom/` | inner | 1 | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/` | inner | 1 | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/raspoznavanie-svoih-emotsiy/` | inner | 1 | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/tehniki-samoregulyatsii/` | inner | 1 | Текст страницы |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/kogda-stoit-obratitsya-za-podderzhkoy/` | inner | 1 | Текст страницы |
| `/podrostkam/ucheba-i-motivatsiya/` | inner | 1 | Текст страницы |
| `/podrostkam/poisk-sebya/` | inner | 1 | Текст страницы |
| `/roditelyam/` | inner | 1 | Текст страницы |
| `/spetsialistam/` | inner | 1 | Текст страницы |
| `/materialy/` | inner | 1 | Текст страницы |
| `/testy/` | inner | 1 | Текст страницы |
| `/vebinary/` | inner | 1 | Текст страницы |

## Шаблоны компонентов

| Шаблон | Общий компонент |
| --- | --- |
| `zhp_cards_whom` — Для кого | `zhp_cards` |
| `zhp_cards_steps` — Этапы | `zhp_cards` |
| `zhp_cards_offer` — Что предлагает проект | `zhp_cards` |
| `zhp_cards_values` — Ценности проекта | `zhp_cards` |
| `zhp_cards_peoples` — Подросткам и родителям | `zhp_cards` |
| `zhp_cards_way` — Как проходит работа | `zhp_cards` |
| `zhp_cards_reasons` — Причины трудностей | `zhp_cards` |
| `zhp_cards_help` — Как мы помогаем | `zhp_cards` |
| `zhp_banner_home` — Главный баннер | `zhp_banner` |
| `zhp_banner_about` — О проекте — первый экран | `zhp_banner` |
| `zhp_banner_about_text` — О проекте — вступление | `zhp_banner` |
| `zhp_banner_specialists` — Заголовок списка специалистов | `zhp_banner` |
| `zhp_banner_gallery` — Заголовок галереи | `zhp_banner` |
| `zhp_banner_service` — Баннер услуги | `zhp_banner` |
| `zhp_specialists_home` — Специалисты — слайдер | `zhp_specialists` |
| `zhp_specialists_team` — Специалисты — команда | `zhp_specialists` |
| `zhp_articles_home` — Статьи — главная | `zhp_articles` |
| `zhp_articles_featured` — Рекомендуемая статья | `zhp_articles` |
| `zhp_reviews_home` — Отзывы — другие страницы | `zhp_reviews` |
| `zhp_faq_contacts` — Вопросы и ответы — другие страницы | `zhp_faq` |
| `zhp_certificates_home` — Дипломы и сертификаты — другие страницы | `zhp_certificates` |
| `zhp_subscription_shared` — Подписка — другие страницы | `zhp_subscription` |
| `zhp_articles_related` — Статьи — рядом с подробной статьёй | `zhp_articles` |
| `zhp_specialists_other` — Другие специалисты | `zhp_specialists` |
