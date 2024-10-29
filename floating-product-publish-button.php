<?php
/**
 * Plugin Name:       Floating Product Publish Button for WooCommerce
 * Plugin URI:        https://it.wordpress.org/plugins/always-visible-floating-product-publish-button-for-woocommerce/
 * Description:       This plugin has the function of making the product update button floating, so that it is always at hand without having to scroll up each time.
 * Version:           2.1.2
 * Author:            andre-dane-dev<andre.dane.dev@gmail.com>
 * Author URI:        https://github.com/andre-dane-dev
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       floating-product-publish-button
 * Domain Path:       /languages
 *
 * Requires PHP: 7.0
 * Requires at least: 6.0
 * Tested up to: 6.2
 * WC tested up to: 7.7.0
 * WC requires at least: 7.0.0
 *
 * Copyright (C) 2022 andre-dane-dev
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Floating_Product_Publish_Button
 *
 * @since   1.0.0
 * @version 2.1.0
 */
class Floating_Product_Publish_Button {

	const WC_MIN_VERSION = '7.0.0';

	/**
	 * Plugin's name.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $plugin_name;

	/**
	 * Plugin's version.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Plugin's text domain.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $text_domain;

	/**
	 * PHP minimum required version.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $php_min_version;

	/**
	 * WordPress minimum required version.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $wp_min_version;

	/**
	 * WooCommerce minimum required version.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public $wc_min_version;

	/**
	 * Notices.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	private $notices = array();

	/**
	 * Section id.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	protected $section_id = '';

	/**
	 * Construct.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 */
	public function __construct() {

		// Set plugin data.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data           = get_plugin_data( __FILE__ );
		$this->plugin_name     = $plugin_data['Name'];
		$this->version         = $plugin_data['Version'];
		$this->text_domain     = $plugin_data['TextDomain'];
		$this->php_min_version = $plugin_data['RequiresPHP'];
		$this->wp_min_version  = $plugin_data['RequiresWP'];
		$this->wc_min_version  = $plugin_data['WC requires at least'] ?? self::WC_MIN_VERSION;
		$this->section_id      = 'fppb';

		// Check environment.
		add_action( 'admin_init', array( $this, 'fppb_check_environment' ), 5 );

		// Add notices.
		add_action( 'admin_notices', array( $this, 'fppb_print_admin_notices' ), 15 );

		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'fppb_load_plugin_textdomain' ) );

		// Init plugin.
		add_action( 'plugins_loaded', array( $this, 'fppb_init' ) );
	}

	/**
	 * Init plugin.
	 *
	 * @since   2.0.1
	 * @version 2.1.0 Removed WC check
	 */
	public function fppb_init() {
		if (
			is_admin()
			&& current_user_can( 'edit_posts' )
			&& $this->fppb_is_php_compatible()
			&& $this->fppb_is_wp_compatible()
			// && $this->fppb_is_wc_compatible()
		) {
			$old_options_names = array(
				'fppb_color',
				'fppb_background_color',
				'fppb_border_color',
				'fppb_shadow_color',
				'fppb_default_position',
				'fppb_custom_top_position',
				'fppb_custom_left_position',
			);

			// Backward compatibility fix //
			// Get new option.
			$fppb_settings = get_option( 'fppb_settings' ) ? get_option( 'fppb_settings' ) : array();

			if ( empty( $fppb_settings ) ) {
				// Fix old options.
				foreach ( $old_options_names as $old_option_name ) {
					if ( get_option( $old_option_name ) ) {
						// Update new option.
						$fppb_settings[ $old_option_name ] = get_option( $old_option_name );
						update_option( 'fppb_settings', $fppb_settings );
					}
					// Delete old option.
					delete_option( $old_option_name );
				}
			}
			// Enqueue assets.
			add_action( 'admin_enqueue_scripts', array( $this, 'fppb_enqueue_scripts_and_styles' ), 10 );
			// Add section to WP Settings menu.
			add_action( 'admin_menu', array( $this, 'fppb_add_wp_section' ) );
			// Create the section under General settings.
			add_filter( 'woocommerce_get_sections_general', array( $this, 'fppb_add_wc_section' ) );
			// Add settings.
			add_filter( 'woocommerce_get_settings_general', array( $this, 'fppb_add_wc_settings' ), 99999, 2 ); // Must be highest to avoid other settings to be printed after
			// Gather options into one.
			add_action( 'woocommerce_update_options_general_fppb', array( $this, 'fppb_gather_options_into_one' ) );
		}
	}

	/**
	 * Add section to WP Settings menu.
	 *
	 * @since 2.1.0
	 */
	public function fppb_add_wp_section() {
		add_submenu_page(
			'options-general.php',
			'Floating Button',
			'Floating Button',
			'manage_options',
			$this->section_id,
			array( $this, 'fppb_add_wp_settings' )
		);
	}

	/**
	 * Add settings to WP Settings section.
	 *
	 * @since 2.1.1
	 */
	public function fppb_add_wp_settings() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check nonce.
		if (
			isset( $_POST['fppb_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fppb_nonce'] ) ), 'fppb_nonce' )
		) {
			$this->fppb_save_wp_settings();
		}

		// Show error/update messages.
		settings_errors( 'fppb_messages' );

		$fppb_settings = get_option( 'fppb_settings' ) ? get_option( 'fppb_settings' ) : array();

		$fppb_fields = $this->fppb_get_settings_fields();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="fppb_settings_section-description">
				<p>
					This is where you can set options concerning the position and style of the buttons.
					In particular, you can insert the colours you prefer with any CSS notation: if you are not handy, I recommend searching for "css colour picker".
					For custom position settings, on the other hand, I recommend searching for "css units for length" to check all units (I recommend percentage values).
				</p>
			</div>
			<form action="" method="post">
				<?php
				// Add nonce field.
				wp_nonce_field( 'fppb_nonce', 'fppb_nonce' );
				?>
				<table class="form-table">
				<?php
				foreach ( $fppb_fields as $field ) {
					$type    = $field['type'] ?? '';
					$id      = $field['id'] ?? '';
					$title   = $field['title'] ?? '';
					$css     = $field['css'] ?? '';
					$desc    = $field['desc'] ?? '';
					$options = $field['options'] ?? array();
					?>
					<tr>
					<?php
					switch ( $type ) {
						case 'text':
							?>
							<th scope="row" class="titledesc">
								<label for="<?php echo esc_attr( $id ); ?>">
									<?php echo esc_html( $title ); ?>
									<span class="woocommerce-help-tip" tabindex="0" aria-label="<?php echo esc_attr( $desc ); ?>"></span>
								</label>
							</th>
							<td class="forminp forminp-text">
								<input name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $css ); ?>" value="<?php echo esc_attr( $fppb_settings[ $id ] ?? '' ); ?>" class="" placeholder="">
							</td>
							<?php
							break;

						case 'select':
							?>
							<th scope="row" class="titledesc">
								<label for="<?php echo esc_attr( $id ); ?>">
									<?php echo esc_html( $title ); ?>
									<span class="woocommerce-help-tip" tabindex="0" aria-label="<?php echo esc_attr( $desc ); ?>">
									</span>
								</label>
							</th>
							<td class="forminp forminp-select">
								<select name="<?php echo esc_attr( $id ); ?>" id="<?php echo esc_attr( $id ); ?>" style="<?php echo esc_attr( $css ); ?>" class="">
									<?php foreach ( $options as $key => $option ) { ?>
										<option value="<?php echo esc_attr( $key ); ?>" 
											<?php
											if ( isset( $fppb_settings[ $id ] ) && $key === $fppb_settings[ $id ] ) {
												echo esc_attr( 'selected' );
											}
											?>
											><?php echo esc_html( $option ); ?>
										</option>
									<?php } ?>
								</select>
							</td>
							<?php
							break;
					}
					?>
					</tr>
					<?php
				}
				?>
				</table>

				<input type="submit" name="fppb_save_wp_settings" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'fppb' ); ?>" style="margin-top:1rem;" />
				<input type="hidden" name="fppb_submit" value="save_settings" />
			</form>
		</div>
		<?php
	}

	/**
	 * Save WP settings.
	 *
	 * @since 2.1.0
	 */
	public function fppb_save_wp_settings() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check nonce.
		if (
			isset( $_POST['fppb_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fppb_nonce'] ) ), 'fppb_nonce' )
		) {
			// Update option.
			$fppb_settings = array(
				'fppb_color'                => isset( $_POST['fppb_color'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_color'] ) ) : '',
				'fppb_background_color'     => isset( $_POST['fppb_background_color'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_background_color'] ) ) : '',
				'fppb_border_color'         => isset( $_POST['fppb_border_color'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_border_color'] ) ) : '',
				'fppb_shadow_color'         => isset( $_POST['fppb_shadow_color'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_shadow_color'] ) ) : '',
				'fppb_default_position'     => isset( $_POST['fppb_default_position'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_default_position'] ) ) : '',
				'fppb_custom_top_position'  => isset( $_POST['fppb_custom_top_position'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_custom_top_position'] ) ) : '',
				'fppb_custom_left_position' => isset( $_POST['fppb_custom_left_position'] ) ? sanitize_text_field( wp_unslash( $_POST['fppb_custom_left_position'] ) ) : '',
			);

			update_option( 'fppb_settings', $fppb_settings );

			// Add notice.
			add_settings_error( 'fppb_messages', 'fppb_message', __( 'Settings Saved', 'fppb' ), 'updated' );
		}
	}

	/**
	 * Checks the environment.
	 *
	 * @since   2.0.0
	 * @version 2.1.2
	 *
	 * @return void
	 */
	public function fppb_check_environment() {
		// Check PHP.
		if ( ! $this->fppb_is_php_compatible() && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			$this->fppb_deactivate_plugin();
			$this->fppb_add_admin_notice(
				'php_error',
				'error',
				$this->plugin_name . ' has been deactivated. ' . sprintf(
					'The minimum PHP version required for this plugin is %1$s. You are running %2$s.',
					$this->php_min_version,
					PHP_VERSION
				)
			);
			return;
		}
		// Check WP.
		if ( ! $this->fppb_is_wp_compatible() ) {
			$this->fppb_add_admin_notice(
				'update_wp',
				'error',
				sprintf(
					'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
					'<strong>' . $this->plugin_name . '</strong>',
					$this->wp_min_version,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>'
				)
			);
		}
		// Check WC.
		// if ( ! $this->is_wc_active() ) {
		// $this->fppb_add_admin_notice(
		// 'update_wc',
		// 'error',
		// sprintf(
		// translators: %1$s is the plugin name, %2$s is the link to WooCommerce plugin search page, %3$s is the closing anchor tag.
		// esc_html__( '%1$s requires WooCommerce to be installed and activated. Please install %2$sWooCommerce%3$s first.', $this->text_domain ),
		// '<strong>' . $this->plugin_name . '</strong>',
		// '<a href="' . admin_url( 'plugin-install.php?tab=search&type=term&s=WooCommerce' ) . '">',
		// '</a>'
		// )
		// );
		// } elseif ( ! $this->fppb_is_wc_compatible() ) {
		// $this->fppb_add_admin_notice(
		// 'update_wc',
		// 'error',
		// sprintf(
		// '%s requires WooCommerce version %s or higher. Please %supdate WooCommerce &raquo;%s',
		// '<strong>' . $this->plugin_name . '</strong>',
		// $this->wc_min_version,
		// '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
		// '</a>'
		// )
		// );
		// }
	}

	/**
	 * Check PHP version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function fppb_is_php_compatible() {
		return version_compare( PHP_VERSION, $this->php_min_version, '>=' );
	}

	/**
	 * Check WordPress version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function fppb_is_wp_compatible() {
		return version_compare( get_bloginfo( 'version' ), $this->wp_min_version, '>=' );
	}

	/**
	 * Check WooCommerce is active.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_wc_active() {
		return class_exists( 'woocommerce' );
	}

	/**
	 * Check WooCommerce version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function fppb_is_wc_compatible() {
		return defined( 'WC_VERSION' ) ? version_compare( WC_VERSION, $this->wc_min_version, '>=' ) : function_exists( 'WC' ) && version_compare( WC()->version, $this->wc_min_version, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 2.0.0
	 */
	protected function fppb_deactivate_plugin() {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug    Notice slug.
	 * @param string $class   Notice class.
	 * @param string $message Notice message body.
	 */
	public function fppb_add_admin_notice( $slug, $class, $message ) {
		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}


	/**
	 * Displays any admin notices.
	 *
	 * @since 2.0.0
	 */
	public function fppb_print_admin_notices() {
		foreach ( (array) $this->notices as $notice_key => $notice ) :
			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p><?php echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) ); ?></p>
			</div>
			<?php

		endforeach;
	}

	/**
	 * Load text domain.
	 *
	 * @since   1.0.0
	 * @version 2.0.0
	 */
	public function fppb_load_plugin_textdomain() {
		load_plugin_textdomain( $this->text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Enqueue script and style.
	 *
	 * @since   1.0.0
	 * @version 1.1.0
	 *
	 * @return void
	 */
	public function fppb_enqueue_scripts_and_styles() {
		$debug        = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$debug_script = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		$suffix       = ( $debug || $debug_script ? '' : '.min' );
		$version      = ( ! ( 'production' === wp_get_environment_type() ) ? time() : $this->version );
		// Enqueue scripts.
		wp_enqueue_script( 'fppb_script', plugin_dir_url( __FILE__ ) . 'includes/js/fppb_scripts' . $suffix . '.js', array( 'jquery' ), $version, true );
		wp_enqueue_style( 'fppb_style', plugin_dir_url( __FILE__ ) . 'includes/css/fppb_styles' . $suffix . '.css', array(), $version );

		$fppb_settings = get_option( 'fppb_settings' );

		$default_position = $fppb_settings['fppb_default_position'] ?? 'bottom-center'; // Fixed missing index bug.

		// Check if has custom position.
		$has_custom_position = 'custom' === $default_position ? $default_position : false;

		// Get top & left attributes.
		if ( $has_custom_position ) {
			$top_position  = $fppb_settings['fppb_custom_top_position'] ?? '';
			$left_position = $fppb_settings['fppb_custom_left_position'] ?? '';
		} else {
			$top_position  = explode( '-', $default_position )[0];
			$left_position = explode( '-', $default_position )[1];
		}

		// Collect options.
		$fppb_options = array(
			'fppb_color'               => $fppb_settings['fppb_color'] ?? '',
			'fppb_background_color'    => $fppb_settings['fppb_background_color'] ?? '',
			'fppb_border_color'        => $fppb_settings['fppb_border_color'] ?? '',
			'fppb_shadow_color'        => $fppb_settings['fppb_shadow_color'] ?? '',
			'fppb_has_custom_position' => $has_custom_position,
			'fppb_top_position'        => $top_position,
			'fppb_left_position'       => $left_position,
		);

		wp_localize_script( 'fppb_script', 'fppb_options', $fppb_options );
	}

	/**
	 * Add section under WooCommerce General settings.
	 *
	 * @since  2.0.0
	 *
	 * @see    https://woocommerce.com/document/adding-a-section-to-a-settings-tab/
	 * @param  array $sections The sections.
	 * @return array
	 */
	public function fppb_add_wc_section( $sections ) {
		$sections[ $this->section_id ] = __( 'Floating Product Publish Button', $this->text_domain );
		return $sections;
	}

	/**
	 * Add settings.
	 *
	 * @since   2.0.0
	 * @version 2.1.0
	 *
	 * @see    https://woocommerce.com/document/adding-a-section-to-a-settings-tab/
	 *
	 * @param  array  $settings        The settings.
	 * @param  string $current_section The section.
	 * @return array
	 */
	public function fppb_add_wc_settings( $settings, $current_section ) {
		// Check current section.
		if ( $current_section !== $this->section_id ) {
			return $settings;
		}

		return $this->fppb_get_settings_fields();
	}

	/**
	 * Get settings fields.
	 *
	 * @since 2.1.0
	 */
	public function fppb_get_settings_fields() {
		$fppb_settings = get_option( 'fppb_settings' ) ? get_option( 'fppb_settings' ) : array();

		return array(
			array(
				'id'   => 'fppb_settings_section',
				'name' => esc_html__( 'Floating Product Publish Button', $this->text_domain ),
				'type' => 'title',
				'desc' => sprintf(
					esc_html__( 'This is where you can set options concerning the position and style of the buttons. In particular, you can insert the colours you prefer with any CSS notation: if you are not handy, I recommend searching for "css colour picker" on your search engine to select the colour that suits you. For custom position settings, on the other hand, I recommend searching for "css units for length" on your search engine to check all units (I recommend percentage values).', $this->text_domain ),
					'<br>',
					'<br>'
				),
			),
			array(
				'id'       => 'fppb_color',
				'title'    => esc_html__( 'Button text color', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the color.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width:50px;',
				'value'    => $fppb_settings['fppb_color'] ?? '',
			),
			array(
				'id'       => 'fppb_background_color',
				'title'    => esc_html__( 'Button background color', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the color.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width:50px;',
				'value'    => $fppb_settings['fppb_background_color'] ?? '',
			),
			array(
				'id'       => 'fppb_border_color',
				'title'    => esc_html__( 'Button border color', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the color.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width:50px;',
				'value'    => $fppb_settings['fppb_border_color'] ?? '',
			),
			array(
				'id'       => 'fppb_shadow_color',
				'title'    => esc_html__( 'Button shadow color', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the color.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width:50px;',
				'value'    => $fppb_settings['fppb_shadow_color'] ?? '',
			),
			array(
				'id'       => 'fppb_default_position',
				'type'     => 'select',
				'title'    => esc_html__( 'Button default position', $this->text_domain ),
				'options'  => array( // top - center - bottom / left center right.
					'bottom-center' => esc_html__( 'Bottom center', $this->text_domain ),
					'bottom-right'  => esc_html__( 'Bottom right', $this->text_domain ),
					'bottom-left'   => esc_html__( 'Bottom left', $this->text_domain ),
					'middle-center' => esc_html__( 'Middle center', $this->text_domain ),
					'middle-right'  => esc_html__( 'Middle right', $this->text_domain ),
					'middle-left'   => esc_html__( 'Middle left', $this->text_domain ),
					'top-center'    => esc_html__( 'Top center', $this->text_domain ),
					'top-right'     => esc_html__( 'Top right', $this->text_domain ),
					'top-left'      => esc_html__( 'Top left', $this->text_domain ),
					'custom'        => esc_html__( 'Custom', $this->text_domain ),
				),
				'desc'     => esc_html__( 'Choose the button position.', $this->text_domain ),
				'desc_tip' => true,
				'value'    => $fppb_settings['fppb_default_position'] ?? '',
			),
			array(
				'id'       => 'fppb_custom_top_position',
				'title'    => esc_html__( 'Custom value: Top', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the value of the "top" attribute: this controls how far the button should be from the top of the screen.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width: 50px;', // 'display: none;',
				'value'    => $fppb_settings['fppb_custom_top_position'] ?? '',
			),
			array(
				'id'       => 'fppb_custom_left_position',
				'title'    => esc_html__( 'Custom value: Left', $this->text_domain ),
				'type'     => 'text',
				'desc'     => esc_html__( 'Enter the value of the "left" attribute: this controls how far the button should be from the left side of the screen.', $this->text_domain ),
				'desc_tip' => true,
				'css'      => 'min-width: 50px;',
				'value'    => $fppb_settings['fppb_custom_left_position'] ?? '',
			),
			array(
				'id'   => 'fppb_settings_section',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Gather multiple options into one, to save space in database.
	 *
	 * @since   1.0.0
	 * @version 1.1.1
	 *
	 * @return void
	 */
	public function fppb_gather_options_into_one() {
		// Get all options.
		$fppb_options = array(
			'fppb_color',
			'fppb_background_color',
			'fppb_border_color',
			'fppb_shadow_color',
			'fppb_default_position',
			'fppb_custom_top_position',
			'fppb_custom_left_position',
		);

		// Get settings option.
		$fppb_settings = is_array( get_option( 'fppb_settings' ) ) ? get_option( 'fppb_settings' ) : array();

		// Store updated options.
		foreach ( $fppb_options as $option_name ) {
			if (
				! isset( $fppb_settings[ $option_name ] )
				|| get_option( $option_name ) !== $fppb_settings[ $option_name ]
			) {
				$fppb_settings[ $option_name ] = get_option( $option_name );
			}

			delete_option( $option_name );
		}
		update_option( 'fppb_settings', $fppb_settings );
	}
}

new Floating_Product_Publish_Button();
