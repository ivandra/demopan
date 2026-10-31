<?php

// Module description
const NETCAT_MODULE_EXTSCRIPTS = 'External Scripts';
const NETCAT_MODULE_EXTSCRIPTS_DESCRIPTION = 'Manage external scripts (counters, analytics, widgets)';

// General
const NETCAT_MODULE_EXTSCRIPTS_ADD = 'Add Script';
const NETCAT_MODULE_EXTSCRIPTS_EDIT = 'Edit';
const NETCAT_MODULE_EXTSCRIPTS_DELETE = 'Delete';
const NETCAT_MODULE_EXTSCRIPTS_SAVE = 'Save';
const NETCAT_MODULE_EXTSCRIPTS_CANCEL = 'Cancel';
const NETCAT_MODULE_EXTSCRIPTS_ENABLED = 'Enabled';
const NETCAT_MODULE_EXTSCRIPTS_DISABLED = 'Disabled';
const NETCAT_MODULE_EXTSCRIPTS_NAME = 'Name';
const NETCAT_MODULE_EXTSCRIPTS_TYPE = 'Script Type';
const NETCAT_MODULE_EXTSCRIPTS_PRIORITY = 'Priority';
const NETCAT_MODULE_EXTSCRIPTS_STATUS = 'Status';
const NETCAT_MODULE_EXTSCRIPTS_ACTIONS = 'Actions';
const NETCAT_MODULE_EXTSCRIPTS_SETTINGS = 'Settings';
const NETCAT_MODULE_EXTSCRIPTS_CONDITIONS = 'Display Conditions';
const NETCAT_MODULE_EXTSCRIPTS_PREVIEW = 'Code Preview';

// Snippet types
const NETCAT_MODULE_EXTSCRIPTS_TYPE_YANDEX_METRIKA = 'Yandex.Metrica';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_GTM = 'Google Tag Manager';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_GA4 = 'Google Analytics 4';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_JIVO = 'Jivo';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CARROT = 'Carrot Quest';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_VK_PIXEL = 'VK Pixel';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CALLTOUCH = 'Calltouch';
const NETCAT_MODULE_EXTSCRIPTS_TYPE_CUSTOM = 'Custom Code';

// Common analytics settings
const NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS = 'Event Tracking';
const NETCAT_MODULE_EXTSCRIPTS_ENABLE_EVENTS_DESC = 'Enable event tracking from forms and other elements with data-analytics-* attributes';

// Yandex.Metrica
const NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID = 'Counter ID';
const NETCAT_MODULE_EXTSCRIPTS_YM_COUNTER_ID_DESC = 'Numeric counter ID from Yandex.Metrica';
const NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR = 'Webvisor';
const NETCAT_MODULE_EXTSCRIPTS_YM_WEBVISOR_DESC = 'Record visitor actions';
const NETCAT_MODULE_EXTSCRIPTS_YM_CLICKMAP = 'Click Map';
const NETCAT_MODULE_EXTSCRIPTS_YM_TRACKLINKS = 'Track Links';
const NETCAT_MODULE_EXTSCRIPTS_YM_ACCURATE_BOUNCE = 'Accurate Bounce Rate';
const NETCAT_MODULE_EXTSCRIPTS_YM_TRACKHASH = 'Track URL Hash';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE = 'E-commerce';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_DESC = 'Enable e-commerce data transmission';
const NETCAT_MODULE_EXTSCRIPTS_YM_ECOMMERCE_CONTAINER = 'Data Container Name';

// Google Tag Manager
const NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID = 'Container ID';
const NETCAT_MODULE_EXTSCRIPTS_GTM_CONTAINER_ID_DESC = 'Container ID in GTM-XXXXXXX format';

// Google Analytics
const NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID = 'Measurement ID';
const NETCAT_MODULE_EXTSCRIPTS_GA_MEASUREMENT_ID_DESC = 'Admin → Data Streams → select stream → Measurement ID (format G-XXXXXXXXXX)';

// Jivo
const NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID = 'Widget ID';
const NETCAT_MODULE_EXTSCRIPTS_JIVO_WIDGET_ID_DESC = 'Widget ID from Jivo dashboard';

// Carrot Quest
const NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID = 'App ID';
const NETCAT_MODULE_EXTSCRIPTS_CARROT_APP_ID_DESC = 'App ID from Carrot Quest dashboard';

// VK Pixel
const NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID = 'Pixel ID';
const NETCAT_MODULE_EXTSCRIPTS_VK_PIXEL_ID_DESC = 'VK Pixel ID';

// Calltouch
const NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID = 'Script ID';
const NETCAT_MODULE_EXTSCRIPTS_CALLTOUCH_ID_DESC = 'Calltouch mod_id from personal account';

// Custom code
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START = 'Code after &lt;head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_START_DESC = 'HTML/JS code to be inserted right after the opening &lt;head&gt; tag';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END = 'Code before &lt;/head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_HEAD_END_DESC = 'HTML/JS code to be inserted before the closing &lt;/head&gt; tag';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START = 'Code after &lt;body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_START_DESC = 'HTML/JS code to be inserted right after the opening &lt;body&gt; tag';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END = 'Code before &lt;/body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_CUSTOM_BODY_END_DESC = 'HTML/JS code to be inserted before the closing &lt;/body&gt; tag';

// Display conditions
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_ALL = 'All pages';
const NETCAT_MODULE_EXTSCRIPTS_SCOPE_SOME = 'Some pages';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE = 'Only selected sections';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_EXCLUDE = 'All except selected sections';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_SELECT = 'Select sections';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_INCLUDE_CHILDREN = 'Include child sections';
const NETCAT_MODULE_EXTSCRIPTS_CONDITION_SELECTED = 'Sections selected';

// Errors
const NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_REQUIRED = 'Counter ID is required';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_COUNTER_NUMERIC = 'Counter ID must be a number';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_REQUIRED = 'Container ID is required';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CONTAINER_FORMAT = 'Container ID must be in GTM-XXXXXXX format';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_CUSTOM_EMPTY = 'At least one code field must be filled';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_NAME_REQUIRED = 'Name is required';
const NETCAT_MODULE_EXTSCRIPTS_ERROR_TYPE_REQUIRED = 'Select script type';

// Messages
const NETCAT_MODULE_EXTSCRIPTS_MSG_SAVED = 'Script saved';
const NETCAT_MODULE_EXTSCRIPTS_MSG_DELETED = 'Script deleted';
const NETCAT_MODULE_EXTSCRIPTS_MSG_ENABLED = 'Script enabled';
const NETCAT_MODULE_EXTSCRIPTS_MSG_DISABLED = 'Script disabled';
const NETCAT_MODULE_EXTSCRIPTS_MSG_ORDER_SAVED = 'Order saved';
const NETCAT_MODULE_EXTSCRIPTS_MSG_NO_SCRIPTS = 'No scripts added';
const NETCAT_MODULE_EXTSCRIPTS_MSG_SELECT_SITE = 'Select a site to manage scripts';
const NETCAT_MODULE_EXTSCRIPTS_MSG_NO_SITES = 'No sites available';
const NETCAT_MODULE_EXTSCRIPTS_SITE = 'Site';
const NETCAT_MODULE_EXTSCRIPTS_SCRIPTS_COUNT = 'Scripts';

// Confirmations
const NETCAT_MODULE_EXTSCRIPTS_CONFIRM_DELETE = 'Are you sure you want to delete this script?';

// Hints
const NETCAT_MODULE_EXTSCRIPTS_HINT_DRAG = 'Drag to change order';
const NETCAT_MODULE_EXTSCRIPTS_HINT_PRIORITY = 'Lower value means earlier insertion';

// Insertion positions (for preview)
const NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_START = 'After &lt;head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_HEAD_END = 'Before &lt;/head&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_BODY_START = 'After &lt;body&gt;';
const NETCAT_MODULE_EXTSCRIPTS_POS_BODY_END = 'Before &lt;/body&gt;';

// Admin
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_TITLE = 'External Scripts';
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_ADD_TITLE = 'Add Script';
const NETCAT_MODULE_EXTSCRIPTS_ADMIN_EDIT_TITLE = 'Edit Script';
const NETCAT_MODULE_EXTSCRIPTS_SELECT_TYPE = 'Select script type';

// Cookie category (consent)
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY = 'Cookie category';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CATEGORY_HINT = 'Set a category if the script stores cookies or sends visitor data to third parties (analytics, ads, chats): when blocking is enabled it will not run until the visitor grants consent. If the script does none of that, choose "Does not collect data" and it will always run.';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_DEFAULT = '(default for the script type)';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_NONE = 'Does not store cookies or send data (never block)';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_NECESSARY = 'Necessary';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_FUNCTIONAL = 'Functional';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_ANALYTICS = 'Analytics';
const NETCAT_MODULE_EXTSCRIPTS_COOKIE_CAT_MARKETING = 'Marketing';

// Consent-window mode line on the module page
const NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_LINE = 'Cookie consent window mode (if the window is present on the site)';
const NETCAT_MODULE_EXTSCRIPTS_CONSENT_MODE_CONFIGURE = 'configure';

// Tabs
const NETCAT_MODULE_EXTSCRIPTS_TAB_SNIPPETS = 'Embeddable scripts';
const NETCAT_MODULE_EXTSCRIPTS_TAB_PROVIDERS = 'Content script auto-block';

// Provider registry (maps/video — domain-based auto-block before consent)
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_INTRO = 'Third-party widgets (maps, video, etc.) blocked until the visitor grants cookie consent — regardless of how they got on the page (built-in widget, custom component, content snippet). "Disable" keeps the entry in the list but stops blocking that provider.';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD = 'Add provider';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ADD_TITLE = 'Add provider';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_EDIT_TITLE = 'Edit provider';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_MSG_EMPTY = 'No providers added yet';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_LABEL = 'Label';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN = 'Domain / pattern';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_PATTERN_HINT = 'A domain substring (e.g. vkvideo.ru), or a regular expression without delimiters if "Regular expression" is checked (e.g. (?:api-maps|maps)\.yandex\.ru). Matched against the tag\'s src and the body of inline scripts.';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_IS_REGEX = 'Regular expression';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CATEGORY = 'Cookie category';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_CONFIRM_DELETE = 'Are you sure you want to delete this provider?';
const NETCAT_MODULE_EXTSCRIPTS_PROVIDER_ERROR_REGEX = 'Invalid regular expression — check the syntax';
