# Компоненты и поля v5

23 базовых компонента, 24 шаблона и существующие регионы №223 / №57. Всего 74 новых поля, включая служебные поля заявок.

| Компонент | Ключ | Поля |
| --- | --- | --- |
| Карточки и преимущества | `life_cards` | `name` — Название; `text` — Описание; `img` — Изображение или иконка; `img_mobile` — Мобильное изображение; `link` — Ссылка |
| Баннер страницы | `life_banner` | `name` — Заголовок; `text` — Описание; `img` — Основное изображение |
| Основатель проекта | `life_founder` | `fio` — ФИО; `img` — Фотография; `bio` — Описание; `achievements` — Достижения |
| ПОЗНАКОМЬТЕСЬ С НАМИ БЛИЖЕ | `life_video` | `name` — Заголовок; `text` — Описание; `link` — Ссылка на видео; `img` — Обложка видео |
| Программа | `life_program` | `name` — Заголовок; `text` — Вступление; `directions` — Направления программы; `support` — Содержание сопровождения |
| Содержание услуги | `life_program_service` | `name` — Заголовок; `text` — Вступление; `directions` — Направления программы; `support` — Содержание сопровождения |
| Группа для родителей | `life_parent_group` | Нет: общий блок целиком в префиксе |
| Как связаться | `life_first_step` | Нет: общий блок целиком в префиксе |
| Социальные сети | `life_socials` | Нет: общий блок целиком в префиксе |
| Специалисты | `life_specialists` | `fio` — ФИО; `position` — Должность / специализация; `img` — Фотография; `intro` — Краткое описание; `bio` — О специалисте; `personalquote` — Цитата; `stats` — Город, формат и опыт; `details` — Сведения о приёме; `methods` — Методы и подходы; `education` — Образование; `category` — Категории фильтра (через пробел) |
| Статьи | `life_articles` | `name` — Заголовок; `intro` — Анонс; `text` — Текст статьи; `img` — Изображение; `date` — Дата публикации; `readtime` — Время чтения; `category` — Категория (teens / parents / specialists) |
| Отзывы | `life_reviews` | `name` — Имя; `role` — Подпись; `text` — Текст отзыва; `img` — Аватар |
| Вопросы и ответы | `life_faq` | `question` — Вопрос; `answer` — Ответ |
| Дипломы и сертификаты | `life_certificates` | `name` — Название документа; `img` — Изображение документа; `file` — Файл для скачивания |
| Фотогалерея | `life_gallery` | `name` — Подпись фотографии; `img` — Фотография; `category` — Категория (classes / events / team / more) |
| Текстовый блок | `life_text` | `text` — Текст |
| Контакты | `life_contacts` | `name` — Заголовок; `text` — Вступление |
| Калькулятор стоимости | `life_calculator` | Нет: общий блок целиком в префиксе |
| Вопросы квиза | `life_quiz` | `question` — Вопрос; `answers` — Варианты ответа (по одному на строку) |
| Подписка на рассылку | `life_subscription` | Нет: общий блок целиком в префиксе |
| Регионы | `life_regions` | `main_domain` — Домен без протокола и пути; `city` — Город; `inCity` — Город: где? (в Москве); `cityRP` — Город: откуда? (Москвы); `cityDP` — Город: кому? (Москве); `address` — Адрес; `phone` — Телефон; `email` — Email для посетителей; `regim` — Время работы; `max` — MAX; `telegram` — Telegram; `whatsapp` — WhatsApp; `vk` — VK; `youtube` — YouTube; `instagram` — Instagram; `mapcode` — Код карты; `email_to` — Получатель заявок; `email_from` — Отправитель писем; `email_enabled` — Отправлять заявки на email |
| Заявки | `life_leads` | `name` — Имя; `phone` — Телефон; `email` — Email; `contact` — Контакт для связи; `message` — Сообщение; `form_type` — Источник формы; `page_url` — Страница; `domain` — Домен; `utm` — UTM-метки; `answers` — Ответы квиза; `request_key` — Ключ отправки; `delivery` — Статус письма; `consent` — Согласие на обработку данных |
| Как нас найти | `life_location` | `name` — Заголовок; `text` — Описание |
| Форма консультации | `life_popup` | Нет: общий блок целиком в префиксе |

## Порядок инфоблоков

| Раздел | Инфоблоки |
| --- | --- |
| `/` | Верхний баннер, Основатель проекта, Видео о проекте, Программа, Группа для родителей, Калькулятор стоимости, Вопросы квиза, Как связаться, Для кого, Этапы, Что предлагает проект, Специалисты, Отзывы, Дипломы и сертификаты, Статьи, Текстовый блок, Социальные сети, Подписка на рассылку, Форма консультации |
| `/o-nas/` | О проекте — первый экран, О проекте — вступление, Ценности проекта, Подросткам и родителям, Как проходит работа, Команда |
| `/blog/` | Рекомендуемая статья, Статьи, Подписка на рассылку |
| `/kontakty/` | Контакты, Вопросы и ответы, Как нас найти |
| `/o-nas/spetsialisty/` | Заголовок страницы, Специалисты, Дипломы и сертификаты, Подписка на рассылку |
| `/o-nas/foto/` | Заголовок галереи, Фотографии, Подписка на рассылку |
| `/o-nas/otzyvy/` | Отзывы |
| `/o-nas/voprosy-i-otvety/` | Вопросы и ответы |
| `/o-nas/sertifikaty/` | Дипломы и сертификаты |
| `/regions/` | Служебный раздел |
| `/zayavki/` | Служебный раздел |
| `/politika-konfidentsialnosti/` | Текст страницы |
| `/policy/` | Текст страницы |
| `/404/` | Текст страницы |
| `/podrostkam/` | Нет: пустой черновик |
| `/podrostkam/trevoga-i-perezhivaniya/` | Баннер услуги, Содержание услуги, Причины трудностей, Как мы помогаем, Как проходит работа, Специалисты |
| `/podrostkam/trevoga-i-perezhivaniya/trevozhnye-mysli-i-strahi/` | Нет: пустой черновик |
| `/podrostkam/trevoga-i-perezhivaniya/stress-i-silnye-perezhivaniya/` | Нет: пустой черновик |
| `/podrostkam/neuverennost-v-sebe/` | Нет: пустой черновик |
| `/podrostkam/neuverennost-v-sebe/sravnenie-sebya-s-drugimi/` | Нет: пустой черновик |
| `/podrostkam/neuverennost-v-sebe/strah-neudachi-i-oshibok/` | Нет: пустой черновик |
| `/podrostkam/neuverennost-v-sebe/rabota-nad-samootsenkoy/` | Нет: пустой черновик |
| `/podrostkam/otnosheniya-s-roditelyami/` | Нет: пустой черновик |
| `/podrostkam/otnosheniya-s-roditelyami/konflikty-i-sposoby-ih-resheniya/` | Нет: пустой черновик |
| `/podrostkam/otnosheniya-s-roditelyami/kak-govorit-o-slozhnom/` | Нет: пустой черновик |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/` | Нет: пустой черновик |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/raspoznavanie-svoih-emotsiy/` | Нет: пустой черновик |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/tehniki-samoregulyatsii/` | Нет: пустой черновик |
| `/podrostkam/emotsionalnye-perepady-i-trudnosti-s-upravleniem-emotsiyami/kogda-stoit-obratitsya-za-podderzhkoy/` | Нет: пустой черновик |
| `/podrostkam/ucheba-i-motivatsiya/` | Нет: пустой черновик |
| `/podrostkam/poisk-sebya/` | Нет: пустой черновик |
| `/roditelyam/` | Нет: пустой черновик |
| `/spetsialistam/` | Нет: пустой черновик |
