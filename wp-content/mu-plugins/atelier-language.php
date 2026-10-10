<?php
/** Plugin Name: Atelier English and Russian storefront */
if (!defined('ABSPATH')) exit;

function atelier_language(): string {
    $value = isset($_GET['atelier_lang']) && is_string($_GET['atelier_lang']) ? sanitize_key(wp_unslash($_GET['atelier_lang'])) : '';
    if ($value === 'ru' || $value === 'en') return $value;
    return (isset($_COOKIE['atelier_lang']) && $_COOKIE['atelier_lang'] === 'ru') ? 'ru' : 'en';
}
add_filter('pre_determine_locale', function ($locale) {
    return is_admin() ? $locale : (atelier_language() === 'ru' ? 'ru_RU' : 'en_US');
}, 1);
add_filter('language_attributes', function ($attributes) {
    if (!is_admin()) $attributes = preg_replace('/\blang=("|\')[^"\']+\1/', 'lang="' . (atelier_language() === 'ru' ? 'ru-RU' : 'en-US') . '"', $attributes, 1);
    return $attributes;
});
add_action('init', function () {
    if (!isset($_GET['atelier_lang']) || !in_array($_GET['atelier_lang'], ['ru', 'en'], true) || is_admin()) return;
    $value = sanitize_key(wp_unslash($_GET['atelier_lang']));
    setcookie('atelier_lang', $value, ['expires' => time() + YEAR_IN_SECONDS, 'path' => COOKIEPATH ?: '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['atelier_lang'] = $value;
}, 0);

function atelier_language_toggle(): string {
    $next = atelier_language() === 'ru' ? 'en' : 'ru';
    $label = $next === 'ru' ? 'Русский' : 'English';
    return '<a class="language-switch" href="' . esc_url(add_query_arg('atelier_lang', $next)) . '" hreflang="' . esc_attr($next === 'ru' ? 'ru' : 'en') . '" lang="' . esc_attr($next === 'ru' ? 'ru' : 'en') . '">' . esc_html($label) . '</a>';
}

// Template copy is kept in the theme for a lightweight, plugin-free demo.
// WooCommerce and WordPress interface strings use their official ru_RU packs.
function atelier_translate_storefront(string $html): string {
    if (atelier_language() !== 'ru' || is_admin()) return $html;
    static $copy = [
        'Skip to content' => 'Перейти к содержимому', 'Open menu' => 'Открыть меню', 'Close menu' => 'Закрыть меню',
        '>Shop<' => '>Каталог<', '>Our story<' => '>О нас<', '>Journal<' => '>Журнал<', '>Account<' => '>Аккаунт<', '>Bag ' => '>Корзина ',
        'Thoughtful objects for a slower home' => 'Продуманные вещи для уютного дома', 'Free delivery on orders of $150 or more' => 'Бесплатная доставка при заказе от $150',
        'OBJECTS FOR EVERYDAY RITUALS' => 'ВЕЩИ ДЛЯ ПОВСЕДНЕВНОЙ ЖИЗНИ', 'Make room<br>for <em>living.</em>' => 'Создайте место<br>для <em>жизни.</em>',
        'Considered pieces for a home that feels like yours. Made slowly, chosen to stay.' => 'Продуманные вещи для дома, который отражает вас. Созданы не спеша и надолго.',
        'Explore the collection' => 'Смотреть коллекцию', 'A HOME, MORE YOU' => 'ДОМ, КОТОРЫЙ ПОХОЖ НА ВАС',
        'Useful things can still hold a little wonder. We work with independent makers to bring honest materials and quiet character into the everyday.' => 'Практичные вещи тоже могут удивлять. Мы работаем с независимыми мастерами, чтобы добавить в повседневную жизнь натуральные материалы и спокойный характер.',
        'A little about us' => 'Немного о нас', 'THE COLLECTION' => 'КОЛЛЕКЦИЯ', 'Made to be <em>kept.</em>' => 'Создано, чтобы <em>остаться.</em>',
        'Shop all pieces' => 'Весь каталог', 'GOOD THINGS TAKE TIME' => 'ХОРОШИЕ ВЕЩИ НЕ СПЕШАТ', 'Made with hands.<br><em>Kept with heart.</em>' => 'Создано руками.<br><em>Остаётся в сердце.</em>',
        'Small batches, natural materials and makers who care about every detail. That’s our kind of progress.' => 'Небольшие серии, натуральные материалы и мастера, внимательные к каждой детали. Вот что для нас важно.',
        'Inside the studio' => 'Заглянуть в мастерскую', 'FIND YOUR EVERYDAY' => 'НАЙДИТЕ СВОЁ', 'Shop by feeling.' => 'Выбирайте по настроению.',
        'Shop ceramics ↗' => 'Керамика ↗', 'Shop textiles ↗' => 'Текстиль ↗', 'Useful things, made thoughtfully. Find the pieces you’ll reach for every day.' => 'Продуманные вещи для жизни. Найдите то, чем будете пользоваться каждый день.',
        'Search products' => 'Поиск товаров', 'Name or description' => 'Название или описание', 'Price range' => 'Цена', 'From' => 'От', 'To' => 'До',
        '>Color<' => '>Цвет<', '>Material<' => '>Материал<', 'Apply filters' => 'Применить фильтры', 'Clear filters' => 'Сбросить фильтры',
        '>Filters ' => '>Фильтры ', '>Refine<' => '>Уточнить выбор<', 'Search' => 'Поиск', 'View piece ↗' => 'Подробнее ↗',
        'WE’RE REAL PEOPLE' => 'МЫ НА СВЯЗИ', 'Say <em>hello.</em>' => 'Напишите <em>нам.</em>', 'GET IN TOUCH' => 'КАК С НАМИ СВЯЗАТЬСЯ',
        'Use the message form ↗' => 'Открыть форму ↗', 'STUDIO HOURS' => 'ЧАСЫ РАБОТЫ', 'ORDERS' => 'ЗАКАЗЫ', 'Track an order ↗' => 'Проверить заказ ↗',
        'Send a note.' => 'Напишите нам', 'Your name' => 'Ваше имя', 'Email address' => 'Электронная почта', 'What’s on your mind?' => 'Ваше сообщение', 'Send message ↗' => 'Отправить ↗',
        'Our story' => 'О нас', 'Shipping & returns' => 'Доставка и возврат', 'My account' => 'Мой аккаунт', 'Back to top ↑' => 'Наверх ↑',
        'A note from us' => 'Новости мастерской', 'New arrivals, studio notes, and things worth keeping.' => 'Новинки, новости мастерской и вещи на долгие годы.',
        'Your email address' => 'Ваша электронная почта', 'Subscribe' => 'Подписаться',
        'A SMALLER, SLOWER KIND OF SHOP' => 'БОЛЕЕ СПОКОЙНЫЙ И ОСОЗНАННЫЙ МАГАЗИН', 'Buy less.<br><em>Live more.</em>' => 'Покупайте меньше.<br><em>Живите полнее.</em>',
        'WHY WE’RE HERE' => 'ЗАЧЕМ МЫ ЗДЕСЬ', 'Objects with a point of view, and a place in your life.' => 'Вещи с характером и своим местом в вашей жизни.',
        'Atelier began with a simple question: what if shopping for home felt a little more like getting to know the people who make the things?' => 'Atelier начался с простого вопроса: что, если выбирать вещи для дома станет похоже на знакомство с мастерами, которые их создают?',
        'We partner with independent studios whose work starts with material, patience and a useful idea. Every piece is selected for the way it works, the way it ages, and the feeling it brings into a room.' => 'Мы сотрудничаем с независимыми мастерскими, где всё начинается с материала, терпения и полезной идеи. Мы выбираем вещи за их практичность, красоту со временем и атмосферу, которую они создают.',
        'We believe in fewer, better things. Not perfect homes, but lived-in ones. The chipped bowl you reach for every morning. Linen that softens with every wash. A lamp that moves with you.' => 'Мы верим в меньшее количество вещей, но лучшего качества. Не в идеальные, а в обжитые дома: любимая миска для завтрака, лён, который становится мягче после стирок, лампа, которая переезжает вместе с вами.',
        'Good materials' => 'Хорошие материалы', 'Natural, traceable where possible, and made to wear in rather than wear out.' => 'Натуральные, по возможности прослеживаемые, рассчитанные на долгую жизнь.',
        'Independent hands' => 'Работа мастеров', 'Small makers bring knowledge and character that mass production cannot.' => 'Небольшие мастерские добавляют знания и характер, которых не хватает массовому производству.',
        'Everyday use' => 'Для повседневной жизни', 'Beautiful things belong in daily life. Use them, wash them, pass them on.' => 'Красивые вещи созданы для жизни. Пользуйтесь ими, стирайте и передавайте дальше.',
        'START WITH ONE GOOD THING' => 'НАЧНИТЕ С ОДНОЙ ХОРОШЕЙ ВЕЩИ', 'Find a piece that feels <em>like home.</em>' => 'Найдите вещь, которая <em>создаёт уют.</em>', 'Visit the shop ↗' => 'Перейти в каталог ↗',
        'THE PRACTICAL BITS' => 'ПОЛЕЗНАЯ ИНФОРМАЦИЯ', 'Good to <em>know.</em>' => 'Что <em>важно знать.</em>',
        'Where do you ship? ' => 'Куда вы доставляете? ', 'How long will my order take? ' => 'Сколько ждать заказ? ', 'Can I return an item? ' => 'Можно ли вернуть товар? ',
        'Are payments real? ' => 'Здесь принимаются настоящие платежи? ', 'How do I care for handmade ceramics? ' => 'Как ухаживать за керамикой ручной работы? ',
        'This demo checkout accepts US addresses: standard delivery is $8, and delivery is free on orders of $150 or more. These are sample rates; demo orders are not packed or shipped.' => 'В демо можно оформить заказ с адресом в США: стандартная доставка стоит $8, бесплатно — от $150. Это пример тарифов; демо-заказы не собираются и не отправляются.',
        'Demo orders are not fulfilled. Add accurate processing and delivery estimates for your own products.' => 'Демо-заказы не выполняются. Для своего магазина укажите реальные сроки обработки и доставки.',
        'Returns policy content is sample copy. Publish a policy that matches your business and local requirements.' => 'Текст о возврате приведён для примера. Добавьте правила, подходящие вашему бизнесу и местным требованиям.',
        'No payment provider is configured. Use WooCommerce’s offline Cash on delivery method for local order-flow testing, or a provider’s sandbox mode for payment testing.' => 'Платёжный провайдер не подключён. Для проверки заказа используется тестовый способ WooCommerce «Оплата при доставке» без списания средств.',
        'Care depends on the maker and glaze. Add the real care instructions for each product to its product description.' => 'Уход зависит от мастера и глазури. Добавьте реальные рекомендации в описание каждого товара.',
        'NOTES FROM OUR WORLD' => 'ЗАМЕТКИ ИЗ НАШЕГО МИРА', 'The <em>Journal.</em>' => '<em>Журнал.</em>', 'Thoughts on making, keeping, and finding beauty in the ordinary.' => 'О создании вещей, заботе о них и красоте повседневности.',
        'MAKERS / 6 MIN READ' => 'МАСТЕРА / 6 МИНУТ', 'A potter’s hands remember what the wheel forgets.' => 'Руки гончара помнят то, что забывает круг.',
        'In a small hillside studio, every bowl begins with a pause. We spent an afternoon with ceramicist Mara Ellis and left with clay beneath our nails.' => 'В небольшой мастерской на склоне каждый предмет начинается с паузы. Мы провели день с керамисткой Марой Эллис и унесли глину под ногтями.',
        'Read the story ↗' => 'Читать историю ↗', 'AT HOME / 4 MIN READ' => 'ДОМ / 4 МИНУТЫ', 'On setting a table for no particular reason.' => 'Как накрыть на стол просто так.',
        'A Tuesday, a mismatched set of plates, something warm in the oven. The best rituals rarely need an occasion.' => 'Обычный вторник, разные тарелки и что-то тёплое в духовке. Для лучших ритуалов не нужен повод.',
        'A NOTE, EVERY SO OFTEN' => 'ПИСЬМО ВРЕМЯ ОТ ВРЕМЕНИ', 'Good things to <em>read slowly.</em>' => 'Хорошие истории для <em>неторопливого чтения.</em>',
        'Join the demo newsletter to see the subscription flow. Addresses are stored privately in WordPress; no emails are sent.' => 'Попробуйте подписку на демо-рассылку. Адреса хранятся в закрытом разделе WordPress; письма не отправляются.',
        'Questions about a piece, an order, or just want to share how it found a home?' => 'Есть вопрос о товаре или заказе? Хотите рассказать, как вещь нашла свой дом?',
        'Your message is saved in this demo site’s private WordPress inbox. Please don’t include sensitive information.' => 'Сообщение сохранится в закрытом разделе демо-сайта WordPress. Не указывайте личные или платёжные данные.',
        'Thanks for writing. Your note is in our inbox.' => 'Спасибо! Сообщение сохранено.', 'Please check the required fields and try again.' => 'Проверьте обязательные поля и попробуйте ещё раз.',
        'Name' => 'Имя', 'Write a little note...' => 'Напишите сообщение…', 'I agree to have my message stored so the Atelier team can reply.' => 'Согласен(на) сохранить сообщение, чтобы команда Atelier могла ответить.',
        'Monday–Friday' => 'Понедельник–пятница', '10 am–5 pm (local time)' => '10:00–17:00 (местное время)', 'Explore' => 'Магазин', 'Help' => 'Помощь',
        'Shop all' => 'Весь каталог', 'Objects made to be lived with.<br>Considered materials, enduring forms.' => 'Вещи для жизни.<br>Натуральные материалы и простые формы.',
        'Manage entries in the private WordPress dashboard.' => 'Список подписчиков доступен в закрытой панели WordPress.', 'Save my email to the demo list; no emails are sent.' => 'Сохранить адрес в тестовом списке; письма не отправляются.',
        'Sunday stoneware bowl' => 'Каменная миска «Воскресенье»', 'A softly rounded everyday bowl, thrown and glazed by hand. The natural variation in each surface makes every piece its own.' => 'Миска мягкой округлой формы на каждый день, выточенная и глазурованная вручную. Естественные оттенки делают каждую вещь неповторимой.',
        'Ripple serving platter' => 'Блюдо для подачи «Волна»', 'A generous platter with a gently waved edge. Made for shared lunches and long evenings around the table.' => 'Вместительное блюдо с мягко волнистым краем для обедов в компании и долгих вечеров за столом.',
        'Everyday espresso cup' => 'Чашка для эспрессо «На каждый день»', 'A small, balanced cup with a thumb-friendly handle and satin glaze. Holds approximately 90 ml.' => 'Небольшая чашка с удобной ручкой и сатиновой глазурью. Объём около 90 мл.',
        'Gathering pitcher' => 'Кувшин «Собраться вместе»', 'A sculptural pitcher that pours cleanly and looks at home on the table between uses.' => 'Выразительный кувшин, из которого удобно наливать и который украшает стол даже без дела.',
        'Washed linen napkin set' => 'Набор льняных салфеток', 'Set of two relaxed linen napkins, pre-washed for a soft hand. Woven from European flax.' => 'Две мягкие льняные салфетки из европейского льна, предварительно выстиранные для мягкости.',
        'Linen table runner' => 'Льняная дорожка на стол', 'A long, softly draping runner with a fine hem. Naturally textured and easy to care for.' => 'Длинная дорожка с тонкой обработкой края. Натуральная фактура и простой уход.',
        'Quiet hour cushion cover' => 'Чехол на подушку «Тихий час»', 'A tactile cover in heavyweight washed linen, finished with a discreet hidden fastening.' => 'Фактурный чехол из плотного выстиранного льна с потайной застёжкой.',
        'Dawn throw' => 'Плед «Рассвет»', 'A light layer for cool mornings, woven from a breathable natural blend with a simple selvedge edge.' => 'Лёгкий плед для прохладного утра из дышащей натуральной ткани с аккуратной кромкой.',
        'Low oak candleholder' => 'Низкий подсвечник из дуба', 'Turned from solid oak with a considered, low profile. Designed for standard taper candles.' => 'Выточен из массива дуба и рассчитан на стандартные конические свечи.',
        'Handblown bud vase' => 'Ваза для одного цветка ручной выдувки', 'A small handblown glass vessel with a softly weighted base. Each one carries subtle bubbles and variation.' => 'Небольшая стеклянная ваза с устойчивым дном. В каждой остаются лёгкие пузырьки и оттенки ручной работы.',
        'Arc oak tray' => 'Дубовый поднос «Дуга»', 'A useful catch-all with a shallow carved edge. Finished by hand with a food-safe oil.' => 'Практичный поднос с неглубоким резным краем, вручную покрытый безопасным для еды маслом.',
        'Evening glass pair' => 'Пара стаканов «Вечер»', 'Two light, durable tumblers made for water, wine, or a small something after dinner.' => 'Два лёгких прочных стакана для воды, вина или вечернего напитка.',
        'Thoughtfully made, ready for everyday.' => 'Продумано и создано для повседневной жизни.', 'Ceramics' => 'Керамика', 'Textiles' => 'Текстиль', 'Objects' => 'Предметы для дома',
        'Sand' => 'Песочный', 'Ivory' => 'Слоновая кость', 'Olive' => 'Оливковый', 'Terracotta' => 'Терракотовый',
        'Stoneware' => 'Каменная керамика', 'Linen' => 'Лён', 'Oak' => 'Дуб', 'Glass' => 'Стекло',
    ];
    return strtr($html, $copy);
}
add_action('template_redirect', function () { if (!is_admin()) ob_start('atelier_translate_storefront'); }, 0);
