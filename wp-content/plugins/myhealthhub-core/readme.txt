=== My Health Hub Core ===
Contributors: myhealthhub
Tags: directory, health, therapists, leads, testimonials
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Core functionality for the My Health Hub directory - handles leads, claims, testimonials, and admin features.

== Description ==

My Health Hub Core is the essential companion plugin for the My Health Hub WordPress theme. It provides all the custom functionality needed to run a mental health professional directory.

**Features:**

* **Lead Management** - Capture and manage "Get Matched" form submissions
* **Listing Claims** - Allow providers to claim and manage their listings
* **Testimonials** - Collect and moderate client testimonials
* **Admin Dashboard** - Centralized dashboard for managing all submissions
* **GoHighLevel Integration** - Optional webhook integration for lead forwarding
* **Email Notifications** - Automatic notifications for new submissions

**Custom Post Types:**

* Leads - Store lead form submissions
* Claims - Track listing claim requests
* Testimonials - Manage client testimonials with approval workflow

**Requirements:**

* WordPress 5.8 or higher
* PHP 7.4 or higher
* My Health Hub theme (recommended)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/myhealthhub-core/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under 'My Health Hub' > 'Settings'

== Frequently Asked Questions ==

= Does this plugin require the My Health Hub theme? =

While the plugin is designed to work best with the My Health Hub theme, it can function independently. Some features may require theme support.

= Can I integrate with my CRM? =

Yes! The plugin supports GoHighLevel webhook integration for lead forwarding. You can configure this in the settings.

= How do I approve testimonials? =

Testimonials are submitted with "Pending" status. Navigate to My Health Hub > Testimonials in the admin to review and approve them.

== Changelog ==

= 1.0.0 =
* Initial release
* Lead management system
* Listing claim workflow
* Testimonial submission and moderation
* Admin dashboard
* GoHighLevel webhook integration
