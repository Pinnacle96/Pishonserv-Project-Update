<?php

/**
 * WooCommerce Product CSV to MySQL Importer
 *
 * This script imports product data from a WooCommerce CSV export file
 * into a MySQL database with a predefined schema. It handles the main
 * products table and normalized tables for categories, tags, images,
 * and attributes.
 *
 * IMPORTANT:
 * 1. Replace 'username' and 'password' with your actual database credentials.
 * 2. Ensure the CSV file 'wc-product-export-22-7-2025-1753183500130.csv' is in
 * the same directory as this script, or provide the correct path.
 * 3. The database schema provided previously MUST be in place before running
 * this script.
 * 4. This script assumes 'Attribute 1', 'Attribute 2', 'Attribute 3' are the
 * only attributes to be processed. If more exist, extend the loop.
 * 5. Columns identified as "missing" in the previous review (e.g., _wt_css, tcb2_ready)
 * are EXCLUDED from the main `products` table insert as they were not
 * part of the provided `products` table schema. If you need them, you must
 * add them to your `products` table schema first.
 */

// Database connection parameters
$host = 'localhost';
$dbname = 'u561302917_Pishonserv';
$user = 'u561302917_Pishonserv'; // <<< CHANGE THIS
$password = 'Pishonserv@255'; // <<< CHANGE THIS


// $host = "localhost";
// $username = "u561302917_Pishonserv";
// $password = "Pishonserv@255";
// $database = "u561302917_Pishonserv";
// CSV file path
$csvFilePath = 'wc-product-export-22-7-2025-1753183500130.csv';

// --- Database Connection ---
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Disable emulation of prepared statements, use real prepared statements
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    echo "Database connected successfully.\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Define Product Table Columns (MUST match your 'products' table schema order) ---
// This array defines the exact order of columns in your `products` table.
// Ensure it matches your CREATE TABLE statement precisely.
$productColumns = [
    'id', 'type', 'sku', 'gtin_upc_ean_isbn', 'name', 'published', 'is_featured',
    'visibility', 'short_description', 'description', 'date_sale_price_starts',
    'date_sale_price_ends', 'tax_status', 'tax_class', 'in_stock', 'stock',
    'low_stock_amount', 'backorders_allowed', 'sold_individually',
    'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'allow_customer_reviews',
    'purchase_note', 'sale_price', 'regular_price', 'categories', 'tags',
    'shipping_class', 'images', 'download_limit', 'download_expiry_days',
    'parent_id', 'grouped_products', 'upsells', 'cross_sells', 'external_url',
    'button_text', 'position', 'brands', 'attribute_1_name', 'attribute_1_values',
    'attribute_1_visible', 'attribute_1_global', 'attribute_1_default',
    'attribute_2_name', 'attribute_2_values', 'attribute_2_visible',
    'attribute_2_global', 'attribute_2_default', 'attribute_3_name',
    'attribute_3_values', 'attribute_3_visible', 'attribute_3_global',
    'attribute_3_default', 'meta__min_variation_price', 'meta__max_variation_price',
    'meta__min_price_variation_id', 'meta__max_price_variation_id',
    'meta__min_variation_regular_price', 'meta__max_variation_regular_price',
    'meta__min_regular_price_variation_id', 'meta__max_regular_price_variation_id',
    'meta__min_variation_sale_price', 'meta__max_variation_sale_price',
    'meta__min_sale_price_variation_id', 'meta__max_sale_price_variation_id',
    'meta__yoast_wpseo_primary_product_cat', 'meta__yoast_wpseo_primary_product_brand',
    'meta__ywpc_enabled', 'meta__ywpc_sale_price_dates_from', 'meta__ywpc_sale_price_dates_to',
    'meta__yst_prominent_words_version', 'meta__yoast_wpseo_focuskeywords',
    'meta__yoast_wpseo_keywordsynonyms', 'meta__wc_gla_mc_status',
    'meta__wc_gla_sync_status', 'meta__wc_gla_visibility',
    'meta__shopengine_product_views_count', 'meta__scwf_sales_countdown',
    'meta__scwf_sales_date', 'meta__scwf_sales_hour', 'meta__scwf_sales_minute',
    'meta__scwf_sales_second', 'meta__scwf_sales_day', 'meta__wp_old_date',
    'meta__last_editor_used_jetpack', 'meta__cartflows_redirect_flow_id',
    'meta__cartflows_add_to_cart_text', 'meta__yoast_wpseo_estimated_reading_time_minutes',
    'meta__wc_gla_synced_at', 'meta__aioseo_description', 'meta__aioseo_keywords',
    'meta__aioseo_og_article_section', 'meta__aioseo_og_article_tags',
    'meta__saswp_schema_type_product_pros_enable_cons', 'meta__om_disable_all_campaigns',
    'meta__aioseo_title', 'meta__yoast_wpseo_wordproof_timestamp',
    'meta__basel_sguide_select', 'meta__basel_total_stock_quantity',
    'meta__product_360_image_gallery', 'meta__basel_main_layout',
    'meta__basel_sidebar_width', 'meta__basel_custom_sidebar',
    'meta__basel_product_design', 'meta__basel_single_product_style',
    'meta__basel_product_background', 'meta__basel_extra_content',
    'meta__basel_extra_position', 'meta__basel_product_custom_tab_title',
    'meta__basel_product_custom_tab_content', 'meta__basel_new_label_date',
    'meta__basel_swatches_attribute', 'meta__basel_product_video',
    'meta__basel_product_hashtag', 'meta__wpb_vc_js_status',
    'meta__xfgmc_google_product_category', 'meta__xfgmc_fb_product_category',
    'meta__xfgmc_tax_category', 'meta__xfgmc_identifier_exists', 'meta__xfgmc_adult',
    'meta__xfgmc_condition', 'meta__xfgmc_is_bundle', 'meta__xfgmc_multipack',
    'meta__xfgmc_shipping_label', 'meta__xfgmc_unit_pricing_measure',
    'meta__xfgmc_unit_pricing_base_measure', 'meta__xfgmc_return_rule_label',
    'meta__xfgmc_store_code', 'meta__xfgmc_min_handling_time',
    'meta__xfgmc_max_handling_time', 'meta__xfgmc_custom_label_0',
    'meta__xfgmc_custom_label_1', 'meta__xfgmc_custom_label_2',
    'meta__xfgmc_custom_label_3', 'meta__xfgmc_custom_label_4',
    'meta__wds_primary_term_product_cat', 'meta__wds_save_primary_product_cat_nonce',
    'meta__wds_trimmed_excerpt', 'meta__berocket_post_order',
    'meta__yoast_wpseo_metadesc', 'meta__yoast_wpseo_content_score',
    'meta__slide_template', 'meta__yoast_wpseo_focuskw', 'meta__yoast_wpseo_linkdex',
    'meta__rs_page_bg_color', 'meta__wds_focus_keywords',
    'meta__iconic_wsb_fbt_discount_type', 'meta__iconic_wsb_fbt_title',
    'meta__iconic_wsb_fbt_sales_pitch', 'meta__iconic_wsb_fbt_discount_value',
    'meta__sale_price_times_from', 'meta__sale_price_times_to',
    'meta__woo_ctr_select_countdown_timer', 'meta__woo_ctr_enable_progress_bar',
    'meta__woo_ctr_progress_bar_goal', 'meta__woo_ctr_progress_bar_initial',
    'fbt_title', 'fbt_description', 'fbt_products', 'fbt_unchecked_by_default',
    'fbt_discount_value', 'fbt_discount_type', 'after_add_to_cart_popup_products',
    'meta__wds_primary_term_product_brand', 'meta__wds_save_primary_product_brand_nonce',
    'meta__wpas_done_all', 'meta__basel_new_label'
];

$productPlaceholders = implode(', ', array_fill(0, count($productColumns), '?'));

// --- Prepare Statements ---
$productStmt = $db->prepare("INSERT INTO products (" . implode(', ', $productColumns) . ") VALUES ($productPlaceholders)");
$categoryStmt = $db->prepare("INSERT INTO product_categories (product_id, category_path) VALUES (?, ?)");
$tagStmt = $db->prepare("INSERT INTO product_tags (product_id, tag) VALUES (?, ?)");
$imageStmt = $db->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
$attrStmt = $db->prepare("INSERT INTO product_attributes (product_id, attribute_name, attribute_values, visible, global, is_default) VALUES (?, ?, ?, ?, ?, ?)");

echo "Prepared SQL statements.\n";

// --- Open CSV file ---
if (!file_exists($csvFilePath)) {
    die("Error: CSV file not found at " . $csvFilePath . "\n");
}

$csvFile = fopen($csvFilePath, 'r');
if ($csvFile === false) {
    die("Error: Could not open CSV file at " . $csvFilePath . "\n");
}
echo "CSV file opened successfully.\n";

$headers = fgetcsv($csvFile);
if ($headers === false) {
    die("Error: Could not read headers from CSV file. File might be empty or corrupted.\n");
}

// *** IMPORTANT FIX: Remove BOM from the first header if present ***
if (isset($headers[0])) {
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
}

// Trim whitespace from headers for robust matching
$headers = array_map('trim', $headers);
echo "CSV Headers read: " . implode(', ', $headers) . "\n";
echo "Trimmed Headers: " . implode(', ', $headers) . "\n";


// Begin transaction for atomicity
$db->beginTransaction();

$importedRowCount = 0;
$rowNumber = 1; // Start from 1 for headers, so first data row is 2

try {
    while (($row = fgetcsv($csvFile)) !== false) {
        $rowNumber++; // Increment for each data row

        // Skip empty rows (e.g., blank lines at the end of CSV)
        if (empty(array_filter($row))) {
            echo "Skipping empty row #" . $rowNumber . "\n";
            continue;
        }

        // Ensure $row has enough elements to match $headers, filling missing values with null
        // This prevents array_combine warning if a row has fewer columns than headers
        $paddedRow = array_pad($row, count($headers), null);
        $data = array_combine($headers, $paddedRow);

        // Get product ID (crucial for foreign keys)
        $productId = !empty($data['ID']) ? (int)$data['ID'] : null;
        if ($productId === null) {
            error_log("Skipping row #" . $rowNumber . " due to missing or invalid Product ID: " . (isset($data['ID']) ? $data['ID'] : 'NULL') . " - Full row data: " . implode(',', $row));
            echo "Skipping row #" . $rowNumber . " (Missing Product ID).\n";
            continue;
        }

        echo "Processing product ID: " . $productId . " (Row #" . $rowNumber . ")\n";

        // --- Prepare values for the main products table ---
        // Ensure the order and type casting match your $productColumns and database schema
        $productValues = [
            $productId,
            trim($data['Type'] ?? ''),
            trim($data['SKU'] ?? ''),
            trim($data['GTIN, UPC, EAN, or ISBN'] ?? ''),
            trim($data['Name'] ?? ''),
            (int)(($data['Published'] ?? '') === '1'),
            (int)(($data['Is featured?'] ?? '') === '1'),
            trim($data['Visibility in catalog'] ?? ''),
            trim($data['Short description'] ?? ''),
            trim($data['Description'] ?? ''),
            !empty($data['Date sale price starts']) ? date('Y-m-d', strtotime($data['Date sale price starts'])) : null,
            !empty($data['Date sale price ends']) ? date('Y-m-d', strtotime($data['Date sale price ends'])) : null,
            trim($data['Tax status'] ?? ''),
            trim($data['Tax class'] ?? ''),
            (int)(($data['In stock?'] ?? '') === '1'),
            !empty($data['Stock']) ? (int)$data['Stock'] : null,
            !empty($data['Low stock amount']) ? (int)$data['Low stock amount'] : null,
            (int)(($data['Backorders allowed?'] ?? '') === '1'),
            (int)(($data['Sold individually?'] ?? '') === '1'),
            !empty($data['Weight (kg)']) ? (float)$data['Weight (kg)'] : null,
            !empty($data['Length (cm)']) ? (float)$data['Length (cm)'] : null,
            !empty($data['Width (cm)']) ? (float)$data['Width (cm)'] : null,
            !empty($data['Height (cm)']) ? (float)$data['Height (cm)'] : null,
            (int)(($data['Allow customer reviews?'] ?? '') === '1'),
            trim($data['Purchase note'] ?? ''),
            !empty($data['Sale price']) ? (float)$data['Sale price'] : null,
            !empty($data['Regular price']) ? (float)$data['Regular price'] : null,
            trim($data['Categories'] ?? ''),
            trim($data['Tags'] ?? ''),
            trim($data['Shipping class'] ?? ''),
            trim($data['Images'] ?? ''),
            !empty($data['Download limit']) ? (int)$data['Download limit'] : null,
            !empty($data['Download expiry days']) ? (int)$data['Download expiry days'] : null,
            !empty($data['Parent']) ? (int)$data['Parent'] : null,
            trim($data['Grouped products'] ?? ''),
            trim($data['Upsells'] ?? ''),
            trim($data['Cross-sells'] ?? ''),
            trim($data['External URL'] ?? ''),
            trim($data['Button text'] ?? ''),
            !empty($data['Position']) ? (int)$data['Position'] : null,
            trim($data['Brands'] ?? ''),
            trim($data['Attribute 1 name'] ?? ''),
            trim($data['Attribute 1 value(s)'] ?? ''),
            (int)(($data['Attribute 1 visible'] ?? '') === '1'),
            (int)(($data['Attribute 1 global'] ?? '') === '1'),
            (int)(($data['Attribute 1 default'] ?? '') === '1'), // Fixed: Use ?? '' for Attribute 1 default
            trim($data['Attribute 2 name'] ?? ''),
            trim($data['Attribute 2 value(s)'] ?? ''),
            (int)(($data['Attribute 2 visible'] ?? '') === '1'),
            (int)(($data['Attribute 2 global'] ?? '') === '1'),
            (int)(($data['Attribute 2 default'] ?? '') === '1'),
            trim($data['Attribute 3 name'] ?? ''),
            trim($data['Attribute 3 value(s)'] ?? ''),
            (int)(($data['Attribute 3 visible'] ?? '') === '1'),
            (int)(($data['Attribute 3 global'] ?? '') === '1'),
            (int)(($data['Attribute 3 default'] ?? '') === '1'),
            !empty($data['Meta: _min_variation_price']) ? (float)$data['Meta: _min_variation_price'] : null,
            !empty($data['Meta: _max_variation_price']) ? (float)$data['Meta: _max_variation_price'] : null,
            !empty($data['Meta: _min_price_variation_id']) ? (int)$data['Meta: _min_price_variation_id'] : null,
            !empty($data['Meta: _max_price_variation_id']) ? (int)$data['Meta: _max_price_variation_id'] : null,
            !empty($data['Meta: _min_variation_regular_price']) ? (float)$data['Meta: _min_variation_regular_price'] : null,
            !empty($data['Meta: _max_variation_regular_price']) ? (float)$data['Meta: _max_variation_regular_price'] : null,
            !empty($data['Meta: _min_regular_price_variation_id']) ? (int)$data['Meta: _min_regular_price_variation_id'] : null,
            !empty($data['Meta: _max_regular_price_variation_id']) ? (int)$data['Meta: _max_regular_price_variation_id'] : null,
            !empty($data['Meta: _min_variation_sale_price']) ? (float)$data['Meta: _min_variation_sale_price'] : null,
            !empty($data['Meta: _max_variation_sale_price']) ? (float)$data['Meta: _max_variation_sale_price'] : null,
            !empty($data['Meta: _min_sale_price_variation_id']) ? (int)$data['Meta: _min_sale_price_variation_id'] : null,
            !empty($data['Meta: _max_sale_price_variation_id']) ? (int)$data['Meta: _max_sale_price_variation_id'] : null,
            !empty($data['Meta: _yoast_wpseo_primary_product_cat']) ? (int)$data['Meta: _yoast_wpseo_primary_product_cat'] : null,
            !empty($data['Meta: _yoast_wpseo_primary_product_brand']) ? (int)$data['Meta: _yoast_wpseo_primary_product_brand'] : null,
            trim($data['Meta: _ywpc_enabled'] ?? ''),
            !empty($data['Meta: _ywpc_sale_price_dates_from']) ? (int)$data['Meta: _ywpc_sale_price_dates_from'] : null,
            !empty($data['Meta: _ywpc_sale_price_dates_to']) ? (int)$data['Meta: _ywpc_sale_price_dates_to'] : null,
            !empty($data['Meta: _yst_prominent_words_version']) ? (int)$data['Meta: _yst_prominent_words_version'] : null,
            trim($data['Meta: _yoast_wpseo_focuskeywords'] ?? ''),
            trim($data['Meta: _yoast_wpseo_keywordsynonyms'] ?? ''),
            trim($data['Meta: _wc_gla_mc_status'] ?? ''),
            trim($data['Meta: _wc_gla_sync_status'] ?? ''),
            trim($data['Meta: _wc_gla_visibility'] ?? ''),
            !empty($data['Meta: shopengine_product_views_count']) ? (int)$data['Meta: shopengine_product_views_count'] : null,
            trim($data['Meta: scwf_sales_countdown'] ?? ''),
            trim($data['Meta: scwf_sales_date'] ?? ''),
            trim($data['Meta: scwf_sales_hour'] ?? ''),
            trim($data['Meta: scwf_sales_minute'] ?? ''),
            trim($data['Meta: scwf_sales_second'] ?? ''),
            trim($data['Meta: scwf_sales_day'] ?? ''),
            !empty($data['Meta: _wp_old_date']) ? date('Y-m-d', strtotime($data['Meta: _wp_old_date'])) : null,
            trim($data['Meta: _last_editor_used_jetpack'] ?? ''),
            !empty($data['Meta: cartflows_redirect_flow_id']) ? (int)$data['Meta: cartflows_redirect_flow_id'] : null,
            trim($data['Meta: cartflows_add_to_cart_text'] ?? ''),
            !empty($data['Meta: _yoast_wpseo_estimated-reading-time-minutes']) ? (int)$data['Meta: _yoast_wpseo_estimated-reading-time-minutes'] : null,
            !empty($data['Meta: _wc_gla_synced_at']) ? (int)$data['Meta: _wc_gla_synced_at'] : null,
            trim($data['Meta: _aioseo_description'] ?? ''),
            trim($data['Meta: _aioseo_keywords'] ?? ''),
            trim($data['Meta: _aioseo_og_article_section'] ?? ''),
            trim($data['Meta: _aioseo_og_article_tags'] ?? ''),
            (int)(($data['Meta: saswp_schema_type_product_pros_enable_cons'] ?? '') === '1'),
            (int)(($data['Meta: om_disable_all_campaigns'] ?? '') === '1'),
            trim($data['Meta: _aioseo_title'] ?? ''),
            !empty($data['Meta: _yoast_wpseo_wordproof_timestamp']) ? (int)$data['Meta: _yoast_wpseo_wordproof_timestamp'] : null,
            trim($data['Meta: basel_sguide_select'] ?? ''),
            !empty($data['Meta: basel_total_stock_quantity']) ? (int)$data['Meta: basel_total_stock_quantity'] : null,
            trim($data['Meta: _product_360_image_gallery'] ?? ''),
            trim($data['Meta: _basel_main_layout'] ?? ''),
            trim($data['Meta: _basel_sidebar_width'] ?? ''),
            trim($data['Meta: _basel_custom_sidebar'] ?? ''),
            trim($data['Meta: _basel_product_design'] ?? ''),
            trim($data['Meta: _basel_single_product_style'] ?? ''),
            trim($data['Meta: _basel_product-background'] ?? ''),
            trim($data['Meta: _basel_extra_content'] ?? ''),
            trim($data['Meta: _basel_extra_position'] ?? ''),
            trim($data['Meta: _basel_product_custom_tab_title'] ?? ''),
            trim($data['Meta: _basel_product_custom_tab_content'] ?? ''),
            trim($data['Meta: _basel_new_label_date'] ?? ''),
            trim($data['Meta: _basel_swatches_attribute'] ?? ''),
            trim($data['Meta: _basel_product_video'] ?? ''),
            trim($data['Meta: _basel_product_hashtag'] ?? ''),
            trim($data['Meta: _wpb_vc_js_status'] ?? ''),
            trim($data['Meta: xfgmc_google_product_category'] ?? ''),
            trim($data['Meta: _xfgmc_fb_product_category'] ?? ''),
            trim($data['Meta: _xfgmc_tax_category'] ?? ''),
            (int)(($data['Meta: xfgmc_identifier_exists'] ?? '') === '1'),
            (int)(($data['Meta: xfgmc_adult'] ?? '') === '1'),
            trim($data['Meta: xfgmc_condition'] ?? ''),
            (int)(($data['Meta: _xfgmc_is_bundle'] ?? '') === '1'),
            !empty($data['Meta: _xfgmc_multipack']) ? (int)$data['Meta: _xfgmc_multipack'] : null,
            trim($data['Meta: _xfgmc_shipping_label'] ?? ''),
            trim($data['Meta: _xfgmc_unit_pricing_measure'] ?? ''),
            trim($data['Meta: _xfgmc_unit_pricing_base_measure'] ?? ''),
            trim($data['Meta: _xfgmc_return_rule_label'] ?? ''),
            trim($data['Meta: _xfgmc_store_code'] ?? ''),
            !empty($data['Meta: _xfgmc_min_handling_time']) ? (int)$data['Meta: _xfgmc_min_handling_time'] : null,
            !empty($data['Meta: _xfgmc_max_handling_time']) ? (int)$data['Meta: _xfgmc_max_handling_time'] : null,
            trim($data['Meta: xfgmc_custom_label_0'] ?? ''),
            trim($data['Meta: xfgmc_custom_label_1'] ?? ''),
            trim($data['Meta: xfgmc_custom_label_2'] ?? ''),
            trim($data['Meta: xfgmc_custom_label_3'] ?? ''),
            trim($data['Meta: xfgmc_custom_label_4'] ?? ''),
            !empty($data['Meta: _wds_primary_term_product_cat']) ? (int)$data['Meta: _wds_primary_term_product_cat'] : null,
            trim($data['Meta: _wds_save_primary_product_cat_nonce'] ?? ''),
            trim($data['Meta: _wds_trimmed_excerpt'] ?? ''),
            !empty($data['Meta: berocket_post_order']) ? (int)$data['Meta: berocket_post_order'] : null,
            trim($data['Meta: _yoast_wpseo_metadesc'] ?? ''),
            !empty($data['Meta: _yoast_wpseo_content_score']) ? (int)$data['Meta: _yoast_wpseo_content_score'] : null,
            trim($data['Meta: slide_template'] ?? ''),
            trim($data['Meta: _yoast_wpseo_focuskw'] ?? ''),
            !empty($data['Meta: _yoast_wpseo_linkdex']) ? (int)$data['Meta: _yoast_wpseo_linkdex'] : null,
            trim($data['Meta: rs_page_bg_color'] ?? ''),
            trim($data['Meta: _wds_focus-keywords'] ?? ''),
            trim($data['Meta: _iconic_wsb_fbt_discount_type'] ?? ''),
            trim($data['Meta: _iconic_wsb_fbt_title'] ?? ''),
            trim($data['Meta: _iconic_wsb_fbt_sales_pitch'] ?? ''),
            !empty($data['Meta: _iconic_wsb_fbt_discount_value']) ? (float)$data['Meta: _iconic_wsb_fbt_discount_value'] : null,
            trim($data['Meta: _sale_price_times_from'] ?? ''),
            trim($data['Meta: _sale_price_times_to'] ?? ''),
            trim($data['Meta: _woo_ctr_select_countdown_timer'] ?? ''),
            (int)(($data['Meta: _woo_ctr_enable_progress_bar'] ?? '') === '1'),
            !empty($data['Meta: _woo_ctr_progress_bar_goal']) ? (int)$data['Meta: _woo_ctr_progress_bar_goal'] : null,
            !empty($data['Meta: _woo_ctr_progress_bar_initial']) ? (int)$data['Meta: _woo_ctr_progress_bar_initial'] : null,
            trim($data['FBT Title'] ?? ''),
            trim($data['FBT Description'] ?? ''),
            trim($data['FBT Products'] ?? ''),
            (int)(($data['FBT Unchecked by Default'] ?? '') === '1'),
            !empty($data['FBT Discount Value']) ? (float)$data['FBT Discount Value'] : null,
            trim($data['FBT Discount Type'] ?? ''),
            trim($data['After Add to Cart Popup Products'] ?? ''),
            !empty($data['Meta: _wds_primary_term_product_brand']) ? (int)$data['Meta: _wds_primary_term_product_brand'] : null,
            trim($data['Meta: _wds_save_primary_product_brand_nonce'] ?? ''),
            (int)(($data['Meta: _wpas_done_all'] ?? '') === '1'),
            (int)(($data['Meta: _basel_new_label'] ?? '') === '1')
        ];

        // Execute main product insertion
        $productStmt->execute($productValues);
        
        // --- Handle normalized data ---

        // Categories
        if (!empty($data['Categories'])) {
            $categories = explode('>', $data['Categories']);
            foreach ($categories as $category) {
                $trimmedCategory = trim($category);
                if (!empty($trimmedCategory)) {
                    $categoryStmt->execute([$productId, $trimmedCategory]);
                }
            }
        }

        // Tags
        if (!empty($data['Tags'])) {
            $tags = explode(',', $data['Tags']);
            foreach ($tags as $tag) {
                $trimmedTag = trim($tag);
                if (!empty($trimmedTag)) {
                    $tagStmt->execute([$productId, $trimmedTag]);
                }
            }
        }

        // Images
        if (!empty($data['Images'])) {
            $images = explode(',', $data['Images']);
            foreach ($images as $image) {
                $trimmedImage = trim($image);
                if (!empty($trimmedImage)) {
                    $imageStmt->execute([$productId, $trimmedImage]);
                }
            }
        }

        // Attributes (Loop for Attribute 1, 2, 3)
        for ($i = 1; $i <= 3; $i++) {
            $attrNameKey = "Attribute {$i} name";
            $attrValuesKey = "Attribute {$i} value(s)";
            $attrVisibleKey = "Attribute {$i} visible";
            $attrGlobalKey = "Attribute {$i} global";
            $attrDefaultKey = "Attribute {$i} default"; // CSV column for default attribute

            // Use null coalescing operator (?? '') to avoid undefined index errors
            // if a column is missing in a specific row.
            if (!empty($data[$attrNameKey] ?? '')) {
                $attrStmt->execute([
                    $productId,
                    trim($data[$attrNameKey] ?? ''),
                    trim($data[$attrValuesKey] ?? ''),
                    (int)(($data[$attrVisibleKey] ?? '') === '1'),
                    (int)(($data[$attrGlobalKey] ?? '') === '1'),
                    (int)(($data[$attrDefaultKey] ?? '') === '1') // Treat missing as 0 (false)
                ]);
            }
        }
        $importedRowCount++;
    }

    $db->commit();
    echo "Import completed successfully! Total rows imported: " . $importedRowCount . "\n";

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Import failed: " . $e->getMessage() . " at row #" . $rowNumber . "\n");
    echo "Import failed: " . $e->getMessage() . "\n";
    echo "Error occurred at row: " . $rowNumber . "\n";
} finally {
    fclose($csvFile);
    echo "CSV file closed.\n";
}

?>