<?php
/**
 * Builds the Bloem Sefa plant shop inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Plants come from products.json (next to this file), photos from /wordpress/bs-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized (720×900 webp), so skip making thumbnails of each one.
// The theme reads this option and serves the photos as they are. (Real hosting can regenerate thumbnails later.)
update_option( 'bs_fast_images', 'yes' );
add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
add_filter( 'big_image_size_threshold', '__return_false' );
add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
add_filter( 'woocommerce_resize_images', '__return_false' );
// The import runs without a logged-in user, so WordPress would store "&" in product names as "&amp;".
kses_remove_filters();

// Store settings.
foreach ( [
	'blogname'                               => 'Bloem Sefa',
	'blogdescription'                        => 'We grow it so that you can own it – pop-up flower shop & boutique garden centre, Karen, Nairobi',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'Karen',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_email_from_name'            => 'Bloem Sefa',
	'woocommerce_manage_stock'               => 'yes',
	'woocommerce_notify_low_stock_amount'    => '3',
	'woocommerce_notify_no_stock_amount'     => '0',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => '',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_enable_coupons'             => 'yes',
	// The plant filters query the attribute terms directly, so they work the moment a plant is saved.
	'woocommerce_attribute_lookup_enabled'   => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Categories (menu order = order in the header and on the home page).
$cat = [];
$i   = 0;
foreach ( [
	'flowering-plants' => [ 'Flowering Plants', 'Dahlias, geraniums, fuchsias, petunias, daisies and seasonal poinsettias, grown in our greenhouse and sold in bloom.' ],
	'indoor-plants'    => [ 'Indoor & Foliage', 'Easy houseplants for living rooms, desks and shelves, including pet-safe picks.' ],
	'succulents'       => [ 'Succulents', 'Echeverias and other succulents for sunny windowsills, desks and gifts.' ],
	'gifts'            => [ 'Gifts & Custom', 'Succulent hearts, wood-slice gardens and Christmas poinsettias. Add gift wrap and a hand-written card to any plant.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Plant filters: the shop filters, the "Shop by space" tiles and the plant matcher read these.
$filters = [
	'light' => [ 'Light', [ 'low-light' => 'Low light', 'bright-indirect' => 'Bright, indirect light', 'full-sun' => 'Full sun' ] ],
	'space' => [ 'Space', [ 'indoor' => 'Indoors', 'office' => 'Office & desk', 'balcony' => 'Balcony & patio', 'garden' => 'Garden & landscape', 'hanging' => 'Hanging & trailing', 'gift' => 'Gifts' ] ],
	'water' => [ 'Watering', [ 'rarely' => 'Every 1–3 weeks', 'weekly' => 'About once a week', 'often' => '2–3 times a week' ] ],
	'care'  => [ 'Care level', [ 'beginner' => 'Beginner friendly', 'easy' => 'Easy care' ] ],
	'pets'  => [ 'Pets', [ 'pet-safe' => 'Pet-safe' ] ],
];
$terms = [];
foreach ( $filters as $attr => [ $label, $options ] ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $attr ) ) {
		wc_create_attribute( [ 'name' => $label, 'slug' => $attr, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ] );
	}
	$tax = wc_attribute_taxonomy_name( $attr );
	if ( ! taxonomy_exists( $tax ) ) {
		register_taxonomy( $tax, [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ] );
	}
	$i = 0;
	foreach ( $options as $slug => $name ) {
		$t = term_exists( $slug, $tax ) ?: wp_insert_term( $name, $tax, [ 'slug' => $slug ] );
		$terms[ $attr ][ $slug ] = (int) $t['term_id'];
		update_term_meta( $terms[ $attr ][ $slug ], 'order', $i++ );
	}
}
delete_transient( 'wc_attribute_taxonomies' );

// Delivery. PLACEHOLDER prices until Bloem Sefa confirms them.
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( 'Nairobi' );
$zone->add_location( 'KE:KE30', 'state' );
$zone->save();
// Free delivery first, so it is picked by default once the cart qualifies.
$id = $zone->add_shipping_method( 'free_shipping' );
update_option( "woocommerce_free_shipping_{$id}_settings", [ 'title' => 'Free Nairobi delivery', 'requires' => 'min_amount', 'min_amount' => '5000' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Rider – Karen, Lang\'ata & Ngong Road', 'cost' => '300', 'tax_status' => 'none' ] );
$id = $zone->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Rider – rest of Nairobi', 'cost' => '500', 'tax_status' => 'none' ] );

$near = new WC_Shipping_Zone();
$near->set_zone_name( 'Nairobi environs' );
foreach ( [ 'KE:KE10', 'KE:KE13' ] as $county ) { // Kajiado (Ngong, Kitengela, Rongai) and Kiambu.
	$near->add_location( $county, 'state' );
}
$near->save();
$id = $near->add_shipping_method( 'flat_rate' );
update_option( "woocommerce_flat_rate_{$id}_settings", [ 'title' => 'Rider – Ngong, Rongai, Kiambu & environs', 'cost' => '800', 'tax_status' => 'none' ] );

update_option( 'woocommerce_pickup_location_settings', [ 'enabled' => 'yes', 'title' => 'Collect at our next pop-up market (free)', 'tax_status' => 'none', 'cost' => '' ] );
update_option( 'pickup_location_pickup_locations', [ [
	'name'    => 'Bloem Sefa pop-up stand',
	'address' => [ 'address_1' => 'Our next pop-up market', 'city' => 'Nairobi', 'state' => 'KE30', 'postcode' => '', 'country' => 'KE' ],
	'details' => 'We\'ll WhatsApp you the date and venue of our next pop-up and keep your plants aside. Pickup from our Karen Garden Centre starts when it opens.',
	'enabled' => true,
] ] );

// Payment: pay on delivery or at pickup until M-Pesa Express is connected.
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay on delivery or at pickup (M-Pesa or cash)',
	'description'        => 'Pay by M-Pesa or cash when your plants arrive, or when you collect them at our pop-up.',
	'instructions'       => 'We\'ll call or WhatsApp you to confirm your plants, delivery day and time.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );

// Plants: from Bloem Sefa's Instagram, built by catalogue/build.mjs.
$products = json_decode( file_get_contents( __DIR__ . '/products.json' ), true );

// The photos are all 720×900 webp files made by catalogue/build.mjs, so they are copied straight into
// uploads and registered with their known size. (media_handle_sideload opens every image, which made
// the demo take several minutes to build.)
function bs_attach_images( $slug, $name ) {
	$files = glob( "/wordpress/bs-images/$slug*.webp" ) ?: [];
	$files = array_values( array_filter( $files, fn( $f ) => preg_match( '#/' . preg_quote( $slug, '#' ) . '(-\d+)?\.webp$#', $f ) ) );
	// slug.webp is the main photo, then slug-2.webp, slug-3.webp…
	$num = fn( $f ) => preg_match( '#-(\d+)\.webp$#', substr( $f, strlen( $slug ) ), $m ) ? (int) $m[1] : 1;
	usort( $files, fn( $a, $b ) => $num( basename( $a ) ) <=> $num( basename( $b ) ) );
	$up  = wp_upload_dir();
	$ids = [];
	foreach ( $files as $i => $file ) {
		$base = basename( $file );
		$dest = trailingslashit( $up['path'] ) . $base;
		if ( ! copy( $file, $dest ) ) {
			continue;
		}
		$size = @getimagesize( $dest ) ?: [ 720, 900 ];
		$att  = wp_insert_attachment( [
			'post_mime_type' => 'image/webp',
			'post_title'     => $name . ( $i ? ' – photo ' . ( $i + 1 ) : '' ),
			'post_status'    => 'inherit',
			'guid'           => trailingslashit( $up['url'] ) . $base,
		], $dest, 0, false, false );
		if ( ! $att ) {
			continue;
		}
		wp_update_attachment_metadata( $att, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
		update_post_meta( $att, '_wp_attachment_image_alt', $name );
		$ids[] = $att;
	}
	return $ids;
}

// Count terms once at the end instead of after every product.
wp_defer_term_counting( true );
// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );
$started = microtime( true );

$by_slug = [];
foreach ( $products as $order => $d ) {
	$variable = ! empty( $d['variations'] );
	$imgs     = bs_attach_images( $d['slug'], $d['name'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_description( wp_kses_post( $d['desc'] ) );
	$p->set_short_description( wp_kses_post( $d['short'] ) );
	$p->set_category_ids( array_map( fn( $s ) => $cat[ $s ], $d['cat'] ) );
	$p->set_featured( ! empty( $d['featured'] ) );
	$p->update_meta_data( '_bs_badge', $d['badge'] );
	$p->update_meta_data( '_bs_made', $d['made'] ? 'yes' : 'no' );
	// Pots and saucers suit single potted plants, not sets, trios or made-to-order arrangements.
	$potted = ! $d['made'] && ! in_array( $d['slug'], [ 'echeveria-succulent', 'mini-plant-trio' ], true );
	$p->update_meta_data( '_bs_pots', $potted ? 'yes' : 'no' );
	foreach ( $d['care'] as $key => $text ) {
		$p->update_meta_data( "_bs_care_$key", $text );
	}

	// Plant tags for the filters, as hidden attributes.
	$tags = [
		'light' => $d['light'],
		'space' => $d['space'],
		'water' => [ $d['water'] ],
		'care'  => [ $d['level'] ],
		'pets'  => $d['pets'] ? [ 'pet-safe' ] : [],
	];
	$attributes = [];
	// Options the customer picks (colour, pack).
	foreach ( $d['attributes'] ?? [] as $pos => $a ) {
		$attr = new WC_Product_Attribute();
		$attr->set_name( $a['name'] );
		$attr->set_options( $a['options'] );
		$attr->set_position( $pos );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$attributes[] = $attr;
	}
	$pos = 5;
	foreach ( $tags as $key => $slugs ) {
		if ( ! $slugs ) continue;
		$tax  = wc_attribute_taxonomy_name( $key );
		$attr = new WC_Product_Attribute();
		$attr->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
		$attr->set_name( $tax );
		$attr->set_options( array_map( fn( $s ) => $terms[ $key ][ $s ], $slugs ) );
		$attr->set_position( $pos++ );
		$attr->set_visible( false );
		$attr->set_variation( false );
		$attributes[] = $attr;
	}
	$p->set_attributes( $attributes );

	if ( ! $variable ) {
		$p->set_regular_price( $d['regular'] );
		$p->set_manage_stock( true );
		$p->set_stock_quantity( $d['made'] ? 5 : 12 ); // PLACEHOLDER stock.
	}
	if ( $imgs ) {
		$p->set_image_id( $imgs[0] );
		$p->set_gallery_image_ids( array_slice( $imgs, 1 ) );
	}
	if ( $variable ) {
		// Open on the first option, so the price and photo show straight away.
		$first = $d['variations'][0]['attrs'];
		$p->set_default_attributes( array_combine( array_map( 'sanitize_title', array_keys( $first ) ), array_values( $first ) ) );
	}
	$pid = $p->save();
	foreach ( $tags as $key => $slugs ) {
		wp_set_object_terms( $pid, array_map( fn( $s ) => $terms[ $key ][ $s ], $slugs ), wc_attribute_taxonomy_name( $key ) );
	}

	if ( $variable ) {
		foreach ( $d['variations'] as $n => $v ) {
			$var = new WC_Product_Variation();
			$var->set_parent_id( $pid );
			$var->set_menu_order( $n );
			$var->set_attributes( array_combine( array_map( 'sanitize_title', array_keys( $v['attrs'] ) ), array_values( $v['attrs'] ) ) );
			$var->set_regular_price( $v['regular'] );
			if ( $v['sale'] ) $var->set_sale_price( $v['sale'] );
			$var->set_manage_stock( true );
			$var->set_stock_quantity( 8 ); // PLACEHOLDER stock.
			if ( isset( $imgs[ $v['img'] ] ) ) $var->set_image_id( $imgs[ $v['img'] ] );
			$var->save();
		}
		WC_Product_Variable::sync( $pid );
	}
	$by_slug[ $d['slug'] ] = $pid;
}

// "Complete the look" suggestions.
foreach ( $products as $d ) {
	if ( empty( $d['upsells'] ) ) continue;
	// Written as meta directly: a full product save per product here doubled the import time.
	update_post_meta( $by_slug[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $by_slug[ $s ] ?? 0, $d['upsells'] ) ) ) );
}

// Pop-up markets, from the Instagram highlights. New ones are added under Pop-ups in the admin;
// one with a future date shows on the site with a countdown.
$theme_photos = get_stylesheet_directory_uri() . '/assets/photos/';
foreach ( [
	[ 'Peponi Pop-Up Plant Sale', '2023-04-16', '', '', 'Peponi House Preparatory School', 'Nairobi', 'Peponi House Preparatory School, Nairobi', 'popup-peponi', 'https://www.instagram.com/stories/highlights/17989979558170683/', 'Our very first pop-up sale. We brought flowers too, not just succulents, and the trailing fuchsia was the customers\' favourite.' ],
	[ 'Centenary Plant & Flower Show', '2023-06-09', '2023-06-11', '', 'Sarit Expo Centre', 'Westlands', 'Sarit Expo Centre, Nairobi', 'popup-sarit', 'https://www.instagram.com/stories/highlights/18095837962340412/', 'Three days at the Kenya Horticultural Society\'s centenary show, as Sarit celebrated 40 years. Thank you @gardeningkenya for organising.' ],
	[ 'KCB Pop-Up', '2023-06-30', '', '', 'KCB Group', 'Nairobi', '', 'popup-kcb', 'https://www.instagram.com/stories/highlights/18024216112549821/', 'Thank you @kcbgroup for the invite and for having us. Plenty of brown paper bags of flowers went home that day.' ],
	[ 'KSPCA Pop-Up', '2023-11-01', '', 'November 2023', 'Ngong Racecourse', 'Ngong Road', 'Ngong Racecourse, Nairobi', 'popup-kspca', 'https://www.instagram.com/stories/highlights/18042504409515114/', 'Thank you @kspca for having us, and to everyone who joined us regardless of the weather.' ],
	[ 'Shamba Christmas Market', '2023-12-02', '2023-12-03', '', 'Shamba Events', 'Loresho', 'Shamba Events, Loresho, Nairobi', 'popup-shamba', 'https://www.instagram.com/stories/highlights/18014906983952029/', 'Annual flowers, trees, perennials, succulents, hanging plants and grasses, and our poinsettias, the customers\' favourite of the season.' ],
] as [ $title, $start, $end, $label, $venue, $area, $map, $photo, $ig, $note ] ) {
	$pid = wp_insert_post( [ 'post_type' => 'bs_popup', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $note ] );
	foreach ( [ 'date' => $start, 'end' => $end, 'label' => $label, 'venue' => $venue, 'area' => $area, 'map' => $map, 'photo' => $theme_photos . $photo . '.webp', 'ig' => $ig ] as $k => $v ) {
		update_post_meta( $pid, "_bs_$k", $v );
	}
}

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
update_option( 'bs_setup_seconds', round( microtime( true ) - $started, 1 ) );

// Pages.
function bs_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
bs_page( 'wishlist', 'Your wishlist', "<!-- wp:shortcode -->\n[bs_wishlist]\n<!-- /wp:shortcode -->" );
bs_page( 'plant-matcher', 'Find your plant', "<!-- wp:shortcode -->\n[bs_matcher]\n<!-- /wp:shortcode -->" );
// These pages have their own theme templates (page-<slug>.html).
bs_page( 'pop-ups', 'Pop-up markets', '' );
bs_page( 'garden-centre', 'Garden Centre', '' );
bs_page( 'about', 'Our story', '' );
bs_page( 'faqs', 'FAQs & plant care guide', '' );

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );
