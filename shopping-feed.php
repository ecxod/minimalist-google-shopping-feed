<?php
/**
 * Plugin Name:       Minimalist Google Shopping Feed
 * Text Domain:       minimalist-google-shopping-feed
 * Plugin URI:        https://github.com/ecxod/minimalist-google-shopping-feed
 * Description:       Generiert einen sauberen XML-Feed für das Google Merchant Center ohne Tracking.
 * Version:           1.0
 * Author:            Christian Eichert <c@zp1.net>
 * Author URI:        https://github.com/ecxod
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */
// Absoluter Pfad zum Vendor-Verzeichnis (außerhalb von WordPress-Core)

$autoload_file = realpath( dirname(ABSPATH). '/vendor/autoload.php');

if (file_exists($autoload_file)) {
    require_once $autoload_file;

    // Initialisierung von Dotenv (falls du es nutzt)
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(ABSPATH);
        $dotenv->safeLoad();
    }
}

if (!defined('ABSPATH')) exit;

// Registriere einen Endpunkt, damit wir die XML aufrufen können
add_action('init', function() {
    add_rewrite_rule('^google-shopping-feed\.xml$', 'index.php?google_shopping_feed=1', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'google_shopping_feed';
    return $vars;
});

add_action('template_redirect', function() {
    if (get_query_var('google_shopping_feed')) {
        generate_google_shopping_xml();
        exit;
    }
});

function generate_google_shopping_xml() {
    if (!class_exists('WooCommerce')) return;

    header('Content-Type: application/xml; charset=utf-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">';
    echo '<channel>';
    echo '<title>' . get_bloginfo('name') . '</title>';
    echo '<link>' . get_bloginfo('url') . '</link>';
    echo '<description>Mein WooCommerce Produkt Feed</description>';

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $products = get_posts($args);

    foreach ($products as $post) {
        $product = wc_get_product($post->ID);

        echo '<item>';
        echo '<g:id>' . $product->get_id() . '</g:id>';
        echo '<g:title>' . htmlspecialchars($product->get_name()) . '</g:title>';
        echo '<g:description>' . htmlspecialchars(wp_strip_all_tags($post->post_content)) . '</g:description>';
        echo '<g:link>' . get_permalink($post->ID) . '</g:link>';
        echo '<g:image_link>' . wp_get_attachment_url($product->get_image_id()) . '</g:image_link>';
        echo '<g:condition>new</g:condition>';
        echo '<g:availability>' . ($product->is_in_stock() ? 'in_stock' : 'out_of_stock') . '</g:availability>';
        echo '<g:price>' . $product->get_price() . ' ' . get_woocommerce_currency() . '</g:price>';

        // WICHTIG: Wenn du keine EAN/GTIN hast, muss Google das wissen:
        echo '<g:identifier_exists>no</g:identifier_exists>';

        echo '</item>';
    }

    echo '</channel>';
    echo '</rss>';
}
