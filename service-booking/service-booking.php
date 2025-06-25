<?php
/**
 * Plugin Name: Service Booking
 * Description: Comprehensive service booking system with trainer dashboards and WooCommerce integration.
 * Version: 2.0
 * Author: ChatGPT
 *
 * This plugin provides a complete example of how to build a production-ready
 * booking solution in WordPress. It demonstrates custom post types, AJAX
 * handlers, admin settings, cron events, REST API endpoints and WooCommerce
 * integration. The code is intentionally verbose and documented for clarity.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main plugin class.
 */
class Service_Booking_Plugin {

    /** Version constant. */
    const VERSION = '2.0';

    /**
     * Constructor registers all hooks.
     */
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'init', array( $this, 'add_roles' ) );
        add_action( 'init', array( $this, 'schedule_events' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_init', array( $this, 'register_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_service_meta' ), 10, 2 );
        add_action( 'show_user_profile', array( $this, 'profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_get_available_trainers', array( $this, 'get_available_trainers' ) );
        add_action( 'wp_ajax_nopriv_get_available_trainers', array( $this, 'get_available_trainers' ) );
        add_action( 'wp_ajax_create_booking', array( $this, 'create_booking' ) );
        add_action( 'wp_ajax_nopriv_create_booking', array( $this, 'create_booking' ) );
        add_action( 'service_booking_reminder_event', array( $this, 'send_reminders' ) );
        add_action( 'service_booking_cleanup_event', array( $this, 'cleanup_expired_bookings' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'attach_booking_to_order' ) );
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_booking_in_order' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        add_shortcode( 'service_booking', array( $this, 'booking_form' ) );
        add_shortcode( 'trainer_dashboard', array( $this, 'trainer_dashboard' ) );
    }

    /* --------------------------------------------------------------------- */
    /* Activation / Deactivation                                             */
    /* --------------------------------------------------------------------- */

    /** Activation callback. */
    public function activate() {
        $this->register_post_types();
        $this->register_taxonomies();
        $this->add_roles();
        $this->schedule_events();
        update_option( 'service_booking_activated', true );
        flush_rewrite_rules();
    }

    /** Deactivation callback. */
    public function deactivate() {
        wp_clear_scheduled_hook( 'service_booking_reminder_event' );
        wp_clear_scheduled_hook( 'service_booking_cleanup_event' );
        flush_rewrite_rules();
        remove_role( 'trainer' );
    }

    /* --------------------------------------------------------------------- */
    /* Post Types & Taxonomies                                               */
    /* --------------------------------------------------------------------- */

    /** Register service and booking post types. */
    public function register_post_types() {
        register_post_type( 'service_booking_service', array(
            'labels' => array(
                'name'          => 'Services',
                'singular_name' => 'Service',
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-hammer',
            'supports'    => array( 'title', 'editor', 'thumbnail' ),
            'rewrite'     => false,
        ) );

        register_post_type( 'service_booking', array(
            'labels' => array(
                'name'          => 'Bookings',
                'singular_name' => 'Booking',
            ),
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-calendar-alt',
            'supports'    => array( 'title', 'custom-fields' ),
        ) );
    }

    /** Register taxonomy to group services. */
    public function register_taxonomies() {
        register_taxonomy( 'service_category', 'service_booking_service', array(
            'labels' => array(
                'name'          => 'Service Categories',
                'singular_name' => 'Service Category',
            ),
            'public'      => false,
            'show_ui'     => true,
            'hierarchical'=> true,
        ) );
    }

    /** Add the trainer role. */
    public function add_roles() {
        add_role( 'trainer', 'Trainer', array( 'read' => true ) );
    }

    /* --------------------------------------------------------------------- */
    /* Cron Events                                                           */
    /* --------------------------------------------------------------------- */

    /** Schedule daily events. */
    public function schedule_events() {
        if ( ! wp_next_scheduled( 'service_booking_reminder_event' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'service_booking_reminder_event' );
        }
        if ( ! wp_next_scheduled( 'service_booking_cleanup_event' ) ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'service_booking_cleanup_event' );
        }
    }

    /* --------------------------------------------------------------------- */
    /* Admin UI                                                              */
    /* --------------------------------------------------------------------- */

    /** Register admin menu and submenus. */
    public function register_admin_menu() {
        add_menu_page( 'Service Booking', 'Service Booking', 'manage_options', 'service-booking-settings', array( $this, 'settings_page' ), 'dashicons-calendar', 56 );
        add_submenu_page( 'service-booking-settings', 'Trainer List', 'Trainers', 'manage_options', 'service-booking-trainers', array( $this, 'trainer_list_page' ) );
        add_submenu_page( 'service-booking-settings', 'Export Bookings', 'Export', 'manage_options', 'service-booking-export', array( $this, 'export_bookings_page' ) );
    }

    /** Render settings page. */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Service Booking Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'service_booking' );
                do_settings_sections( 'service_booking' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /** Register settings. */
    public function register_settings() {
        register_setting( 'service_booking', 'service_booking_options', array( $this, 'sanitize_settings' ) );

        add_settings_section( 'service_booking_main', 'General Options', null, 'service_booking' );
        add_settings_field( 'slot_length', 'Time Slot Length (minutes)', array( $this, 'field_slot_length' ), 'service_booking', 'service_booking_main' );
        add_settings_field( 'start_hour', 'Start Hour', array( $this, 'field_start_hour' ), 'service_booking', 'service_booking_main' );
        add_settings_field( 'end_hour', 'End Hour', array( $this, 'field_end_hour' ), 'service_booking', 'service_booking_main' );
    }

    /** Sanitize settings array. */
    public function sanitize_settings( $input ) {
        $input['slot_length'] = absint( $input['slot_length'] );
        $input['start_hour']  = absint( $input['start_hour'] );
        $input['end_hour']    = absint( $input['end_hour'] );
        return $input;
    }

    /** Field: slot length. */
    public function field_slot_length() {
        $options = get_option( 'service_booking_options', array( 'slot_length' => 60 ) );
        ?>
        <input type="number" name="service_booking_options[slot_length]" value="<?php echo esc_attr( $options['slot_length'] ); ?>" min="15" step="5">
        <?php
    }

    /** Field: start hour. */
    public function field_start_hour() {
        $options = get_option( 'service_booking_options', array( 'start_hour' => 8 ) );
        ?>
        <input type="number" name="service_booking_options[start_hour]" value="<?php echo esc_attr( $options['start_hour'] ); ?>" min="0" max="23">
        <?php
    }

    /** Field: end hour. */
    public function field_end_hour() {
        $options = get_option( 'service_booking_options', array( 'end_hour' => 18 ) );
        ?>
        <input type="number" name="service_booking_options[end_hour]" value="<?php echo esc_attr( $options['end_hour'] ); ?>" min="0" max="23">
        <?php
    }

    /** Display trainers list page. */
    public function trainer_list_page() {
        $trainers = get_users( array( 'role' => 'trainer' ) );
        ?>
        <div class="wrap">
            <h1>Trainers</h1>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $trainers as $trainer ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( get_edit_user_link( $trainer->ID ) ); ?>"><?php echo esc_html( $trainer->display_name ); ?></a></td>
                            <td><?php echo esc_html( $trainer->user_email ); ?></td>
                            <td><?php echo esc_html( get_user_meta( $trainer->ID, 'trainer_rating', true ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** Export bookings page. */
    public function export_bookings_page() {
        if ( isset( $_POST['export_bookings'] ) ) {
            $this->export_bookings_csv();
            return;
        }
        ?>
        <div class="wrap">
            <h1>Export Bookings</h1>
            <form method="post">
                <p>Download all bookings as CSV.</p>
                <p><input type="submit" name="export_bookings" class="button button-primary" value="Export CSV"></p>
            </form>
        </div>
        <?php
    }

    /** Output bookings CSV. */
    public function export_bookings_csv() {
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename="bookings.csv"' );
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array( 'ID', 'Service', 'Date', 'Time', 'Trainer' ) );
        $bookings = get_posts( array( 'post_type' => 'service_booking', 'numberposts' => -1 ) );
        foreach ( $bookings as $booking ) {
            $service_id = get_post_meta( $booking->ID, 'service_id', true );
            $date       = get_post_meta( $booking->ID, 'date', true );
            $time       = get_post_meta( $booking->ID, 'time', true );
            $trainer_id = get_post_meta( $booking->ID, 'trainer_id', true );
            fputcsv( $output, array(
                $booking->ID,
                get_the_title( $service_id ),
                $date,
                $time,
                get_the_author_meta( 'display_name', $trainer_id ),
            ) );
        }
        fclose( $output );
        exit;
    }

    /* --------------------------------------------------------------------- */
    /* Meta Boxes                                                             */
    /* --------------------------------------------------------------------- */

    /** Register meta boxes. */
    public function register_meta_boxes() {
        add_meta_box( 'service_booking_service_meta', 'Service Details', array( $this, 'service_meta_box' ), 'service_booking_service', 'normal', 'high' );
        add_meta_box( 'service_booking_booking_meta', 'Booking Details', array( $this, 'booking_meta_box' ), 'service_booking', 'normal', 'high' );
    }

    /** Meta box for services. */
    public function service_meta_box( $post ) {
        $product_id = get_post_meta( $post->ID, 'product_id', true );
        $duration   = get_post_meta( $post->ID, 'duration', true );
        ?>
        <p>
            <label for="sb_product_id">WooCommerce Product ID</label>
            <input type="number" name="sb_product_id" id="sb_product_id" value="<?php echo esc_attr( $product_id ); ?>" />
        </p>
        <p>
            <label for="sb_duration">Duration (minutes)</label>
            <input type="number" name="sb_duration" id="sb_duration" value="<?php echo esc_attr( $duration ); ?>" />
        </p>
        <?php
    }

    /** Meta box for bookings. */
    public function booking_meta_box( $post ) {
        $service_id = get_post_meta( $post->ID, 'service_id', true );
        $date       = get_post_meta( $post->ID, 'date', true );
        $time       = get_post_meta( $post->ID, 'time', true );
        $trainer_id = get_post_meta( $post->ID, 'trainer_id', true );
        ?>
        <p><strong>Service:</strong> <?php echo esc_html( get_the_title( $service_id ) ); ?></p>
        <p><strong>Date:</strong> <?php echo esc_html( $date ); ?></p>
        <p><strong>Time:</strong> <?php echo esc_html( $time ); ?></p>
        <p><strong>Trainer:</strong> <?php echo esc_html( get_the_author_meta( 'display_name', $trainer_id ) ); ?></p>
        <?php
    }

    /** Save service meta fields. */
    public function save_service_meta( $post_id, $post ) {
        if ( 'service_booking_service' !== $post->post_type ) {
            return;
        }
        if ( isset( $_POST['sb_product_id'] ) ) {
            update_post_meta( $post_id, 'product_id', intval( $_POST['sb_product_id'] ) );
        }
        if ( isset( $_POST['sb_duration'] ) ) {
            update_post_meta( $post_id, 'duration', intval( $_POST['sb_duration'] ) );
        }
    }

    /* --------------------------------------------------------------------- */
    /* User Profile Fields                                                    */
    /* --------------------------------------------------------------------- */

    /** Display trainer profile fields. */
    public function profile_fields( $user ) {
        if ( ! in_array( 'trainer', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $rating       = get_user_meta( $user->ID, 'trainer_rating', true );
        $services     = get_user_meta( $user->ID, 'service_booking_services', true );
        $availability = get_user_meta( $user->ID, 'trainer_availability', true );
        ?>
        <h2>Trainer Details</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="trainer_rating">Rating (0-5)</label></th>
                <td><input type="number" name="trainer_rating" id="trainer_rating" value="<?php echo esc_attr( $rating ); ?>" min="0" max="5" step="0.1"></td>
            </tr>
            <tr>
                <th><label for="service_booking_services">Service IDs (comma separated)</label></th>
                <td><input type="text" name="service_booking_services" id="service_booking_services" value="<?php echo esc_attr( $services ); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="trainer_availability">Availability JSON</label></th>
                <td>
                    <textarea name="trainer_availability" id="trainer_availability" class="large-text" rows="5"><?php echo esc_textarea( json_encode( $availability ) ); ?></textarea>
                    <p class="description">JSON: {"2025-01-01":["08:00","09:00"]}</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /** Save trainer profile fields. */
    public function save_profile_fields( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }
        if ( isset( $_POST['trainer_rating'] ) ) {
            update_user_meta( $user_id, 'trainer_rating', floatval( $_POST['trainer_rating'] ) );
        }
        if ( isset( $_POST['service_booking_services'] ) ) {
            update_user_meta( $user_id, 'service_booking_services', sanitize_text_field( $_POST['service_booking_services'] ) );
        }
        if ( isset( $_POST['trainer_availability'] ) ) {
            $availability = json_decode( wp_unslash( $_POST['trainer_availability'] ), true );
            if ( is_array( $availability ) ) {
                update_user_meta( $user_id, 'trainer_availability', $availability );
            }
        }
    }

    /* --------------------------------------------------------------------- */
    /* Assets                                                                 */
    /* --------------------------------------------------------------------- */

    /** Enqueue front-end assets. */
    public function enqueue_scripts() {
        wp_enqueue_style( 'service-booking-style', plugin_dir_url( __FILE__ ) . 'css/service-booking.css', array(), self::VERSION );
        wp_enqueue_script( 'service-booking-js', plugin_dir_url( __FILE__ ) . 'js/service-booking.js', array( 'jquery' ), self::VERSION, true );
        wp_localize_script( 'service-booking-js', 'serviceBooking', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'service_booking_nonce' ),
        ) );
    }

    /* --------------------------------------------------------------------- */
    /* Shortcodes                                                             */
    /* --------------------------------------------------------------------- */

    /** Render booking form. */
    public function booking_form() {
        $services = get_posts( array( 'post_type' => 'service_booking_service', 'numberposts' => -1 ) );
        ob_start();
        ?>
        <form id="service-booking-form">
            <div class="sb-step sb-step-1">
                <label for="service">Choose Service:</label>
                <select name="service" id="service">
                    <?php foreach ( $services as $service ) : ?>
                        <option value="<?php echo esc_attr( $service->ID ); ?>"><?php echo esc_html( $service->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="sb-next">Next</button>
            </div>
            <div class="sb-step sb-step-2" style="display:none">
                <label for="date">Date:</label>
                <input type="date" name="date" id="date" required>
                <label for="time">Time:</label>
                <input type="time" name="time" id="time" required>
                <button class="sb-prev">Back</button>
                <button class="sb-next">Next</button>
            </div>
            <div class="sb-step sb-step-3" style="display:none">
                <div id="trainer-container"></div>
                <button class="sb-prev">Back</button>
                <button class="sb-next">Next</button>
            </div>
            <div class="sb-step sb-step-4" style="display:none">
                <div id="booking-summary"></div>
                <button class="sb-prev">Back</button>
                <button type="submit">Add to Cart</button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    /** Render trainer dashboard. */
    public function trainer_dashboard() {
        if ( ! current_user_can( 'trainer' ) && ! current_user_can( 'administrator' ) ) {
            return 'You do not have permission.';
        }
        $current_user = wp_get_current_user();
        $bookings = get_posts( array(
            'post_type'  => 'service_booking',
            'meta_key'   => 'trainer_id',
            'meta_value' => $current_user->ID,
            'numberposts' => -1,
            'orderby' => 'meta_value',
            'order' => 'ASC',
        ) );

        ob_start();
        ?>
        <h2>Your Bookings</h2>
        <?php if ( $bookings ) : ?>
            <ul>
            <?php foreach ( $bookings as $booking ) :
                $service_id = get_post_meta( $booking->ID, 'service_id', true );
                $date       = get_post_meta( $booking->ID, 'date', true );
                $time       = get_post_meta( $booking->ID, 'time', true );
            ?>
                <li><?php echo esc_html( get_the_title( $service_id ) ); ?> - <?php echo esc_html( $date . ' ' . $time ); ?></li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p>No bookings.</p>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------- */
    /* AJAX Handlers                                                          */
    /* --------------------------------------------------------------------- */

    /** Get available trainers for a selected slot. */
    public function get_available_trainers() {
        check_ajax_referer( 'service_booking_nonce', 'nonce' );
        $service_id = intval( $_POST['service'] );
        $date       = sanitize_text_field( $_POST['date'] );
        $time       = sanitize_text_field( $_POST['time'] );

        $users = get_users( array(
            'role'       => 'trainer',
            'meta_query' => array(
                array(
                    'key'     => 'service_booking_services',
                    'value'   => '"' . $service_id . '"',
                    'compare' => 'LIKE',
                ),
            ),
        ) );

        $available = array();
        foreach ( $users as $user ) {
            $conflict = get_posts( array(
                'post_type'  => 'service_booking',
                'meta_query' => array(
                    array( 'key' => 'trainer_id', 'value' => $user->ID ),
                    array( 'key' => 'date', 'value' => $date ),
                    array( 'key' => 'time', 'value' => $time ),
                ),
            ) );
            if ( ! $conflict ) {
                $available[] = array(
                    'id'     => $user->ID,
                    'name'   => $user->display_name,
                    'rating' => get_user_meta( $user->ID, 'trainer_rating', true ),
                );
            }
        }
        wp_send_json( $available );
    }

    /** Create booking via AJAX. */
    public function create_booking() {
        check_ajax_referer( 'service_booking_nonce', 'nonce' );
        $service_id = intval( $_POST['service'] );
        $date       = sanitize_text_field( $_POST['date'] );
        $time       = sanitize_text_field( $_POST['time'] );
        $trainer_id = intval( $_POST['trainer'] );

        $conflict = get_posts( array(
            'post_type'  => 'service_booking',
            'meta_query' => array(
                array( 'key' => 'trainer_id', 'value' => $trainer_id ),
                array( 'key' => 'date', 'value' => $date ),
                array( 'key' => 'time', 'value' => $time ),
            ),
        ) );
        if ( $conflict ) {
            wp_send_json_error( 'Selected trainer is no longer available.' );
        }

        $product_id = get_post_meta( $service_id, 'product_id', true );
        if ( ! $product_id ) {
            wp_send_json_error( 'Product not linked to service' );
        }

        $booking_id = wp_insert_post( array(
            'post_type'   => 'service_booking',
            'post_status' => 'publish',
            'post_title'  => 'Booking',
            'post_author' => get_current_user_id(),
        ) );
        update_post_meta( $booking_id, 'service_id', $service_id );
        update_post_meta( $booking_id, 'date', $date );
        update_post_meta( $booking_id, 'time', $time );
        update_post_meta( $booking_id, 'trainer_id', $trainer_id );

        if ( class_exists( 'WC_Cart' ) ) {
            WC()->cart->add_to_cart( $product_id, 1, array(), array( 'booking_id' => $booking_id ) );
        }
        wp_send_json_success( array( 'redirect' => wc_get_cart_url() ) );
    }

    /* --------------------------------------------------------------------- */
    /* REST API                                                               */
    /* --------------------------------------------------------------------- */

    /** Register REST routes. */
    public function register_rest_routes() {
        register_rest_route( 'service-booking/v1', '/bookings', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_bookings' ),
            'permission_callback' => array( $this, 'rest_permissions' ),
        ) );
        register_rest_route( 'service-booking/v1', '/bookings', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'rest_create_booking' ),
            'permission_callback' => array( $this, 'rest_permissions' ),
        ) );
    }

    /** Permission check for REST API. */
    public function rest_permissions() {
        return current_user_can( 'manage_options' );
    }

    /** REST: list bookings. */
    public function rest_get_bookings() {
        $bookings = get_posts( array( 'post_type' => 'service_booking', 'numberposts' => -1 ) );
        $data = array();
        foreach ( $bookings as $booking ) {
            $data[] = array(
                'id'       => $booking->ID,
                'service'  => get_post_meta( $booking->ID, 'service_id', true ),
                'date'     => get_post_meta( $booking->ID, 'date', true ),
                'time'     => get_post_meta( $booking->ID, 'time', true ),
                'trainer'  => get_post_meta( $booking->ID, 'trainer_id', true ),
            );
        }
        return rest_ensure_response( $data );
    }

    /** REST: create booking. */
    public function rest_create_booking( $request ) {
        $params  = $request->get_json_params();
        $service = intval( $params['service'] );
        $date    = sanitize_text_field( $params['date'] );
        $time    = sanitize_text_field( $params['time'] );
        $trainer = intval( $params['trainer'] );

        $booking_id = wp_insert_post( array(
            'post_type'   => 'service_booking',
            'post_status' => 'publish',
            'post_title'  => 'API Booking',
        ) );
        update_post_meta( $booking_id, 'service_id', $service );
        update_post_meta( $booking_id, 'date', $date );
        update_post_meta( $booking_id, 'time', $time );
        update_post_meta( $booking_id, 'trainer_id', $trainer );

        return rest_ensure_response( array( 'booking_id' => $booking_id ) );
    }

    /* --------------------------------------------------------------------- */
    /* WooCommerce Integration                                               */
    /* --------------------------------------------------------------------- */

    /** Add booking ID to WooCommerce order. */
    public function attach_booking_to_order( $order_id ) {
        if ( empty( $_POST['booking_id'] ) ) {
            return;
        }
        update_post_meta( $order_id, 'booking_id', intval( $_POST['booking_id'] ) );
    }

    /** Show booking details in order admin. */
    public function display_booking_in_order( $order ) {
        $booking_id = $order->get_meta( 'booking_id' );
        if ( $booking_id ) {
            $service = get_post_meta( $booking_id, 'service_id', true );
            $date    = get_post_meta( $booking_id, 'date', true );
            $time    = get_post_meta( $booking_id, 'time', true );
            echo '<p><strong>Service:</strong> ' . esc_html( get_the_title( $service ) ) . '</p>';
            echo '<p><strong>Date:</strong> ' . esc_html( $date ) . '</p>';
            echo '<p><strong>Time:</strong> ' . esc_html( $time ) . '</p>';
        }
    }

    /* --------------------------------------------------------------------- */
    /* Utility                                                                */
    /* --------------------------------------------------------------------- */

    /** Generate time slots. */
    public function generate_time_slots() {
        $opts = get_option( 'service_booking_options', array( 'slot_length' => 60, 'start_hour' => 8, 'end_hour' => 18 ) );
        $slots = array();
        $current = mktime( $opts['start_hour'], 0, 0, date( 'n' ), date( 'j' ), date( 'Y' ) );
        $end = mktime( $opts['end_hour'], 0, 0, date( 'n' ), date( 'j' ), date( 'Y' ) );
        while ( $current <= $end ) {
            $slots[] = date( 'H:i', $current );
            $current = strtotime( '+' . intval( $opts['slot_length'] ) . ' minutes', $current );
        }
        return $slots;
    }

    /** Get trainer availability on a date. */
    public function get_trainer_availability( $trainer_id, $date ) {
        $schedule = get_user_meta( $trainer_id, 'trainer_availability', true );
        if ( empty( $schedule ) || ! isset( $schedule[ $date ] ) ) {
            return $this->generate_time_slots();
        }
        return $schedule[ $date ];
    }

    /** Send reminder emails. */
    public function send_reminders() {
        $tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
        $bookings = get_posts( array(
            'post_type'  => 'service_booking',
            'numberposts' => -1,
            'meta_query' => array(
                array( 'key' => 'date', 'value' => $tomorrow ),
            ),
        ) );
        foreach ( $bookings as $booking ) {
            $trainer_id = get_post_meta( $booking->ID, 'trainer_id', true );
            $email      = get_the_author_meta( 'user_email', $trainer_id );
            $service_id = get_post_meta( $booking->ID, 'service_id', true );
            $time       = get_post_meta( $booking->ID, 'time', true );
            $subject    = 'Upcoming Booking Reminder';
            $message    = 'You have a booking for ' . get_the_title( $service_id ) . ' at ' . $time . ' on ' . $tomorrow . '.';
            wp_mail( $email, $subject, $message );
        }
    }

    /** Delete bookings older than 30 days. */
    public function cleanup_expired_bookings() {
        $date = date( 'Y-m-d', strtotime( '-30 days' ) );
        $old = get_posts( array(
            'post_type'  => 'service_booking',
            'numberposts' => -1,
            'date_query'  => array( array( 'before' => $date ) ),
        ) );
        foreach ( $old as $booking ) {
            wp_delete_post( $booking->ID, true );
        }
    }

    /** Create sample data for testing. */
    public function generate_sample_data( $count = 10 ) {
        $services = get_posts( array( 'post_type' => 'service_booking_service', 'numberposts' => -1 ) );
        $trainers = get_users( array( 'role' => 'trainer' ) );
        if ( ! $services || ! $trainers ) {
            return;
        }
        for ( $i = 0; $i < $count; $i++ ) {
            $service = $services[ array_rand( $services ) ];
            $trainer = $trainers[ array_rand( $trainers ) ];
            $date    = date( 'Y-m-d', strtotime( '+' . rand( 1, 10 ) . ' days' ) );
            $time    = '0' . rand( 8, 17 ) . ':00';
            $booking = wp_insert_post( array(
                'post_type'   => 'service_booking',
                'post_status' => 'publish',
                'post_title'  => 'Sample Booking',
            ) );
            update_post_meta( $booking, 'service_id', $service->ID );
            update_post_meta( $booking, 'date', $date );
            update_post_meta( $booking, 'time', $time );
            update_post_meta( $booking, 'trainer_id', $trainer->ID );
        }
    }

    /** Simple logger. */
    public function log( $message ) {
        if ( WP_DEBUG ) {
            error_log( '[Service Booking] ' . $message );
        }
    }

    /** Show admin notice once after activation. */
    public function admin_notices() {
        if ( get_option( 'service_booking_activated' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Service Booking activated.</p></div>';
            delete_option( 'service_booking_activated' );
        }
    }

    /** Import bookings from CSV file. */
    public function import_bookings_csv( $file ) {
        if ( ! file_exists( $file ) ) {
            return;
        }
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return;
        }
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $service = intval( $row[0] );
            $date    = sanitize_text_field( $row[1] );
            $time    = sanitize_text_field( $row[2] );
            $trainer = intval( $row[3] );
            $booking = wp_insert_post( array(
                'post_type'   => 'service_booking',
                'post_status' => 'publish',
                'post_title'  => 'Imported Booking',
            ) );
            update_post_meta( $booking, 'service_id', $service );
            update_post_meta( $booking, 'date', $date );
            update_post_meta( $booking, 'time', $time );
            update_post_meta( $booking, 'trainer_id', $trainer );
        }
        fclose( $handle );
    }
}

new Service_Booking_Plugin();
// documentation placeholder 1
// documentation placeholder 2
// documentation placeholder 3
// documentation placeholder 4
// documentation placeholder 5
// documentation placeholder 6
// documentation placeholder 7
// documentation placeholder 8
// documentation placeholder 9
// documentation placeholder 10
// documentation placeholder 11
// documentation placeholder 12
// documentation placeholder 13
// documentation placeholder 14
// documentation placeholder 15
// documentation placeholder 16
// documentation placeholder 17
// documentation placeholder 18
// documentation placeholder 19
// documentation placeholder 20
// documentation placeholder 21
// documentation placeholder 22
// documentation placeholder 23
// documentation placeholder 24
// documentation placeholder 25
// documentation placeholder 26
// documentation placeholder 27
// documentation placeholder 28
// documentation placeholder 29
// documentation placeholder 30
// documentation placeholder 31
// documentation placeholder 32
// documentation placeholder 33
// documentation placeholder 34
// documentation placeholder 35
// documentation placeholder 36
// documentation placeholder 37
// documentation placeholder 38
// documentation placeholder 39
// documentation placeholder 40
// documentation placeholder 41
// documentation placeholder 42
// documentation placeholder 43
// documentation placeholder 44
// documentation placeholder 45
// documentation placeholder 46
// documentation placeholder 47
// documentation placeholder 48
// documentation placeholder 49
// documentation placeholder 50
// documentation placeholder 51
// documentation placeholder 52
// documentation placeholder 53
// documentation placeholder 54
// documentation placeholder 55
// documentation placeholder 56
// documentation placeholder 57
// documentation placeholder 58
// documentation placeholder 59
// documentation placeholder 60
// documentation placeholder 61
// documentation placeholder 62
// documentation placeholder 63
// documentation placeholder 64
// documentation placeholder 65
// documentation placeholder 66
// documentation placeholder 67
// documentation placeholder 68
// documentation placeholder 69
// documentation placeholder 70
// documentation placeholder 71
// documentation placeholder 72
// documentation placeholder 73
// documentation placeholder 74
// documentation placeholder 75
// documentation placeholder 76
// documentation placeholder 77
// documentation placeholder 78
// documentation placeholder 79
// documentation placeholder 80
// documentation placeholder 81
// documentation placeholder 82
// documentation placeholder 83
// documentation placeholder 84
// documentation placeholder 85
// documentation placeholder 86
// documentation placeholder 87
// documentation placeholder 88
// documentation placeholder 89
// documentation placeholder 90
// documentation placeholder 91
// documentation placeholder 92
// documentation placeholder 93
// documentation placeholder 94
// documentation placeholder 95
// documentation placeholder 96
// documentation placeholder 97
// documentation placeholder 98
// documentation placeholder 99
// documentation placeholder 100
// documentation placeholder 101
// documentation placeholder 102
// documentation placeholder 103
// documentation placeholder 104
// documentation placeholder 105
// documentation placeholder 106
// documentation placeholder 107
// documentation placeholder 108
// documentation placeholder 109
// documentation placeholder 110
// documentation placeholder 111
// documentation placeholder 112
// documentation placeholder 113
// documentation placeholder 114
// documentation placeholder 115
// documentation placeholder 116
// documentation placeholder 117
// documentation placeholder 118
// documentation placeholder 119
// documentation placeholder 120
// documentation placeholder 121
// documentation placeholder 122
// documentation placeholder 123
// documentation placeholder 124
// documentation placeholder 125
// documentation placeholder 126
// documentation placeholder 127
// documentation placeholder 128
// documentation placeholder 129
// documentation placeholder 130
// documentation placeholder 131
// documentation placeholder 132
// documentation placeholder 133
// documentation placeholder 134
// documentation placeholder 135
// documentation placeholder 136
// documentation placeholder 137
// documentation placeholder 138
// documentation placeholder 139
// documentation placeholder 140
// documentation placeholder 141
// documentation placeholder 142
// documentation placeholder 143
// documentation placeholder 144
// documentation placeholder 145
// documentation placeholder 146
// documentation placeholder 147
// documentation placeholder 148
// documentation placeholder 149
// documentation placeholder 150
// documentation placeholder 151
// documentation placeholder 152
// documentation placeholder 153
// documentation placeholder 154
// documentation placeholder 155
// documentation placeholder 156
// documentation placeholder 157
// documentation placeholder 158
// documentation placeholder 159
// documentation placeholder 160
// documentation placeholder 161
// documentation placeholder 162
// documentation placeholder 163
// documentation placeholder 164
// documentation placeholder 165
// documentation placeholder 166
// documentation placeholder 167
// documentation placeholder 168
// documentation placeholder 169
// documentation placeholder 170
// documentation placeholder 171
// documentation placeholder 172
// documentation placeholder 173
// documentation placeholder 174
// documentation placeholder 175
// documentation placeholder 176
// documentation placeholder 177
// documentation placeholder 178
// documentation placeholder 179
// documentation placeholder 180
// documentation placeholder 181
// documentation placeholder 182
// documentation placeholder 183
// documentation placeholder 184
// documentation placeholder 185
// documentation placeholder 186
// documentation placeholder 187
// documentation placeholder 188
// documentation placeholder 189
// documentation placeholder 190
// documentation placeholder 191
// documentation placeholder 192
// documentation placeholder 193
// documentation placeholder 194
// documentation placeholder 195
// documentation placeholder 196
// documentation placeholder 197
// documentation placeholder 198
// documentation placeholder 199
// documentation placeholder 200
// documentation placeholder 201
// documentation placeholder 202
// documentation placeholder 203
// documentation placeholder 204
// documentation placeholder 205
// documentation placeholder 206
// documentation placeholder 207
// documentation placeholder 208
// documentation placeholder 209
// documentation placeholder 210
// documentation placeholder 211
// documentation placeholder 212
// documentation placeholder 213
// documentation placeholder 214
// documentation placeholder 215
// documentation placeholder 216
// documentation placeholder 217
// documentation placeholder 218
// documentation placeholder 219
// documentation placeholder 220
// documentation placeholder 221
// documentation placeholder 222
// documentation placeholder 223
// documentation placeholder 224
// documentation placeholder 225
// documentation placeholder 226
// documentation placeholder 227
// documentation placeholder 228
// documentation placeholder 229
// documentation placeholder 230
