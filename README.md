# Service Booking Plugin

This repository contains a WordPress plugin that lets customers book services with specific trainers and pay via WooCommerce. The plugin now includes a comprehensive multi-step booking form, trainer dashboards, REST API endpoints, daily reminder emails and more.

## Installation
1. Copy the `service-booking` directory to your `wp-content/plugins` folder.
2. Activate **Service Booking** from the admin Plugins page.
3. Create services via the **Services** custom post type. For each service, add the WooCommerce product ID as custom field `product_id`.
4. Create trainer accounts using the **Trainer** role. Optionally set their available service IDs in `service_booking_services` and a numeric `trainer_rating` meta field.
5. Add the `[service_booking]` shortcode to any page to display the booking form.
6. Trainers can manage upcoming bookings with the `[trainer_dashboard]` shortcode.

## Features
- Custom post types for Services and Bookings
- Trainer role with profile fields for rating and availability
- Multi-step AJAX booking form with realtime trainer availability
- Bookings added to WooCommerce cart and linked to orders
- Daily reminder emails and cleanup of old bookings
- REST API endpoints for listing and creating bookings
- CSV export and optional import utilities

This plugin is provided as an example and may require additional customization before use in production.
