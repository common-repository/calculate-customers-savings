<?php
/**
 * Plugin Name: Calculate Customer's Savings
 * Plugin URI: https://wordpress.org/plugins/calculate-customers-savings/
 * Description: Show customers how much they can save.
 * Version: 1.0.0
 * Author: Ramon Ahnert
 * Author URI: https://profiles.wordpress.org/rahmohn/
 * Text Domain: calculate-customers-savings
 * Requires at least: 5.9
 * Requires PHP: 7.2
 *
 * @package CalculateCustomersSavings
 */

namespace Calculate_Customers_Savings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', with_namespace( 'enqueue_assets' ) );
add_action( 'wp_head', with_namespace( 'add_style' ) );
add_action( 'woocommerce_cart_totals_before_order_total', with_namespace( 'output_savings_total' ) );
add_action( 'woocommerce_review_order_before_order_total', with_namespace( 'output_savings_total' ) );

add_filter( 'woocommerce_cart_item_price', with_namespace( 'get_price_valid_till_html' ), 10, 2 );
add_filter( 'woocommerce_cart_item_subtotal', with_namespace( 'update_cart_item_subtotal' ), 10, 2 );
add_filter( 'woocommerce_cart_product_subtotal', with_namespace( 'get_product_subtotal' ), 10, 3 );
add_filter( 'woocommerce_cart_subtotal', with_namespace( 'get_subtotal' ) );

/**
 * Add namespace to function name.
 *
 * @param string $function_name The function name.
 *
 * @return string
 */
function with_namespace( $function_name ) {
	return __NAMESPACE__ . '\\' . $function_name;
}

/**
 * Enqueue assets (scripts and styles).
 */
function enqueue_assets() {
	wp_enqueue_style( 'dashicons' );
}

/**
 * Add CSS styles to the cart and checkout page.
 */
function add_style() {
	if ( ! is_cart() && ! is_checkout() ) {
		return;
	}

	?>
	<style>
		.product-price__wrapper {
			display: flex;
			align-items: center;
		}

		.woocommerce-Price-amount {
			margin-right: 4px;
		}

	</style>
	<?php
}

/**
 * Get the `Price valid till` HTML markup.
 *
 * @param string $price The price.
 * @param array  $cart_item The cart item.
 * @return string
 */
function get_price_valid_till_html( $price, $cart_item ) {
	if ( empty( $cart_item['data'] ) ) {
		return $price;
	}

	$product = $cart_item['data'];

	if ( ! is_a( $product, 'WC_Product' ) ) {
		return $price;
	}

	if ( ! $product->is_on_sale() ) {
		return $price;
	}

	/**
	 * Filter whether it should show the `Price valid till` HTML markup.
	 *
	 * @since 1.0.0
	 * @hook ccs_skip_valid_till_html_element
	 *
	 * @param  bool       $skip      Should skip? Default: false.
	 * @param  string     $price     The price.
	 * @param  array      $cart_item The cart item.
	 * @param  WC_Product $product   The product.
	 * @return bool New value
	 */
	$skip = apply_filters( 'ccs_skip_valid_till_html_element', false, $price, $cart_item, $product );

	if ( $skip ) {
		return $price;
	}

	$allowed_html = array(
		'bdi'  => array(),
		'del'  => array(
			'aria-hidden' => array(),
		),
		'ins'  => array(),
		'span' => array(
			'class' => array(),
		),
	);

	$date_on_sale_to           = $product->get_date_on_sale_to();
	$date_on_sale_to_formatted = '';

	if ( ! empty( $date_on_sale_to ) ) {
		// translators: This format is a fallback if `date_format` option is not available.
		$default_date_format       = __( 'F j, Y', 'calculate-customers-savings' );
		$date_on_sale_to_formatted = $date_on_sale_to->format( get_option( 'date_format', $default_date_format ) );
	}

	if ( is_cart() ) {
		$price = $product->get_price_html();
	}

	$icon_class = 'dashicons dashicons-clock product-price__clock';
	/**
	 * Filter the classes used in the price valid till icon.
	 *
	 * By default, it uses the `dashicons dashicons-clock` classes that
	 * renders a clock icon.
	 *
	 * @see https://developer.wordpress.org/resource/dashicons/#clock
	 *
	 * @since 1.0.0
	 * @hook ccs_price_valid_till_icon_class
	 * @param  string name The description.
	 * @param  WC_Product name The description.
	 * @param  string name The description.
	 * @param  array name The description.
	 * @return string New value
	 */
	$icon_class = apply_filters( 'ccs_price_valid_till_icon_class', $icon_class, $product, $price, $cart_item );

	ob_start();
	?>
	<div class="product-price__wrapper">
		<?php echo wp_kses( $price, $allowed_html ); ?>

		<?php if ( ! empty( $date_on_sale_to_formatted ) ) : ?>
			<span
				class="dashicons dashicons-clock product-price__clock"
				title="
				<?php
				echo sprintf(
					// translators: %s: The date until sales price is valid.
					esc_attr( __( 'Price valid till %s.', 'calculate-customers-savings' ) ),
					esc_attr( $date_on_sale_to_formatted )
				);
				?>
				"
			>
			</span>
		<?php endif; ?>
	</div>
	<?php

	/**
	 * Filter the `Price valid till` HTML markup.
	 *
	 * @since 1.0.0
	 * @hook ccs_price_valid_till_html_markup
	 * @param  string     $html_markup The HTML markup.
	 * @param  string     $price       The price.
	 * @param  array      $cart_item   The cart item.
	 * @param  WC_Product $product     The product.
	 * @return type New value
	 */
	$html_markup = apply_filters( 'ccs_price_valid_till_html_markup', ob_get_clean(), $price, $cart_item, $product );

	return $html_markup;
}

/**
 * Add `Price valid till` HTML markup to the cart item subtotal
 * in the Checkout page.
 *
 * @param string $price The price.
 * @param array  $cart_item The cart item.
 * @return string
 */
function update_cart_item_subtotal( $price, $cart_item ) {
	if ( ! is_checkout() ) {
		return $price;
	}

	return get_price_valid_till_html( $price, $cart_item );
}

/**
 * Get the subtotal regular price.
 *
 * @return float
 */
function get_cart_regular_price_subtotal() {
	$cart_regular_price_subtotal = 0;

	foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
		$product = $cart_item['data'];

		$cart_regular_price_subtotal += $product->get_regular_price() * $cart_item['quantity'];
	}

	/**
	 * Filter the subtotal regular price of the cart. This is the sum of all
	 * regular prices of all products in the cart.
	 *
	 * @since 1.0.0
	 * @hook ccs_cart_regular_price_subtotal
	 * @param  float name The description.
	 * @return float New value
	 */
	$cart_regular_price_subtotal = apply_filters( 'ccs_cart_regular_price_subtotal', $cart_regular_price_subtotal );

	return $cart_regular_price_subtotal;
}

/**
 * Get savings.
 *
 * @return float
 */
function get_savings() {
	$cart_regular_price_subtotal = get_cart_regular_price_subtotal();

	if ( empty( $cart_regular_price_subtotal ) ) {
		return apply_filters( 'ccs_savings', 0 );
	}

	$savings = $cart_regular_price_subtotal - WC()->cart->get_cart_contents_total();

	if ( $savings <= 0 ) {
		return apply_filters( 'ccs_savings', 0 );
	}

	/**
	 * Filter the savings.
	 *
	 * @since 1.0.0
	 * @hook ccs_savings
	 * @param  float $savings The savings.
	 * @return float New value
	 */
	$savings = apply_filters( 'ccs_savings', $savings );

	return $savings;
}

/**
 * Output savings.
 *
 * @return void
 */
function output_savings_total() {
	$savings = get_savings();

	if ( empty( $savings ) ) {
		return;
	}

	$allowed_html = array(
		'del'  => array(),
		'span' => array(
			'class' => array(),
		),
	);
	/**
	 * Filter savings total allowed HTML.
	 *
	 * The savings HTML is sanitized using `wp_kses_post()`. If
	 * you add a new HTML element, make sure to add it to the
	 * allowed HTML.
	 *
	 * @since 1.0.0
	 * @hook ccs_savings_total_html
	 * @param  array $allowed_html The formatted savings total HTML.
	 * @param  float  $savings      The savings.
	 * @return string New value
	 */
	$allowed_html = apply_filters( 'ccs_savings_total_allowed_html', $allowed_html, $savings );

	/**
	 * Filter formatted savings total HTML.
	 *
	 * @since 1.0.0
	 * @hook ccs_savings_total_html
	 * @param  string $savings_html The formatted savings total HTML.
	 * @param  float  $savings      The savings.
	 * @return string New value
	 */
	$savings_html = apply_filters( 'ccs_savings_total_html', wc_price( $savings ), $savings );

	/**
	 * Filter savings total title. Default: `Savings`.
	 *
	 * @since 1.0.0
	 * @hook ccs_savings_total_title
	 * @param  float  $savings The savings.
	 * @return string New value
	 */
	$title = apply_filters( 'ccs_savings_total_title', __( 'Savings', 'calculate-customers-savings' ), $savings );

	?>
	<tr class="order-savings">
		<th>
			<?php
				/**
				 * Fires before Savings Total title.
				 *
				 * @since 1.0.0
				 * @hook ccs_before_savings_total_title
				 * @param  string $title   The description.
				 * @param  float  $savings The description.
				 */
				do_action( 'ccs_before_savings_total_title', $title, $savings );
			?>
			<?php echo esc_html( $title ); ?>
			<?php
				/**
				 * Fires after Savings Total title.
				 *
				 * @since 1.0.0
				 * @hook ccs_after_savings_total_title
				 * @param  string $title   The description.
				 * @param  float  $savings The description.
				 */
				do_action( 'ccs_after_savings_total_title', $title, $savings );
			?>
		</th>
		<td data-title="<?php echo esc_attr( $title ); ?>">
			<?php
				/**
				 * Fires before Savings Total content.
				 *
				 * @since 1.0.0
				 * @hook ccs_before_savings_total_content
				 * @param  string $savings_html The formatted savings to total HTML.
				 * @param  float  $savings      The description.
				 */
				do_action( 'ccs_before_savings_total_content', $savings_html, $savings );
			?>
			<?php
			echo wp_kses(
				$savings_html,
				$allowed_html
			);
			?>
			<?php
				/**
				 * Fires after Savings Total content.
				 *
				 * @since 1.0.0
				 * @hook ccs_after_savings_total_content
				 * @param  string $savings_html The formatted savings to total HTML.
				 * @param  float  $savings      The description.
				 */
				do_action( 'ccs_after_savings_total_content', $savings_html, $savings );
			?>
		</td>
	</tr>
	<?php
}

/**
 * Get the product subtotal.
 *
 * @param string     $product_subtotal The product subtotal.
 * @param WC_Product $product The product.
 * @param int        $quantity The quantity.
 * @return string
 */
function get_product_subtotal( $product_subtotal, $product, $quantity ) {
	if ( ! is_checkout() ) {
		return $product_subtotal;
	}

	if ( ! $product->is_on_sale() ) {
		return $product_subtotal;
	}

	$product_regular_price_subtotal = wc_price( $product->get_regular_price() * $quantity );

	$formatted_product_subtotal = '<del>' . $product_regular_price_subtotal . '</del> ' . $product_subtotal;
	/**
	 * Filter product subtotal.
	 *
	 * @since 1.0.0
	 * @hook ccs_product_subtotal
	 * @param  string     $formatted_product_subtotal     The formatted product subtotal.
	 * @param  string     $product_regular_price_subtotal The product regular price subtotal.
	 * @param  string     $product_subtotal               The product subtotal (on sale price).
	 * @param  WC_Product $product                        The product.
	 * @param  int        $quantity                       The quantity.
	 * @return type New value
	 */
	$formatted_product_subtotal = apply_filters( 'ccs_product_subtotal', $formatted_product_subtotal, $product_regular_price_subtotal, $product_subtotal, $product, $quantity );

	return '<del>' . $product_regular_price_subtotal . '</del> ' . $product_subtotal;
}

/**
 * Get the cart subtotal.
 *
 * @param string $cart_subtotal The cart subtotal.
 * @return string
 */
function get_subtotal( $cart_subtotal ) {
	if ( empty( get_savings() ) ) {
		return $cart_subtotal;
	}

	$cart_regular_price_subtotal = get_cart_regular_price_subtotal();

	if ( empty( $cart_regular_price_subtotal ) ) {
		return $cart_subtotal;
	}

	$formatted_cart_subtotal = '<del>' . $cart_regular_price_subtotal . '</del> ' . $cart_subtotal;
	/**
	 * Filter cart subtotal.
	 *
	 * @since 1.0.0
	 * @hook ccs_cart_subtotal
	 * @param  string $formatted_cart_subtotal     The formatted cart subtotal.
	 * @param  string $cart_regular_price_subtotal The cart regular price subtotal.
	 * @param  string $cart_subtotal               The cart subtotal (on sale price).
	 * @return type New value
	 */
	$formatted_cart_subtotal = apply_filters( 'ccs_cart_subtotal', $formatted_cart_subtotal, $cart_regular_price_subtotal, $cart_subtotal );

	return $formatted_cart_subtotal;
}
