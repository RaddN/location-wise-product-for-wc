<?php
/**
 * Location-wise Reviews (Class-based)
 *
 * - Stores selected store location with each product review
 * - Shows a "Reviews from your neighbours" section before Woo's default reviews inside the Reviews tab
 * - Optionally displays the reviewer's stored location under each review
 *
 * @package Multi Location Product & Inventory Management for WooCommerce Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'MULOPIMFWC_Location_Wise_Reviews' ) ) {

	class MULOPIMFWC_Location_Wise_Reviews {

		/**
		 * Version for cache-busting CSS.
		 * @var string
		 */
		const VERSION = '1.1.7.18';

		public function __construct() {
			$options = get_option( 'mulopimfwc_display_options', array() );
			if ( function_exists( 'mulopimfwc_is_location_reviews_enabled' ) && ! mulopimfwc_is_location_reviews_enabled( $options ) ) {
				return;
			}

			// Save location on review submit.
			add_action( 'comment_post', array( $this, 'save_location_on_review' ), 10, 3 );

			// Show location under each review (if enabled).
			add_action( 'woocommerce_review_after_comment_text', array( $this, 'maybe_output_review_location' ), 10, 1 );

			// Override Reviews tab to inject our neighbour block first.
			add_filter( 'woocommerce_product_tabs', array( $this, 'override_reviews_tab_callback' ), 50 );

			// Enqueue styles.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		/**
		 * Enqueue front-end CSS for location reviews.
		 */
		public function enqueue_assets() {
			// Only load on single product pages.
			if ( function_exists( 'is_product' ) && is_product() ) {
				// Path: plugin_root/assets/css/location-reviews.css (this file lives in includes/)
				$css_url = plugins_url( '../assets/css/location-reviews.css', __FILE__ );
				wp_enqueue_style( 'mulopimfwc-location-reviews', $css_url, array(), self::VERSION );
			}
		}

		/**
		 * Override the Reviews tab callback so we can print our block first,
		 * then render the default WooCommerce comments template.
		 *
		 * @param array $tabs
		 * @return array
		 */
		public function override_reviews_tab_callback( $tabs ) {
			if ( isset( $tabs['reviews'] ) ) {
				$tabs['reviews']['callback'] = array( $this, 'render_reviews_tab_with_neighbours' );
			}
			return $tabs;
		}

		/**
		 * Render: Neighbour reviews block, then the default comments template.
		 */
		public function render_reviews_tab_with_neighbours() {

			$options          = get_option( 'mulopimfwc_display_options', array() );
			$enabled_specific = isset( $options['location_specific_reviews'] ) && mulopimfwc_premium_feature() ? $options['location_specific_reviews'] : 'off';

			if ( 'on' === $enabled_specific && function_exists( 'is_product' ) && is_product() ) {
				$current_location = $this->get_current_location_slug();

				if ( ! empty( $current_location ) && 'all-products' !== $current_location ) {
					$product_id = get_the_ID();

					if ( $product_id ) {
						$args = array(
							'post_id'    => $product_id,
							'status'     => 'approve',
							'meta_key'   => '_mulopimfwc_location',
							'meta_value' => $current_location,
							'type'       => 'review', // Woo stores product reviews with comment_type 'review'
							'number'     => 6,
						);

						$neighbour_reviews = get_comments( $args );

						if ( ! empty( $neighbour_reviews ) ) {
							$location_name = $this->location_name_from_slug( $current_location );
							?>
							<div class="mulopimfwc-neighbour-reviews">
								<h3 class="mulopimfwc-neighbour-reviews__title">
									<?php echo esc_html( mulopimfwc_get_text_value( 'text_reviews_heading' ) ); ?>
								</h3>

								<?php if ( ! empty( $location_name ) ) : ?>
									<p class="mulopimfwc-neighbour-reviews__subtitle">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s location name */
												mulopimfwc_get_text_value( 'text_reviews_recent' ),
												$location_name
											)
										);
										?>
									</p>
								<?php endif; ?>

								<ul class="mulopimfwc-neighbour-reviews__list">
									<?php foreach ( $neighbour_reviews as $c ) :
										$rating = (int) get_comment_meta( $c->comment_ID, 'rating', true );
										$author = get_comment_author( $c );
										$date   = get_comment_date( wc_date_format(), $c );
										$text   = wpautop( wp_kses_post( $c->comment_content ) );
										?>
										<li class="mulopimfwc-neighbour-reviews__item">
											<div class="mulopimfwc-neighbour-reviews__meta">
												<strong class="mulopimfwc-neighbour-reviews__author"><?php echo esc_html( $author ); ?></strong>
												<span class="mulopimfwc-neighbour-reviews__dot" aria-hidden="true">·</span>
												<time class="mulopimfwc-neighbour-reviews__date" datetime="<?php echo esc_attr( get_comment_date( 'c', $c ) ); ?>">
													<?php echo esc_html( $date ); ?>
												</time>
												<?php if ( $rating > 0 ) : ?>
													<span class="mulopimfwc-neighbour-reviews__rating" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: star rating (1-5) */ _n( '%d star', '%d stars', $rating, 'multi-location-product-and-inventory-management-pro'  ), $rating ) ); ?>">
														<?php
														for ( $star_index = 1; $star_index <= 5; $star_index++ ) :
															$star_class = $star_index <= $rating ? ' is-filled' : '';
															?>
															<svg class="mulopimfwc-review-star<?php echo esc_attr( $star_class ); ?>" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
																<path d="M12 2.75l2.79 5.65 6.23.91-4.51 4.39 1.06 6.2L12 16.97 6.43 19.9l1.06-6.2-4.51-4.39 6.23-.91L12 2.75Z" />
															</svg>
														<?php endfor;
														?>
													</span>
												<?php endif; ?>
											</div>
											<div class="mulopimfwc-neighbour-reviews__content">
												<?php echo $text; // already escaped via wpautop/wp_kses_post ?>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
							<?php
						}
					}
				}
			}

			// Default WooCommerce reviews (list + form).
			comments_template();
		}

		/**
		 * Save the visitor's selected location when a product review is submitted.
		 *
		 * @param int   $comment_id
		 * @param int   $comment_approved
		 * @param array $commentdata
		 */
		public function save_location_on_review( $comment_id, $comment_approved, $commentdata = array() ) {
			$comment = get_comment( $comment_id );
			if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) ) {
				return; // Only on product reviews.
			}

			$slug = $this->get_current_location_slug();
			if ( empty( $slug ) || 'all-products' === $slug ) {
				return;
			}

			$name = $this->location_name_from_slug( $slug );

			update_comment_meta( $comment_id, '_mulopimfwc_location', $slug );
			update_comment_meta( $comment_id, '_mulopimfwc_location_name', $name );
		}

		/**
		 * Output the stored location under each review (if enabled via settings).
		 *
		 * @param WP_Comment $comment
		 */
		public function maybe_output_review_location( $comment ) {
			$options = get_option( 'mulopimfwc_display_options', array() );
			$show    = isset( $options['show_location_in_reviews'] ) && mulopimfwc_premium_feature() ? $options['show_location_in_reviews'] : 'off';

			if ( 'on' !== $show ) {
				return;
			}

			$location_name = get_comment_meta( $comment->comment_ID, '_mulopimfwc_location_name', true );

			if ( empty( $location_name ) ) {
				$slug          = get_comment_meta( $comment->comment_ID, '_mulopimfwc_location', true );
				$location_name = $this->location_name_from_slug( $slug );
			}

			if ( ! empty( $location_name ) ) {
				echo '<p class="mulopimfwc-review-location">';
				echo '<span class="mulopimfwc-review-location__label"><svg class="mulopimfwc-review-location__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z" fill="currentColor"/></svg>';
				echo esc_html(
					sprintf(
						/* translators: %s location name */
						mulopimfwc_get_text_value( 'text_reviews_label' ),
						$location_name
					)
				);
				echo '</span></p>';
			}
		}

		/**
		 * Helper: current location slug from cookie.
		 *
		 * @return string
		 */
		private function get_current_location_slug() {
			return mulopimfwc_get_store_location_cookie();
		}

		/**
		 * Helper: convert slug to term name (fallback to slug).
		 *
		 * @param string $slug
		 * @return string
		 */
		private function location_name_from_slug( $slug ) {
			if ( empty( $slug ) ) {
				return '';
			}
			$term = get_term_by( 'slug', $slug, 'mulopimfwc_store_location' );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
			return $slug;
		}
	}

	// Bootstrap
	new MULOPIMFWC_Location_Wise_Reviews();
}
