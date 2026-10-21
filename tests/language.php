<?php
// WP-CLI eval-file; explicit RU request overrides the administrator CLI locale.
$_GET['atelier_lang'] = 'ru';
$samples = [
    '<script>var c = document.body.className; c = c.replace(/woocommerce-no-js/, "woocommerce-js");</script>',
    '<script type="application/ld+json">{"@type":"Product","name":"Low oak candleholder"}</script>',
    '<style>.NameFromTo{color:red}</style>',
    '<SCRIPT>const Name = "From To";</SCRIPT>',
];
foreach ($samples as $sample) {
    if (atelier_translate_storefront($sample) !== $sample) throw new RuntimeException('Translation changed script/style content.');
}
if (atelier_translate_storefront('<p>Name</p>') !== '<p>Имя</p>') throw new RuntimeException('Visible copy stopped translating.');
if (atelier_translate_storefront('Open menu<script>document.body.className</script>Close menu') !== 'Открыть меню<script>document.body.className</script>Закрыть меню') throw new RuntimeException('Mixed markup was not translated safely.');
echo "PASS: RU visible copy translated; JS, CSS and JSON preserved.\n";
