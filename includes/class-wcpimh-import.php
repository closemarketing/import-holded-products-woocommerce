<?php
/**
 * Library for importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    1.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MAX_LOCAL_LOOP', 45 );
define( 'MAX_SYNC_LOOP', 5 );
define( 'MAX_LIMIT_HOLDED_API', 500 );
/**
 * Library for WooCommerce Settings
 *
 * Settings in order to importing products
 *
 * @package    WordPress
 * @author     David Perez <david@closemarketing.es>
 * @copyright  2019 Closemarketing
 * @version    0.1
 */
class WCPIMH_Import {
	/**
	 * The plugin file
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Array of products to import
	 *
	 * @var array
	 */
	private $products;

	/**
	 * Ajax Message that shows while imports
	 *
	 * @var string
	 */
	private $ajax_msg;

	/**
	 * Saves the products with errors to send after
	 *
	 * @var array
	 */
	private $error_product_import;

	/**
	 * Table of Sync DB
	 *
	 * @var string
	 */
	private $table_sync;

	/**
	 * Constructs of class
	 */
	public function __construct() {
		global $wpdb;
		$this->table_sync = $wpdb->prefix . WCPIMH_TABLE_SYNC;

		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_footer_scripts' ), 11, 1 );
		add_action( 'wp_ajax_wcpimh_import_products', array( $this, 'wcpimh_import_products' ) );

		// Admin Styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Cron jobs.
		if ( WP_DEBUG ) {
			//add_action( 'admin_head', array( $this, 'cron_sync_products' ), 20 );
		}
		$imh_settings = get_option( 'imhset' );
		$sync_period  = isset( $imh_settings['wcpimh_sync'] ) ? strval( $imh_settings['wcpimh_sync'] ) : 'no';

		if ( $sync_period && 'no' !== $sync_period ) {
			add_action( $sync_period, array( $this, 'cron_sync_products' ) );
		}
		$this->is_woocommerce_active = imhwc_is_active_ecommerce( 'woocommerce' ) ? true : false;
		$this->is_edd_active         = imhwc_is_active_ecommerce( 'edd' ) ? true : false;
	}

	/**
	 * Adds one or more classes to the body tag in the dashboard.
	 *
	 * @link https://wordpress.stackexchange.com/a/154951/17187
	 * @param  String $classes Current body classes.
	 * @return String          Altered body classes.
	 */
	public function admin_body_class( $classes ) {
		return "$classes wcpimh-plugin";
	}

	/**
	 * Enqueues Styles for admin
	 *
	 * @return void
	 */
	public function admin_styles() {
		wp_enqueue_style( 'import-holded', plugins_url( 'admin.css', __FILE__ ), array(), WCPIMH_VERSION );
	}
	/**
	 * Imports products from Holded
	 *
	 * @return void
	 */
	public function wcpimh_import_products() {
		// Imports products.
		$this->wcpimh_import_method_products();
	}

	/**
	 * Internal function to sanitize text
	 *
	 * @param string $text Text to sanitize.
	 * @return string Sanitized text.
	 */
	private function sanitize_text( $text ) {
		$text = str_replace( '>', '&gt;', $text );
		return $text;
	}

	/**
	 * Assigns the array to a taxonomy, and creates missing term
	 *
	 * @param string $post_id Post id of actual post id.
	 * @param array  $taxonomy_slug Slug of taxonomy.
	 * @param array  $category_array Array of category.
	 * @return void
	 */
	private function assign_product_term( $post_id, $taxonomy_slug, $category_array ) {
		$parent_term      = '';
		$term_levels      = count( $category_array );
		$term_level_index = 1;
		foreach ( $category_array as $category_name ) {
			$category_name = $this->sanitize_text( $category_name );
			$search_term = term_exists( $category_name, $taxonomy_slug );

			if ( 0 === $search_term || null === $search_term ) {
				// Creates taxonomy.
				$args_term = array(
					'slug' => sanitize_title( $category_name ),
				);
				if ( $parent_term ) {
					$args_term['parent'] = $parent_term;
				}
				$search_term = wp_insert_term(
					$category_name,
					$taxonomy_slug,
					$args_term
				);
			}
			if ( $term_level_index === $term_levels ) {
				wp_set_object_terms( $post_id, (int) $search_term['term_id'], $taxonomy_slug );
			}

			// Next iteration for child.
			$parent_term = $search_term['term_id'];
			$term_level_index++;
		}
	}

	/**
	 * Create a new global attribute.
	 *
	 * @param string $raw_name Attribute name (label).
	 * @return int Attribute ID.
	 */
	protected static function create_global_attribute( $raw_name ) {
		$slug = wc_sanitize_taxonomy_name( $raw_name );

		$attribute_id = wc_create_attribute(
			array(
				'name'         => $raw_name,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => false,
			)
		);

		$taxonomy_name = wc_attribute_taxonomy_name( $slug );
		register_taxonomy(
			$taxonomy_name,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy_name,
				array(
					'labels'       => array(
						'name' => $raw_name,
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		delete_transient( 'wc_attribute_taxonomies' );

		return $attribute_id;
	}

	/**
	 * Create global attributes in WooCommerce
	 *
	 * @param array   $attributes Attributes array.
	 * @param boolean $for_variation Is for variation.
	 * @return array
	 */
	private function make_attributes( $attributes, $for_variation = true ) {
		$position = 0;
		foreach ( $attributes as $attr_name => $attr_values ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_id( 0 );
			$attribute->set_position( $position );
			$attribute->set_visible( true );
			$attribute->set_variation( $for_variation );

			$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
			$attribute_name   = array_search( $attr_name, $attribute_labels, true );

			if ( ! $attribute_name ) {
				$attribute_name = wc_sanitize_taxonomy_name( $attr_name );
			}

			$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

			if ( ! $attribute_id ) {
				$attribute_id = self::create_global_attribute( $attr_name );
			}
			$slug          = wc_sanitize_taxonomy_name( $attr_name );
			$taxonomy_name = wc_attribute_taxonomy_name( $slug );

			$attribute->set_name( $taxonomy_name );
			$attribute->set_id( $attribute_id );
			$attribute->set_options( $attr_values );

			$attributes_return[] = $attribute;
			$position++;
		}
		return $attributes_return;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	private function find_product( $sku ) {
		global $wpdb;
		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$meta_key  = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$meta_key  = 'edd_sku';
		}
		$result_query = $wpdb->get_var( $wpdb->prepare( "SELECT P.ID FROM $wpdb->posts AS P LEFT JOIN $wpdb->postmeta AS PM ON PM.post_id = P.ID WHERE P.post_type = '$post_type' AND PM.meta_key='$meta_key' AND PM.meta_value=%s AND P.post_status != 'trash' LIMIT 1", $sku ) );

		return $result_query;
	}

	/**
	 * Finds simple and variation item in WooCommerce.
	 *
	 * @param string $sku SKU of product.
	 * @return string $product_id Products id.
	 */
	private function find_parent_product( $sku ) {
		global $wpdb;
		$post_id_var = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ) );

		if ( $post_id_var ) {
			$post_parent = wp_get_post_parent_id( $post_id_var );
			return $post_parent;
		}
		return false;
	}

	/**
	 * Update product meta with the object included in WooCommerce
	 *
	 * Coded inspired from: https://github.com/woocommerce/wc-smooth-generator/blob/master/includes/Generator/Product.php
	 *
	 * @param object $item Item Object from holded.
	 * @param string $product_id Product ID. If is null, is new product.
	 * @param string $type Type of the product.
	 * @param array  $pack_items Array of packs: post_id and qty.
	 * @return void.
	 */
	private function sync_product( $item, $product_id = 0, $type, $pack_items = null ) {
		global $connwoo_premium;

		$imh_settings     = get_option( 'imhset' );
		$import_stock     = isset( $imh_settings['wcpimh_stock'] ) ? $imh_settings['wcpimh_stock'] : 'no';
		$is_virtual       = ( isset( $imh_settings['wcpimh_virtual'] ) && 'yes' === $imh_settings['wcpimh_virtual'] ) ? true : false;
		$allow_backorders = isset( $imh_settings['wcpimh_backorders'] ) ? $imh_settings['wcpimh_backorders'] : 'yes';
		$rate_id          = isset( $imh_settings['wcpimh_rates'] ) ? $imh_settings['wcpimh_rates'] : 'default';
		$post_status      = ( isset( $imh_settings['wcpimh_prodst'] ) && $imh_settings['wcpimh_prodst'] ) ? $imh_settings['wcpimh_prodst'] : 'draft';
		$is_new_product   = ( 0 === $product_id || false === $product_id ) ? true : false;

		// Translations.
		$msg_variation_error = __( 'Variation error: ', 'import-holded-products-woocommerce' );

		/**
		 * # Updates info for the product
		 * ---------------------------------------------------------------------------------------------------- */

		// Start.
		if ( 'simple' === $type ) {
			$product = new \WC_Product( $product_id );
		} elseif ( 'variable' === $type && cmk_fs()->is__premium_only() ) {
			$product = new \WC_Product_Variable( $product_id );
		} elseif ( 'pack' === $type && cmk_fs()->is__premium_only() ) {
			$product = new \WC_Product( $product_id );
		}
		// Common and default properties.
		$product_props     = array(
			'stock_status'  => 'instock',
			'backorders'    => $allow_backorders,
			'regular_price' => $item['price'],
		);
		$product_props_new = array();
		if ( $is_new_product ) {
			$product_props_new = array(
				'menu_order'         => 0,
				'name'               => $item['name'],
				'featured'           => false,
				'catalog_visibility' => 'visible',
				'description'        => $item['desc'],
				'short_description'  => '',
				'sale_price'         => '',
				'date_on_sale_from'  => '',
				'date_on_sale_to'    => '',
				'total_sales'        => '',
				'tax_status'         => 'taxable',
				'tax_class'          => '',
				'manage_stock'       => 'yes' === $import_stock ? true : false,
				'stock_quantity'     => null,
				'sold_individually'  => false,
				'weight'             => $is_virtual ? '' : $item['weight'],
				'length'             => '',
				'width'              => '',
				'height'             => '',
				'barcode'            => $item['barcode'],
				'upsell_ids'         => '',
				'cross_sell_ids'     => '',
				'parent_id'          => 0,
				'reviews_allowed'    => true,
				'purchase_note'      => '',
				'virtual'            => $is_virtual,
				'downloadable'       => false,
				'category_ids'       => '',
				'tag_ids'            => '',
				'shipping_class_id'  => 0,
				'image_id'           => '',
				'gallery_image_ids'  => '',
				'status'             => $post_status,
			);
		}
		$product_props = array_merge( $product_props, $product_props_new );
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();

		$product_id = $product->get_id();

		switch ( $type ) {
			case 'simple';
			case 'grouped';
				$connwoo_premium->sync_product_variable( $product, $item, $is_new_product, $rate_id );
				break;
			case 'variable':
				if ( connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) ) {
					$connwoo_premium->sync_product_variable( $product, $item, $is_new_product, $rate_id );
				}
				break;
			case 'pack':
				if ( connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) ) {
					$connwoo_premium->sync_product_pack( $product, $item, $pack_items );
				}
				break;
	 	}

		if ( connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) ) {
			$categories_ids = $connwoo_premium->get_categories_ids( $imh_settings, $is_new_product );
			if ( ! empty( $categories_ids ) ) {
				$product_props['category_ids'] = $categories_ids;
			}
		}

		if ( connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) ) {
			// Imports image.
			$connwoo_premium->put_product_image( $item['id'], $product_id );
		}
		// Set properties and save.
		$product->set_props( $product_props );
		$product->save();
		if ( 'pack' === $type && connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) ) {
			wp_set_object_terms( $product_id, 'woosb', 'product_type' );
		}
	}
	/**
	 * Filters product to not import to web
	 *
	 * @param array $tag_product Tags of the product.
	 * @return boolean True to not get the product, false to get it.
	 */
	private function filter_product( $tag_product ) {
		$imh_settings       = get_option( 'imhset' );
		$tag_product_option = isset( $imh_settings['wcpimh_filter'] ) ? $imh_settings['wcpimh_filter'] : '';
		if ( $tag_product_option && ! in_array( $tag_product_option, $tag_product, true ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Creates the post for the product from item
	 *
	 * @param [type] $item
	 * @return void
	 */
	private function create_product_post( $item ) {
		$imh_settings = get_option( 'imhset' );
		$prod_status  = ( isset( $imh_settings['wcpimh_prodst'] ) && $imh_settings['wcpimh_prodst'] ) ? $imh_settings['wcpimh_prodst'] : 'draft';

		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$sku_key   = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$sku_key   = 'edd_sku';
		}
		$post_arg = array(
			'post_title'   => ( $item['name'] ) ? $item['name'] : '',
			'post_content' => ( $item['desc'] ) ? $item['desc'] : '',
			'post_status'  => $prod_status,
			'post_type'    => $post_type,
		);
		$post_id  = wp_insert_post( $post_arg );
		if ( $post_id ) {
			update_post_meta( $post_id, $sku_key, $item['sku'] );
		}

		return $post_id;
	}

	/**
	 * Creates the simple product post from item
	 *
	 * @param array $item
	 * @return int
	 */
	private function sync_product_simple( $item, $from_pack = false ) {
		$post_id = $this->find_product( $item['sku'] );
		if ( ! $post_id ) {
			$post_id = $this->create_product_post( $item );
		}
		if ( $post_id && $item['sku'] && 'simple' == $item['kind'] ) {

			if ( $this->is_woocommerce_active ) {
				wp_set_object_terms( $post_id, 'simple', 'product_type' );
			}

			// Update meta for product.
			$this->sync_product( $item, $post_id, 'simple' );
		}
		if ( $from_pack ) {
			$this->ajax_msg .= '<br/>';
			if ( ! $post_id ) {
				$this->ajax_msg .= __( 'Subproduct created: ', 'import-holded-products-woocommerce' );
			} else {
				$this->ajax_msg .= __( 'Subproduct synced: ', 'import-holded-products-woocommerce' );
			}
		} else {
			if ( ! $post_id ) {
				$this->ajax_msg .= __( 'Product created: ', 'import-holded-products-woocommerce' );
			} else {
				$this->ajax_msg .= __( 'Product synced: ', 'import-holded-products-woocommerce' );
			}
		}
		$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')';

		return $post_id;
	}

	/**
	 * Import products from API
	 *
	 * @return void
	 */
	public function wcpimh_import_method_products() {
		global $connapi_erp;
		extract( $_REQUEST );
		$not_sapi_cli = substr( php_sapi_name(), 0, 3 ) != 'cli' ? true : false;
		$doing_ajax   = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$imh_settings = get_option( 'imhset' );
		$apikey       = $imh_settings['wcpimh_api'];
		$prod_status  = ( isset( $imh_settings['wcpimh_prodst'] ) && $imh_settings['wcpimh_prodst'] ) ? $imh_settings['wcpimh_prodst'] : 'draft';

		if ( $this->is_woocommerce_active ) {
			$post_type = 'product';
			$sku_key   = '_sku';
		} elseif ( $this->is_edd_active ) {
			$post_type = 'download';
			$sku_key   = 'edd_sku';
		}

		if ( in_array( 'woo-product-bundle/wpc-product-bundles.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$plugin_grouped_prod_active = true;
		} else {
			$plugin_grouped_prod_active = false;
		}

		$syncLoop     = isset( $syncLoop ) ? $syncLoop : 0;

		// Translations.
		$msg_product_created = __( 'Product created: ', 'import-holded-products-woocommerce' );
		$msg_product_synced  = __( 'Product synced: ', 'import-holded-products-woocommerce' );

		// Start.
		if ( ! isset( $this->products ) ) {
			$next     = true;
			$page     = 1;
			$output   = array();
			$products = array();

			while ( $next ) {
				$this->write_log( 'Page: ' . $page );
				$output   = $connapi_erp->get_products( null, $page );
				$products = array_merge( $products, $output );

				if ( count( $output ) === MAX_LIMIT_HOLDED_API ) {
					$page++;
				} else {
					$next = false;
				}
			}
			$this->products = $products;
		}

		if ( false === $this->products ) {
			if ( $doing_ajax ) {
				wp_send_json_error( array( 'msg' => 'Error' ) );
			} else {
				die();
			}
		} else {
			$products_array           = $this->products;
			$products_count           = count( $products_array );
			$item                     = $products_array[ $syncLoop ];
			$error_products_html      = '';
			$this->msg_error_products = array();

			// For testing:
			// $products_count = MAX_LOCAL_LOOP; .

			if ( $products_count ) {
				if ( ( $doing_ajax ) || $not_sapi_cli ) {
					$limit = 10;
					$count = $syncLoop + 1;
				}
				if ( $syncLoop > $products_count ) {
					if ( $doing_ajax ) {
						wp_send_json_error(
							array(
								'msg' => __( 'No products to import', 'import-holded-products-woocommerce' ),
							)
						);
					} else {
						die( esc_html( __( 'No products to import', 'import-holded-products-woocommerce' ) ) );
					}
				} else {
					$is_new_product      = false;
					$post_id             = 0;
					$is_filtered_product = $this->filter_product( $item['tags'] );

					if ( ! $is_filtered_product && $item['sku'] && 'simple' === $item['kind'] ) {
						$this->sync_product_simple( $item );
					} elseif ( ! $is_filtered_product && 'variants' === $item['kind'] && connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) && $this->is_woocommerce_active ) {
						// Variable product.
						// Check if any variants exists.
						$post_parent = 0;
						// Activar para buscar un archivo.
						$any_variant_sku = false;

						foreach ( $item['variants'] as $variant ) {
							if ( ! $variant['sku'] ) {
								break;
							} else {
								$any_variant_sku = true;
							}
							$post_parent = $this->find_parent_product( $variant['sku'] );
							if ( $post_parent ) {
								// Do not iterate if it's find it.
								break;
							}
						}
						if ( false === $any_variant_sku ) {
							$this->ajax_msg .= __( 'Product not imported becouse any variant has got SKU: ', 'import-holded-products-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ') <br/>';
						} else {
							// Update meta for product.
							$this->sync_product( $item, $post_parent, 'variable' );
							if ( 0 === $post_parent || false === $post_parent ) {
								$this->ajax_msg .= $msg_product_created;
							} else {
								$this->ajax_msg .= $msg_product_synced;
							}
							$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . '(' . $item['kind'] . ') <br/>';
						}
					} elseif ( ! $is_filtered_product && 'pack' === $item['kind'] && connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) && $this->is_woocommerce_active && $plugin_grouped_prod_active ) {
						$post_id = $this->find_product( $item['sku'] );

						if ( ! $post_id ) {
							$post_id = $this->create_product_post( $item );
							wp_set_object_terms( $post_id, 'woosb', 'product_type' );
						}
						if ( $post_id && $item['sku'] && 'pack' == $item['kind'] ) {

							// Create subproducts before.
							$pack_items = '';
							if ( isset( $item['packItems'] ) && ! empty( $item['packItems'] ) ) {
								foreach ( $item['packItems'] as $pack_item ) {
									$item_simple     = $this->get_products( $pack_item['pid'] );
									$product_pack_id = $this->sync_product_simple( $item_simple, true );
									$pack_items     .= $product_pack_id . '/' . $pack_item['u'] . ',';
									$this->ajax_msg .= ' x ' . $pack_item['u'];
								}
								$this->ajax_msg .= '<br/>';
								$pack_items = substr( $pack_items, 0, -1 );
							}

							// Update meta for product.
							$this->sync_product( $item, $post_id, 'pack', $pack_items );
						} else {
							if ( $doing_ajax ) {
								wp_send_json_error(
									array(
										'msg' => __( 'There was an error while inserting new product!', 'import-holded-products-woocommerce' ) . ' ' . $item['name'],
									)
								);
							} else {
								die( esc_html( __( 'There was an error while inserting new product!', 'import-holded-products-woocommerce' ) ) );
							}
						}
						if ( ! $post_id ) {
							$this->ajax_msg .= $msg_product_created;
						} else {
							$this->ajax_msg .= $msg_product_synced;
						}
						$this->ajax_msg .= $item['name'] . '. SKU: ' . $item['sku'] . ' (' . $item['kind'] . ')';
					} elseif ( ! $is_filtered_product && 'pack' === $item['kind'] && connwoo_is_premium() && class_exists( 'CONNWOO_Import_Premium' ) && $this->is_woocommerce_active && ! $plugin_grouped_prod_active ) {
						$plugin_url = 
						$this->ajax_msg .= '<span class="warning">' . __( 'Product needs Plugin to import: ', 'import-holded-products-woocommerce' );
						$this->ajax_msg .= '<a href="https://wordpress.org/plugins/woo-product-bundle/" target="_blank">WPC Product Bundles for WooCommerce</a> ';
						$this->ajax_msg .= '(' . $item['kind'] . ') </span></br>';

					} elseif ( $is_filtered_product ) {
						// Product not synced without SKU.
						$this->ajax_msg .= '<span class="warning">' . __( 'Product filtered to not import: ', 'import-holded-products-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ') </span></br>';
					} elseif ( '' === $item['sku'] && 'simple' === $item['kind'] ) {
						// Product not synced without SKU.
						$this->ajax_msg .= __( 'SKU not finded in Simple product. Product not imported: ', 'import-holded-products-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

						$this->error_product_import[] = array(
							'id_holded' => $item['id'],
							'name'      => $item['name'],
							'sku'       => $item['sku'],
							'error'     => __( 'SKU not finded in Simple product. Product not imported. ', 'import-holded-products-woocommerce' ),
						);
					} elseif ( 'simple' !== $item['kind'] ) {
						// Product not synced without SKU.
						$this->ajax_msg .= __( 'Product type not supported. Product not imported: ', 'import-holded-products-woocommerce' ) . $item['name'] . '(' . $item['kind'] . ')</br>';

						$this->error_product_import[] = array(
							'id_holded' => $item['id'],
							'name'      => $item['name'],
							'sku'       => $item['sku'],
							'error'     => __( 'Product type not supported. Product not imported: ', 'import-holded-products-woocommerce' ),
						);
					}
				}

				if ( $doing_ajax || $not_sapi_cli ) {
					$products_synced = $syncLoop + 1;

					if ( $products_synced <= $products_count ) {
						$this->ajax_msg = '[' . date_i18n( 'H:i:s' ) . '] ' . $products_synced . '/' . $products_count . ' ' . __( 'products. ', 'import-holded-products-woocommerce' ) . $this->ajax_msg;
						if ( $post_id ) {
							$this->ajax_msg .= ' <a href="' . get_edit_post_link( $post_id ) . '" target="_blank">' . __( 'View', 'import-holded-products-woocommerce' ) . '</a>';
						}
						if ( $products_synced == $products_count ) {
							$this->ajax_msg .= '<p class="finish">' . __( 'All caught up!', 'import-holded-products-woocommerce' ) . '</p>';
						}

						$args = array(
							'msg'           => $this->ajax_msg,
							'product_count' => $products_count,
						);
						if ( $doing_ajax ) {
							if ( $products_synced < $products_count ) {
								$args['loop'] = $syncLoop + 1;
							}
							wp_send_json_success( $args );
						} elseif ( $not_sapi_cli && $products_synced < $products_count ) {
							$url  = home_url() . '/?sync=true';
							$url .= '&syncLoop=' . ( $syncLoop + 1 );
							?>
							<script>
								window.location.href = '<?php echo esc_url( $url ); ?>';
							</script>
							<?php
							echo esc_html( $args['msg'] );
							die( 0 );
						}
					}
				}
			} else {
				if ( $doing_ajax ) {
					wp_send_json_error( array( 'msg' => __( 'No products to import', 'import-holded-products-woocommerce' ) ) );
				} else {
					die( esc_html( __( 'No products to import', 'import-holded-products-woocommerce' ) ) );
				}
			}
		}
		if ( $doing_ajax ) {
			wp_die();
		}
		// Email errors.
		$this->send_product_errors();
	}

	/**
	 * Emails products with errors
	 *
	 * @return void
	 */
	public function send_product_errors() {
		$error_content = '';
		if ( empty( $this->error_product_import ) ) {
			return;
		}
		foreach ( $this->error_product_import as $error ) {
			$error_content .= ' ' . __( 'Error:', 'import-holded-products-woocommerce' ) . $error['error'];
			$error_content .= ' ' . __( 'SKU:', 'import-holded-products-woocommerce' ) . $error['sku'];
			$error_content .= ' ' . __( 'Name:', 'import-holded-products-woocommerce' ) . $error['name'];
			$error_content .= ' <a href="https://app.holded.com/products/' . $error['id_holded'] . '">';
			$error_content .= __( 'Edit:', 'import-holded-products-woocommerce' ) . '</a>';
			$error_content .= '<br/>';
		}
		// Sends an email to admin.
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( get_option( 'admin_email' ), __( 'Error in Products Synced in', 'import-holded-products-woocommerce' ) . ' ' . get_option( 'blogname' ), $error_content, $headers );
	}

	public function attach_image( $post_id, $img_string ) {
		if ( ! $img_string || ! $post_id ) {
			return null;
		}

		$post         = get_post( $post_id );
		$upload_dir   = wp_upload_dir();
		$upload_path  = $upload_dir['path'];
		$filename     = $post->post_name . '.png';
		$image_upload = file_put_contents( $upload_path . $filename, $img_string );
		// HANDLE UPLOADED FILE.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'wp_get_current_user' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		$file = array(
			'error'    => '',
			'tmp_name' => $upload_path . $filename,
			'name'     => $filename,
			'type'     => 'image/png',
			'size'     => filesize( $upload_path . $filename ),
		);
		if ( ! empty( $file ) ) {
			$file_return = wp_handle_sideload( $file, array( 'test_form' => false ) );
			$filename    = $file_return['file'];
		}
		if ( isset( $file_return['file'] ) && isset( $file_return['file'] ) ) {
			$attachment = array(
				'post_mime_type' => $file_return['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', ' ', basename( $file_return['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'guid'           => $file_return['url'],
			);
			$attach_id  = wp_insert_attachment( $attachment, $filename, $post_id );
			if ( $attach_id ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$post_thumbnail_id = get_post_thumbnail_id( $post_id );
				if ( $post_thumbnail_id ) {
					wp_delete_attachment( $post_thumbnail_id, true );
				}
				$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				set_post_thumbnail( $post_id, $attach_id );
			}
		}
	}

	/**
	 * Get mains image.
	 *
	 * @param string $id ID of the post.
	 * @param string $post_id Post ID.
	 * @return void
	 */
	private function get_main_image( $id, $post_id ) {
		$product_main_img = $this->get_product_detail( $id );
		$this->attach_image( $post_id, $product_main_img );
	}

	/**
	 * Write Log
	 *
	 * @param string $log String log.
	 * @return void
	 */
	public function write_log( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
			} else {
				error_log( $log );
			}
		}
	}

	/**
	 * Adds AJAX Functionality
	 *
	 * @return void
	 */
	public function admin_print_footer_scripts() {
		$screen  = get_current_screen();
		$get_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'sync';

		if ( 'toplevel_page_import_holded' === $screen->base && 'sync' === $get_tab ) {
		?>
		<style>
			.spinner{ float: none; }
		</style>
		<script type="text/javascript">
			var loop=0;
			jQuery(function($){
				$(document).find('#sync-holded-engine').after('<div class="sync-wrapper"><h2><?php _e( 'Import Products from Holded', 'import-holded-products-woocommerce' ); ?></h2><p><?php _e( 'After you fillup the API settings, use the button below to import the products. The importing process may take a while and you need to keep this page open to complete it.', 'import-holded-products-woocommerce' ); ?><br/></p><button id="start-sync" class="button button-primary"<?php if ( false === sync_ecommerce_check_can_sync() ) { echo ' disabled'; } ?>><?php _e( 'Start Import', 'import-holded-products-woocommerce' ); ?></button></div><fieldset id="logwrapper"><legend><?php _e( 'Log', 'import-holded-products-woocommerce' ); ?></legend><div id="loglist"></div></fieldset>');
				$(document).find('#start-sync').on('click', function(){
					$(this).attr('disabled','disabled');
					$(this).after('<span class="spinner is-active"></span>');
					var class_task = 'odd';
					$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'"><?php echo '[' . date_i18n( 'H:i:s' ) . '] ' . __( 'Connecting with Holded and syncing Products ...', 'import-holded-products-woocommerce' ); ?></p>');

					var syncAjaxCall = function(x){
						$.ajax({
							type: "POST",
							url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
							dataType: "json",
							data: {
								action: "wcpimh_import_products",
								syncLoop: x
							},
							success: function(results) {
								if(results.success){
									if(results.data.loop){
										syncAjaxCall(results.data.loop);
									}else{
										$(document).find('#start-sync').removeAttr('disabled');
										$(document).find('.sync-wrapper .spinner').remove();
									}
								} else {
									$(document).find('#start-sync').removeAttr('disabled');
									$(document).find('.sync-wrapper .spinner').remove();
								}
								if( results.data.msg != undefined ){
									$(document).find('#logwrapper #loglist').append( '<p class="'+class_task+'">'+results.data.msg+'</p>');
								}
								if ( class_task == 'odd' ) {
									class_task = 'even';
								} else {
									class_task = 'odd';
								}
								$(".toplevel_page_import_holded #loglist").animate({ scrollTop: $(".toplevel_page_import_holded #loglist")[0].scrollHeight}, 1000);
							},
							error: function (xhr, text_status, error_thrown) {
								$(document).find('#start-sync').removeAttr('disabled');
								$(document).find('.sync-wrapper .spinner').remove();
								$(document).find('.sync-wrapper').append('<div class="progress">There was an Error! '+xhr.responseText+' '+text_status+': '+error_thrown+'</div>');
							}
								});
						}
						syncAjaxCall(window.loop);
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Sends errors to admin
	 *
	 * @param string $subject Subject of Email.
	 * @param array  $errors  Array of errors.
	 * @return void
	 */
	public function send_email_errors( $subject, $errors ) {
		$body    = implode( '<br/>', $errors );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( get_option( 'admin_email' ), 'IMPORT HOLDED: ' . $subject, $body, $headers );
	}
}

global $connwoo;

$connwoo = new WCPIMH_Import();
