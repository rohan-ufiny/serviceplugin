<?php
/*
Plugin Name: Service Booking
Description: Allows users to book services with trainers and checkout via WooCommerce.
Version: 1.1
Author: ChatGPT
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Service_Booking_Plugin {
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'init', array( $this, 'add_roles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        add_shortcode( 'service_booking', array( $this, 'booking_form' ) );
        add_shortcode( 'trainer_dashboard', array( $this, 'trainer_dashboard' ) );

        add_action( 'wp_ajax_get_available_trainers', array( $this, 'get_available_trainers' ) );
        add_action( 'wp_ajax_nopriv_get_available_trainers', array( $this, 'get_available_trainers' ) );
        add_action( 'wp_ajax_create_booking', array( $this, 'create_booking' ) );
        add_action( 'wp_ajax_nopriv_create_booking', array( $this, 'create_booking' ) );
    }

    public function activate() {
        $this->register_post_types();
        $this->add_roles();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
        remove_role( 'trainer' );
    }

    public function register_post_types() {
        register_post_type( 'service_booking_service', array(
            'labels' => array(
                'name' => 'Services',
                'singular_name' => 'Service',
            ),
            'public' => false,
            'show_ui' => true,
            'supports' => array( 'title', 'editor', 'thumbnail' ),
            'rewrite' => false,
        ) );

        register_post_type( 'service_booking', array(
            'labels' => array(
                'name' => 'Bookings',
                'singular_name' => 'Booking',
            ),
            'public' => false,
            'show_ui' => true,
            'supports' => array( 'title', 'editor', 'custom-fields' ),
        ) );
    }

    public function add_roles() {
        add_role( 'trainer', 'Trainer', array( 'read' => true ) );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'service-booking-style', plugin_dir_url( __FILE__ ) . 'css/service-booking.css' );
        wp_enqueue_script( 'service-booking-js', plugin_dir_url( __FILE__ ) . 'js/service-booking.js', array( 'jquery' ), null, true );
        wp_localize_script( 'service-booking-js', 'serviceBooking', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'service_booking_nonce' ),
        ) );
    }

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
        ) );

        ob_start();
        echo '<h2>Your Bookings</h2>';
        if ( $bookings ) {
            echo '<ul>';
            foreach ( $bookings as $booking ) {
                $service_id = get_post_meta( $booking->ID, 'service_id', true );
                $date       = get_post_meta( $booking->ID, 'date', true );
                $time       = get_post_meta( $booking->ID, 'time', true );
                echo '<li>' . esc_html( get_the_title( $service_id ) ) . ' - ' . esc_html( $date . ' ' . $time ) . '</li>';
            }
            echo '</ul>';
        } else {
            echo 'No bookings.';
        }
        return ob_get_clean();
    }

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
                    array(
                        'key'   => 'trainer_id',
                        'value' => $user->ID,
                    ),
                    array(
                        'key'   => 'date',
                        'value' => $date,
                    ),
                    array(
                        'key'   => 'time',
                        'value' => $time,
                    ),
                ),
            ) );
            if ( ! $conflict ) {
                $available[] = array(
                    'id'   => $user->ID,
                    'name' => $user->display_name,
                    'rating' => get_user_meta( $user->ID, 'trainer_rating', true ),
                );
            }
        }

        wp_send_json( $available );
    }

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
}

new Service_Booking_Plugin();
