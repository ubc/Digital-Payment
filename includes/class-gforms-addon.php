<?php
/**
 * A gravity form add-on which integrates with UBC ePayments's new uPay Proxy system
 *
 * @package ubc-dpp
 * @since 0.1.0
 */

namespace UBC\CTLT\DPP;

\GFForms::include_addon_framework();

	/**
	 * Status_listener
	 */
class GForms_Addon extends \GFAddon {

	/**
	 * The version of this add-on.
	 *
	 * @var string
	 */
	protected $_version = '1.0';
	/**
	 * The version of Gravity Forms required for this add-on.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '1.9';
	/**
	 * A short, lowercase, URL-safe unique identifier for the add-on. This will be used in option keys, filter, actions, URLs, and text-domain localization. The maximum size allowed for the slug is 33 characters.
	 *
	 * @var string
	 */
	protected $_slug = 'ubc-dpp';
	/**
	 * Relative path to the plugin from the plugins folder. Example “gravityforms/gravityforms.php”
	 *
	 * @var string
	 */
	protected $_path = 'ubc-dpp/includes/class-settings.php';
	/**
	 * The physical path to the main plugin file. Set this to __FILE__
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;
	/**
	 * The complete title of the Add-On.
	 *
	 * @var string
	 */
	protected $_title = 'Digital Payments';
	/**
	 * The short title of the Add-On to be used in limited spaces.
	 *
	 * @var string
	 */
	protected $_short_title = 'Digital Payments';

	/**
	 * If available, contains an instance of this class.
	 *
	 * @var object|null
	 */
	private static $_instance = null;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @return object $_instance An instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}//end get_instance()

	/**
	 * Register styles for the settings page.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'upay-settings-style',
				'src'     => UBC_DPP_PLUGIN_URL . 'src/settings.css',
				'version' => filemtime( UBC_DPP_PLUGIN_DIR . 'src/settings.css' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => 'ubc-dpp',
					),
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => 'ubc-dpp',
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}//end styles()

	/**
	 * Placeholder for HTML field type, this function is suppose to be empty.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function settings_html_field() {
	}//end settings_html_field()

	/**
	 * Register and render uPay form settings.
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed $form current Form object.
	 * @return array
	 */
	public function form_settings_fields( $form ) {
		$is_cbm_enabled = class_exists( 'UBC_CBM' );

		return ! empty( $this->get_plugin_setting( 'ubc_upay_onboarding_complete' ) ) ? array(
			array(
				'title'  => esc_html__( 'E-Payment Form?', 'ubc-dpp' ),
				'fields' => array(
					array(
						'type'  => 'toggle',
						'label' => 'Enable this form to be used as an e-payment form for uPay.',
						'name'  => 'ubc_upay_is_epayment_form',
					),
				),
			),
			array(
				'title'  => esc_html__( 'Payment Environment', 'ubc-dpp' ),
				'fields' => array(
					array(
						'type'          => 'radio',
						'description'   => wp_kses_post( 'The uPay <em>test</em> environment will allow you to test this form without taking actual payments. Use the <em>production</em> environment when you are ready to take real payments with this form.<br><br>' ),
						'default_value' => 'test',
						'name'          => 'ubc_upay_form_environment',
						'horizontal'    => true,
						'disabled'      => $is_cbm_enabled,
						'choices'       => array(
							array(
								'tooltip' => esc_html__( 'Placeholder', 'ubc-dpp' ),
								'label'   => esc_html__( 'Test', 'ubc-dpp' ),
								'value'   => 'test',
							),
							array(
								'tooltip' => esc_html__( 'Placeholder', 'ubc-dpp' ),
								'label'   => esc_html__( 'Production', 'ubc-dpp' ),
								'value'   => 'prod',
							),
						),
					),
					$is_cbm_enabled ? array(
						'label' => '<br><div class="alert gforms_note_warning">You currently have the old e-payments plugin active. You will only be able to use the uPay TEST environment until you disabled the old plugin. Once you disable the old plugin e-payments plugin you will be able to switch your forms to use the uPay production environment.</div>',
						'type'  => 'html_field',
					) : array(),
				),
			),
			array(
				'title'      => esc_html__( 'Workday Override', 'ubc-dpp' ),
				'dependency' => array(
					'field'  => 'ubc_upay_is_epayment_form',
					'values' => 1,
				),
				'fields'     => array(
					array(
						'type'  => 'toggle',
						'label' => 'Enable',
						'name'  => 'ubc_upay_is_workday_override',
					),
					array(
						'name'                => 'ubc_upay_ledger_id[0]',
						'label'               => '<br>Ledger Account ID',
						'tooltip'             => esc_html__( 'A Ledger Account ID override in Workday.<br><br>This parameter is a part of the all-or-nothing accounting override parameter group: if specified, all other parameters in the same group must be specified as well.', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'required'            => true,
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								$text_object->set_error( __( 'This field is required.', 'ubc-dpp' ) );
								return;
							}

							if ( ! ( (float) $value >= 1105 && (float) $value <= 4500 ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Ledger Account ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return (float) $value >= 1105 && (float) $value <= 4500;
						},
					),
					array(
						'name'                => 'ubc_upay_revenue_category_id[0]',
						'tooltip'             => esc_html__( 'A Revenue Category ID override in Workday. This value typically classifies the item purchased.<br><br>This parameter is a part of the all-or-nothing accounting override parameter group: if specified, all other parameters in the same group must be specified as well.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Revenue Category ID', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'required'            => true,
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								$text_object->set_error( __( 'This field is required.', 'ubc-dpp' ) );
								return;
							}

							if ( false == preg_match( '/^RC([0-9]){4}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Category ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^RC([0-9]){4}$/', $value );
						},
					),
					array(
						'name'                => 'ubc_upay_fund_id[0]',
						'tooltip'             => esc_html__( 'A Fund ID override in Workday.<br><br>This parameter is a part of the all-or-nothing accounting override parameter group: if specified, all other parameters in the same group must be specified as well.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Fund ID', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'required'            => true,
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								$text_object->set_error( __( 'This field is required.', 'ubc-dpp' ) );
								return;
							}

							if ( false == preg_match( '/^FD([0-9]){3}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Fund ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^FD([0-9]){3}$/', $value );
						},
					),
					array(
						'name'                => 'ubc_upay_function_id[0]',
						'tooltip'             => esc_html__( 'A Custom Organization Reference ID override in Workday.<br><br>This parameter is a part of the all-or-nothing accounting override parameter group: if specified, all other parameters in the same group must be specified as well.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Function ID', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'required'            => true,
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								$text_object->set_error( __( 'This field is required.', 'ubc-dpp' ) );
								return;
							}

							if ( false == preg_match( '/^FN([0-9]){3}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Function ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^FN([0-9]){3}$/', $value );
						},
					),
					array(
						'name'                => 'ubc_upay_cost_centre_id[0]',
						'tooltip'             => esc_html__( 'A Cost Center Reference ID override in Workday.<br><br>This parameter is a part of the all-or-nothing accounting override parameter group: if specified, all other parameters in the same group must be specified as well.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Cost Centre ID', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'required'            => true,
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								$text_object->set_error( __( 'This field is required.', 'ubc-dpp' ) );
								return;
							}

							if ( false == preg_match( '/^CC([0-9]){5}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Cost Centre ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^CC([0-9]){5}$/', $value );
						},
					),
					array(
						'name'                => 'ubc_upay_program_id[0]',
						'tooltip'             => esc_html__( 'A Program ID override in Workday. Mutually exclusive with Project ID.<br><br>If specified, the rest of the corresponding accounting override parameter group must also be specified.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Program ID', 'ubc-dpp' ),
						'description'         => esc_html__( 'If specified, the Project ID must not be provided.', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								return;
							}

							if ( false == preg_match( '/^PM([0-9]){6}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Program ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^PM([0-9]){6}$/', $value );
						},
					),
					array(
						'name'                => 'ubc_upay_project_id[0]',
						'tooltip'             => esc_html__( 'A Project ID override in Workday. Mutually exclusive with Program ID.<br><br>If specified, the rest of the corresponding accounting override parameter group must also be specified.', 'ubc-dpp' ),
						'label'               => esc_html__( 'Project ID', 'ubc-dpp' ),
						'description'         => esc_html__( 'If specified, the Program ID must not be provided.', 'ubc-dpp' ),
						'type'                => 'text',
						'class'               => 'small',
						'validation_callback' => function ( $text_object, $value ) {
							if ( null === $value ) {
								return;
							}

							if ( empty( sanitize_text_field( $value ) ) ) {
								return;
							}

							if ( false == preg_match( '/^PJ([0-9]){6}$/', $value ) ) {
								$text_object->set_error( __( 'The value you have entered is not valid, please contact the UBC Digital Payment Processing Team for the correct Project ID.', 'ubc-dpp' ) );
								return;
							}
						},
						'dependency'          => array(
							'field'  => 'ubc_upay_is_workday_override',
							'values' => 1,
						),
						'feedback_callback'   => function ( $value ) {
							return false != preg_match( '/^PJ([0-9]){6}$/', $value );
						},
					),
				),
			),
		) : array(
			// uPay Form Settings onboarding warning section.
			array(
				'title'  => esc_html__( 'Payments Form Settings', 'ubc-dpp' ),
				'fields' => array(
					array(
						'label' => '<br><div class="alert gforms_note_warning">Please complete the onboarding at Forms > Settings > Digital Payments before trying to activate a form for use with Digital Payments.</div>',
						'type'  => 'html_field',
					),
				),
			),
		);
	}//end form_settings_fields()

	/**
	 * Register and render uPay plugin settings.
	 *
	 * @since 0.1.0
	 *
	 * @return array plugin settings page form fields.
	 */
	public function plugin_settings_fields() {

		$is_cbm_enabled = class_exists( 'UBC_CBM' );
		$is_super_admin = is_super_admin();

		$fields = array(

			// Debugging section for super admins
			array(
				'title'       => esc_html__( 'Enable DPP Logging', 'ubc-dpp' ),
				'description' => wp_kses_post( 'Network Administrators Only: Enabling logging will output information to the dpp-debug.log file in the wp-content directory. It only applies to this site, not network wide. This toggle is not available to site admins.' ),
				'dependency'  => function () use ( $is_super_admin ) {
					return $is_super_admin;
				},
				'fields'      => array(
					array(
						'type'  => 'toggle',
						'label' => '',
						'name'  => 'ubc_upay_enable_debugging',
					),
				),
			),

			// uPay intro section.
			array(
				'title'       => esc_html__( 'uPay Settings', 'ubc-dpp' ),
				'description' => wp_kses_post( 'These are the site wide settings which you will have received from the UBC e-payments team as part of their onboarding. They are necessary before you are able to assign any form as being for e-payment.' ),
				'fields'      => $is_cbm_enabled ?
					array(
						array(
							'label' => '<br><div class="alert gforms_note_warning">You currently have the old e-payments plugin active. You will only be able to use the uPay TEST environment until you disable the old plugin. Once you disable the old e-payments plugin you will be able to switch your forms to use the uPay production environment.</div>',
							'type'  => 'html_field',
						),
					) :
					array(
						array(),
					),
			),
			// uPay test environment settings.
			array(
				'title'       => esc_html__( 'uPay Test Environment Settings', 'ubc-dpp' ),
				'description' => wp_kses_post( 'Enter the details provided by the UBC Digital Payments Processing Team as part of the onboarding for the uPay <strong>test environment</strong>. These are used when a form is set to use the <strong>test environment</strong> so that you can ensure your form is working as you expect. <em>Note: No actual money will be collected when a form is using the test environment.</em><br><br>' ),
				'dependency'  => function () {
					return ! empty( $this->get_plugin_setting( 'ubc_upay_onboarding_complete' ) );
				},
				'class'       => 'gform-settings-panel--half',
				'fields'      => array(
					// Test credentials.
					array(
						'name'              => 'ubc_upay_merchant_id_test',
						'tooltip'           => esc_html__( 'A merchant identifier.<br><br>This ID is assigned to the Merchant during onboarding and is unique for this Merchant.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Testing Merchant ID', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^([A-Za-z0-9]){4,10}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_store_id_test',
						'tooltip'           => esc_html__( 'A merchant store identifier.<br><br>This ID is assigned to the Merchant\'s Web Store during onboarding and is unique for this Web Store.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Testing Merchant Store ID', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^([0-9]){2}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_proxy_key_test',
						'tooltip'           => esc_html__( 'A secret \'key\' value is assigned to the Merchant\'s Web Store by UBC uPay API Admin during the uPay onboarding process. It does not change unless explicitly re-assigned in response to a uPay API policy change, or if the Merchant reports it as compromised.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Testing Merchant Proxy Key', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^[a-f0-9]{30}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_update_secret_test',
						'tooltip'           => esc_html__( 'The shared secret that is used by the Merchant to validate the request sender. This value is provided to the Merchant during onboarding. It must be kept secure.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Testing Merchant Update Secret', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^[a-f0-9]{30}$/', $value );
						},
					),
				),
			),
			// uPay prod credentials.
			array(
				'title'       => esc_html__( 'uPay Production Environment Settings', 'ubc-dpp' ),
				'description' => wp_kses_post( 'Enter the details provided by the UBC Digital Payments Processing Team as part of the onboarding for the uPay <strong>production environment</strong>. These are used when you want a form to start collecting payments from people. <em>Note: you will still need to set up each form to use the <strong>production environment</strong> in each forms settings.</em><br><br>' ),
				'dependency'  => function () {
					return ! empty( $this->get_plugin_setting( 'ubc_upay_onboarding_complete' ) );
				},
				'class'       => 'gform-settings-panel--half',
				'fields'      => array(
					// Prod credentials.
					array(
						'name'              => 'ubc_upay_merchant_id_prod',
						'tooltip'           => esc_html__( 'A merchant identifier.<br><br>This ID is assigned to the Merchant during onboarding and is unique for this Merchant.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Production Merchant ID', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'readonly'          => $is_cbm_enabled,
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^([A-Za-z0-9]){4,10}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_store_id_prod',
						'tooltip'           => esc_html__( 'A merchant store identifier.<br><br>This ID is assigned to the Merchant\'s Web Store during onboarding and is unique for this Web Store.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Production Merchant Store ID', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'readonly'          => $is_cbm_enabled,
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^([0-9]){2}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_proxy_key_prod',
						'tooltip'           => esc_html__( 'A secret \'key\' value is assigned to the Merchant\'s Web Store by UBC uPay API Admin during the uPay onboarding process. It does not change unless explicitly re-assigned in response to a uPay API policy change, or if the Merchant reports it as compromised.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Production Merchant Proxy Key', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'readonly'          => $is_cbm_enabled,
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^[a-f0-9]{30}$/', $value );
						},
					),
					array(
						'name'              => 'ubc_upay_merchant_update_secret_prod',
						'tooltip'           => esc_html__( 'The shared secret that is used by the Merchant to validate the request sender. This value is provided to the Merchant during onboarding. It must be kept secure.', 'ubc-dpp' ),
						'label'             => esc_html__( 'Production Merchant Update Secret', 'ubc-dpp' ),
						'type'              => 'text',
						'class'             => 'small',
						'readonly'          => $is_cbm_enabled,
						'feedback_callback' => function ( $value ) {
							return false != preg_match( '/^[a-f0-9]{30}$/', $value );
						},
					),
				),
			),
			// uPay onboarding URLs section.
			array(
				'title'       => esc_html__( 'uPay Settings - Onboarding Details', 'ubc-dpp' ),
				'description' => wp_kses_post( '<p>In order to use the new uPay integration with this website, you must first go through the <a href="https://vpfo-dpp-2022.sites.olt.ubc.ca/intake-forms/" target="_blank">on-boarding procedure with the UBC Digital Payments Processing team</a> from the UBC IT e-payments team. Here is some information you will need for that on-boarding.<br><br></p>' ),
				'fields'      => array(
					array(
						'name'          => 'ubc_upay_public_site_url',
						'label'         => esc_html__( 'Posting URL', 'ubc-dpp' ),
						'type'          => 'text',
						'class'         => 'small',
						'readonly'      => 'true',
						'default_value' => get_site_url( null, '', 'https' ),
					),
					array(
						'name'          => 'ubc_upay_public_listener_url',
						'label'         => esc_html__( 'Endpoint URL', 'ubc-dpp' ),
						'type'          => 'text',
						'class'         => 'small',
						'readonly'      => 'true',
						'default_value' => get_site_url( null, 'wp-json/ubc/v1/epayments-upay', 'https' ),
					),
					array(
						'name'          => 'ubc_upay_success_redirect_url',
						'label'         => esc_html__( 'Success Link URL', 'ubc-dpp' ),
						'type'          => 'text',
						'class'         => 'small',
						'readonly'      => 'true',
						'default_value' => get_site_url( null, 'ubc-epayment/success', 'https' ),
					),
					array(
						'name'          => 'ubc_upay_cancelled_redirect_url',
						'label'         => esc_html__( 'Cancel Link URL', 'ubc-dpp' ),
						'type'          => 'text',
						'class'         => 'small',
						'readonly'      => 'true',
						'default_value' => get_site_url( null, 'ubc-epayment/cancelled', 'https' ),
					),
					array(
						'name'          => 'ubc_upay_error_redirect_url',
						'label'         => esc_html__( 'Error Link URL', 'ubc-dpp' ),
						'type'          => 'text',
						'class'         => 'small',
						'readonly'      => 'true',
						'default_value' => get_site_url( null, 'ubc-epayment/error', 'https' ),
					),
				),
			),
			// Onboarding complete section.
			array(
				'title'       => esc_html__( 'On-boarding Complete', 'ubc-dpp' ),
				'description' => wp_kses_post( 'Enable this toggle once you have been through the on-boarding procedure outlined above and you have credentials such as Merchant ID, Merchant Store ID, Proxy Key, and Merchant Update Secret.' ),
				'fields'      => array(
					array(
						'type'  => 'toggle',
						'label' => '',
						'name'  => 'ubc_upay_onboarding_complete',
					),
				),
			),
		);

		return $fields;
	}//end plugin_settings_fields()
}
