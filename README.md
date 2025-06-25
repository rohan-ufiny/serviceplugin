# Service Booking Plugin

This repository contains a sample WordPress plugin that allows customers to book services with trainers and checkout via WooCommerce. The booking form is a multi-step flow where users pick a service, choose a date and time, select an available trainer and then review the booking before proceeding to the cart.

## Installation

1. Copy the `service-booking` directory to your WordPress `wp-content/plugins` folder.
2. Activate **Service Booking** from the WordPress admin plugins screen.
3. Create services using the **Services** custom post type in the dashboard. Each service should have a linked WooCommerce product (add the product ID as custom field `product_id`).
4. Create users with the role **Trainer** and set the custom field `service_booking_services` to a serialized array of service IDs they can perform.
5. Use the shortcode `[service_booking]` on any page to display the booking form.
6. Trainers can view their assigned bookings with the `[trainer_dashboard]` shortcode.
7. Add optional trainer ratings by setting the user meta `trainer_rating` (0-5). Ratings appear beside trainer names during selection.

This plugin is a minimal demonstration and may require further customization for production use.
