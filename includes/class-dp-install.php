<?php
/**
 * Installation related functions and actions
 *
 * @author   BlackCarrotVentures
 * @category Admin
 * @package  DashPayments/Classes
 * @version  0.0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DP_Install Class.
 */
class DP_Install {

    /**
     * Hook in tabs.
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
        add_action( 'init', array( __CLASS__, 'install_actions' ) );

        add_filter( 'woocommerce_email_classes', array( __CLASS__, 'add_dashpayments_emails' ) );

        // add_action( 'admin_init', array( __CLASS__, 'install_actions' ) );
        add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
    }

    /**
     * Add DashPayments emails to WooCommerce.
     */
    public static function add_dashpayments_emails( $emails ) {
      $emails['DP_Email_Invoice_Paid'] = include('emails/class-dp-email-invoice-paid.php');
      return $emails;
    }

    /**
     * Check DashPayments version and run the updater if required.
     *
     * This check is done on all requests and runs if the versions do not match.
     */
    public static function check_version() {
        if ( ! defined( 'IFRAME_REQUEST' ) && get_option( 'dashpayments_version' ) !== DP()->version ) {
            self::install();
            do_action( 'dashpayments_updated' );
        }
    }

    /**
     * Install actions when a update button is clicked within the admin area.
     *
     * This function is hooked into admin_init to affect admin only.
     */
    public static function install_actions() {
      add_action('dashpayments_check_unpaid_orders', 'dp_check_unpaid_orders');
      // if ( ! empty( $_GET['do_update_dashpayments'] ) ) {
      //   self::update();
      //   DP_Admin_Notices::remove_notice( 'update' );
      //   add_action( 'admin_notices', array( __CLASS__, 'updated_notice' ) );
      // }
    }


    /**
     * Show notice stating update was successful.
     */
    public static function updated_notice() {
?>
        <div id="message" class="updated dashpayments-message dp-connect">
            <p><?php _e( 'DashPayments data update complete. Thank you for updating to the latest version!', 'dashpay-woocommerce' ); ?></p>
        </div>
<?php
    }

    /**
     * Install DP.
     */
    public static function install() {
        global $wpdb;

        if ( ! defined( 'DP_INSTALLING' ) ) {
            define( 'DP_INSTALLING', true );
        }

        // Ensure needed classes are loaded
        // include_once( 'admin/class-wc-admin-notices.php' );
        // self::create_options();
        self::create_cron_jobs();

        // TODO: Queue upgrades/setup wizard
        // $current_dp_version    = get_option( 'dashpayments_version', null );
        // $major_dp_version      = substr( DP()->version, 0, strrpos( DP()->version, '.' ) );
        self::update_dp_version();

        // Trigger action
        do_action( 'dashpayments_installed' );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('dashpayments_check_unpaid_orders');
    }

    public static function uninstall() {
        // TODO: check if user has 'delete all plugin data' set, if so, then
        // delete all data (e.g. gateway options)
        //
        // remove settings from wp_options
        // foreach ($active_gateways as $gateway) {
        //   if (delete_all_date_for($gateway)) {
        //     delete_option($gateway_settings_option_name);
        //   }
        // }

        // current dashpay transients & options:
        // _transient_timeout_dashpay_exchange_rate_DASH_USD
        // _transient_dashpay_exchange_rate_DASH_USD
        // woocommerce_dash_settings
        // dashpayments_version
        // dashpay_last_dash_xpub_6e61155e076a6490a11d812d51c73600_index
        // dashpay_last_dash_xpub_b4ef38eb1de8cad77682990cc4e5fa90_index
    }

    /**
     * Update DP version to current.
     */
    private static function update_dp_version() {
        delete_option( 'dashpayments_version' );
        add_option( 'dashpayments_version', DP()->version );
    }

    /**
     * Handle updates.
     */
    /*
    private static function update() {
        if ( ! defined( 'DP_UPDATING' ) ) {
            define( 'DP_UPDATING', true );
        }
    }
     */

    /**
     * Add more cron schedules.
     * @param  array $schedules
     * @return array
     */
    public static function cron_schedules( $schedules ) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __( 'Every minute', 'woocommerce' )
        );
        return $schedules;
    }

    /**
     * Create cron jobs (clear them first).
     */
    private static function create_cron_jobs() {
        wp_clear_scheduled_hook( 'dashpayments_check_unpaid_orders' );
        wp_schedule_event( time(), 'every_minute', 'dashpayments_check_unpaid_orders' );
    }

    /**
     * Default options.
     *
     * Sets up the default options used on the settings page.
     */
    private static function create_options() {
      // TODO: this
        // Include settings so that we can run through defaults
        // include_once( 'admin/class-wc-admin-settings.php' );
        // $settings = WC_Admin_Settings::get_settings_pages();
    }

}

DP_Install::init();
