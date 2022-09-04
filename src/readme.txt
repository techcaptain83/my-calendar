=== My Calendar ===
Contributors: joedolson
Donate link: http://www.joedolson.com/donate/
Tags: calendar, dates, times, event, events, scheduling, schedule, event manager, event calendar, class, concert, venue, location, box office, tickets, registration
Requires at least: 4.4
Tested up to: 6.0
Requires PHP: 7.0
Text domain: my-calendar
Stable tag: 3.3.22
License: GPLv2 or later

Accessible WordPress event calendar plugin. Show events from multiple calendars on pages, in posts, or in widgets.

== Description ==

My Calendar does WordPress event management with richly customizable ways to display events. The plugin supports individual event calendars within WordPress Multisite, multiple calendars displayed by categories, locations or author, or simple lists of upcoming events.

Easy to use for anybody, My Calendar provides enormous flexibility for designers and developers needing a custom calendar. My Calendar is built with accessibility in mind, so all your users can get equal access and experience in your calendar.

= Premium Event Management =
Looking for more? [Buy My Calendar Pro](https://www.joedolson.com/my-calendar/pro/), the premium extension for My Calendar to add support for user-submitted events, integration between posting and event creation, and import events from outside sources.

= Selling event tickets? =
Do you sell tickets for your events? [Use My Tickets](https://wordpress.org/plugins/my-tickets/) and sell tickets for My Calendar events. Set prices, ticket availability, and sell multiple events at the same time using My Tickets.

= Features: =

*	Calendar grid and list views of events
*	Monthly, weekly, or daily view.
*	Mini-calendar for compact displays (as widget or as shortcode)
*	Widgets: today's events, upcoming events, compact calendar, event search
*	Custom templates for event output
*	Limit views by categories, location, author, or host
*	Editable CSS styles and JavaScript behaviors
*	Schedule recurring events.
*	Edit single occurrences of recurring events
*	Rich permissions handling to restrict access to parts of My Calendar
*	Email notification to administrator when events are scheduled or reserved
*	Post to Twitter when events are created (using [WP to Twitter](http://wordpress.org/extend/plugins/wp-to-twitter/))
*	Managing locations
*	Fetch events from a remote database. (Sharing events in a network of sites.)
*	Multisite-friendly
*	Integrated help page
*	Shortcode Generator to create customized views of My Calendar

= What's in My Calendar Pro? =

* Let your site visitors submit events to your site (pay to post or free!).
* Let logged-in users edit their events from the front-end.
* Create events when you publish a blog post
* Publish a blog post when you create an event
* Advanced search features
* Responsive mode
* Import events from .ics or .csv formats via file or URL.
* REST API support for sharing events between multiple sites.

= Translations =

Visit [Wordpress Translations](https://translate.wordpress.org/projects/wp-plugins/my-calendar) to check progress or contribute to your language.

Translating my plugins is always appreciated. Visit <a href="https://translate.wordpress.org/projects/wp-plugins/my-calendar">WordPress translations</a> to help get your language to 100%!

== Installation ==

1. Upload the `/my-calendar/` directory into your WordPress plugins directory.

2. Activate the plugin on your WordPress plugins page

3. Configure My Calendar using the settings pages in the admin panel:

   My Calendar -> Add New Event
   My Calendar -> Manage Events
   My Calendar -> Event Groups
   My Calendar -> Add New Location
   My Calendar -> Manage Locations
   My Calendar -> Manage Categories
   My Calendar -> Style Editor
   My Calendar -> Script Manager
   My Calendar -> Template Editor
   My Calendar -> Settings
   My Calendar -> Help

4. Visit My Calendar -> Help for assistance with shortcode options or widget configuration.

== Changelog ==

= 3.3.22 =

* Bug fix: Mismatched variable type broke default week view.

= 3.3.21 =

* Bug fix: Accidentally stripped HTML out of all event titles with search excerpt highlighting.

= 3.3.20 =

* Bug fix: Recurring month by day not propagating correctly.
* Bug fix: Available admin input settings not displaying correctly.
* Bug fix: Unset style variables array could throw PHP warning.
* Change: Improvements to structure of search results.
* Change: Change default search result template.
* Add: search_results template tag with search term highlighting.

= 3.3.19 =

* Bug fix: Missing support for 'show_recurring' parameter in shortcode builder.
* Bug fix: Checkbox input layout in shortcode builder
* Bug fix: Locations content filter needs to be restricted to main query only.
* Complete documentation of hooks.

= 3.3.18 =

* Bug fix: img and svg category icon styles applied to list items in category admin.
* Bug fix: duplicate sprintf call missing arguments.
* Bug fix: Globally review & align var type declarations with params & returns in functions & documentation.
* Bug fix: Fix some date iteration on recurring events in iCal exports.
* Change: Return http 500 if invalid URL passed to print view return URL.
* Continuing hook documentation.

= 3.3.17 =

* Security Fix: XSS flaw in print view.
* Bug fix: View full calendar could be empty if settings not edited.
* Bug fix: View full calendar text default not translatable.
* Bug fix: Allow class attribute on time element.
* Change open in new tab text to 'new tab'.
* Label error source in cases where wp_die() is used.
* Switch subscription links to webcal: protocol.
* Begin adding hook documentation at https://joedolson.github.io/my-calendar/
* Begin adding framework for future version of template handling.

= 3.3.16 =

* Bug fix: Incorrectly passed list type caused templates to encode html entities.

= 3.3.15 =

* Bug fix: CPT base values shouldn't allow URL-invalid characters.
* Bug fix: Improper variable type checking in mc_settings checkboxes.
* Bug fix: Unset search variable in auto-generated display hashes.
* New filters: Filter event object. (multilanguage support in Pro)
* New filter: Filters on single event HTML. (multilanguage support in Pro)
* Add: `language` attribute in main, today, and upcoming events shortcodes.

= 3.3.14 =

* Bug fix: Variables undefined if scripts disabled.
* Update tested to value for WP 6.0.

= 3.3.13 =

* Bug fix: Don't display empty field containers if field settings are empty.
* Bug fix: Pass version number to core stylesheet.
* Bug fix: Prevent warning from undefined GET variable in previous/next event links.
* Bug fix: Allow strong, b, and hr in shortcode output.
* Bug fix: Override masking; causing too many display problems.
* Bug fix: JS classes in main output used inverted comparisons.
* Notice: Custom JS will be disabled and removed in 3.4.
* Change: Collapsed view scripts from five files to one file.
* Change: Load new combined file minified.

= 3.3.12 =

* Bug fix: Find title and find event should use nearest event, not first event.
* Bug fix: Title replacement used event ID improperly if mc_id not passed.
* Bug fix: Seed GUID with home_url.
* Bug fix: Don't throw warnings if host/author ID no longer exists.
* Bug fix: Handle recurring event codes if passed from event importer.

= 3.3.11 =

* Bug fix: Modifying a category didn't refresh the icon SVG for that category.
* Bug fix: Contextual help should be viewable with 'view help' capability.
* Bug fix: Allow img in event title templates.
* Bug fix: 'all' category limit could be cast to an integer, breaking links.
* Change: text changes to location select label for clarification of purpose.
* Change: allow mc_bulk_actions() to take a second argument with events to modify.

= 3.3.10 =

* Bug fix: aria-current not rendered on event manager filters.
* Bug fix: published filter not marked as current by default.
* Bug fix: Cache allowed sites for CORS headers on multisite networks.
* Bug fix: fread error if no stylesheet defined.
* Bug fix: Extra closing div in single-day view.
* Bug fix: Better support for local user time.
* Change: Wrapper function for My Calendar time format: `mc_time_format()`

= 3.3.9 =

* Feature: Ability to merge duplicate locations.
* Bug fix: New locations created with events were not properly saved with the event, leading to possible location duplications.
* Bug fix: Add location to table should not be checked when copying an event.
* Bug fix: Possible fix to meta permissions.
* Bug fix: Fall back to non-fulltext queries if search term below length limit.
* Bug fix: 'search' nav item not rendering.

= 3.3.8 =

* Bug fix: Generated a duplicate location if event with location unselected location.
* Bug fix: Setting an event's all day label text to blank should not be overridden by defaults.
* Bug fix: Delete single event from front-end pointed to wrong destination.
* Bug fix: Missing help text for copying events.
* Change: Minor text change to empty location value.
* Change: Clear list items in list view (CSS)

= 3.3.7 =

* Bug fix: Fixes location admin verification error in manage locations list.

= 3.3.6 =

* Bug fix: Event template previews should only show to users who can use them.
* Bug fix: Category key icons should show background colors when configured.

= 3.3.5 =

* Bug fix: Default values for screen options were not called.
* Bug fix: Event count dots should not show in print view.
* Bug fix: PHP notice if mc_id not set on single event views.
* Bug fix: Documentation link led to removed page.
* Bug fix: Modal help links should open in parent window, not within modal.
* Bug fix: Search query sent to docs site should not be translatable.
* Bug fix: JPG or GIF custom icons should be accepted.
* Bug fix: Template attributes containing HTML stripped attributes in template manager.
* Bug fix: PHP Warning when checking for private category property and object not defined.
* Bug fix: Don't show admin grid view location dropdown if more than 200 locations.
* Bug fix: Prevent large icons from overflowing custom icon list.
* Bug fix: Fix display of custom icons in icons modal.
* Performance: only run mc_create_tags() once per event.
* Performance: cache whether icons are custom rather than inspecting directory for every icon load.
* New filter: `mc_display_location_events` on upcoming event arguments for location screen.
* Change: label My Calendar page in pages list.

= 3.3.4 =

* Bug fix: is_single() shouldn't be called in admin
* Bug fix: Prevent invalid events from breaking year dropdown.
* Bug fix: Make sure category colors are important.
* Bug fix: Set margins to 0 on input/button in nav.
* Bug fix: Decreasing font sizes in nav caused too many problems in older themes.
* Bug fix: Don't insert locations if no data passed to inserter.
* Bug fix: Delete location argument was not used.
* Bug fix: don't output empty locations.
* Bug fix: 'span' is not an attribute on 'span'.
* Bug fix: Verify validity of category relationships when parsing admin lists.
* Bug fix: $templates was undefined and broke saving templates.
* Bug fix: missing quote in 'delete template' button.
* Bug fix: custom templates sanitized incorrectly.
* Bug fix: translations link went to old translations site.
* Bug fix: Handle what happens if default category is deleted.
* Bug fix: Invalid class not reset in admin lists.
* Bug fix: date displayed in wrong timezone in admin recurring events list.
* Change: If location without any unique data is listed in admin, auto delete.
* Change: changes to add dates UI to clarify usage.

= 3.3.3 =

* Bug fix: Timezone omits positive/negative signifier in JSON LD in UTC+ timezones.
* Bug fix: Widen location autocomplete field.
* Bug fix: Fix show location shortcode templating.
* Bug fix: Recur daily by weekday did not produce valid times.
* Bug fix: Skip holidays default state filter missing.
* Bug fix: Only apply default state on special case recurrence fields on new events.
* Bug fix: Category relationships not updated correctly if category deleted.
* Bug fix: File path incorrectly referenced when finding custom icon directories.

= 3.3.2 =

* Change: Add classes representing start time and event length.
* Bug fix: Remove unneeded generic class declarations.
* Bug fix: Show stored location, not event location, in events list.
* Bug fix: Add missing elements to KSES filters for widgets.
* Bug fix: Incorrect logic to hide read more link.
* Feature: Add field to set calendar month heading. 

= 3.3.1 =

* Bug fix: Bulk removal of locations broken.
* Bug fix: SVG category icons should not be queried remotely; use filesystem.
* Layout: wider max-width by default, center calendar in container.
* Bug fix: Display more information link had inverted logic & wrong label.
* Bug fix: Don't show location link if location is not post type mc-locations.
* Bug fix: Week view could end up offset incorrectly in some views due to dates getting double timezone offsets.
* Bug fix: Provide back-compatibility for tabs in older versions of My Calendar Pro

= 3.3.0 =

Backend Changes:

* Replaced date picker with the <a href="https://github.com/duetds/date-picker">Duet Design Systems accessible date picker</a>.
* Accessibility & usability improvements to adding additional occurrences to an event. (DB change)
* Add support for custom fields on locations. <a href="https://github.com/joedolson/plugin-extensions/blob/master/my-calendar/mc-custom-location-fields.php">See demo at Github</a>
* Extensive back-end user experience changes.
* Link location title to edit screen in location manager
* Improve checkbox labeling in event manager.
* Improve button labeling in nav ordering.
* Add row actions to Location manager.
* Add support for custom columns in location manager.
* Bug fix: use aria tab panels properly in settings.
* Removed upgrade cycles & associated code for upgrading from version 2.3.x (last release in 2015.)
* Support aria-sort on sortable tables.
* Locations support both descending & ascending sort.
* Bug fix: pagination when sorting in event manager.
* Update settings configuration for default calendar URL.
* New setting to control whether plugin settings are removed on uninstall.
* Text changes for clarity & simplification
* Change 'Short Description' to 'Excerpt' for clarity
* Collapse 'Event Groups' and 'Events List' into a single screen.
* Inline help pop-ups
* Show event count for category links.
* Add settings manager to My Calendar primary view page.
* Updated recurring events input method.
* Add category during event creation.
* Make event bulk actions a dropdown.
* With Google Maps API, auto query lat/lon data for locations.
* Add calendar view for navigating events in admin.
* Simplify featured image support.
* Use checkboxes to select categories in widgets & shortcode generator.
* Show warning if screen has unsaved changes
* Template tag & event template previews.

Bug fixes:

* Bug fix: Deleting a location from the location manager should not send user to the location editor.
* Bug fix: row action links not properly labeled.
* Bug fix: row action links not becoming visible on focus.
* Bug fix: PHP warning on installations without saved locations.
* Bug fix: Screen options weren't able to retrieve user settings correctly.
* Bug fix: Event manager displayed recurring event options on single event editing screens.
* Bug fix: Form overflows in responsive views.
* Bug fix: Need breaking container in map bubble after location name.
* Bug fix [a11y]: Ensure focus isn't loss in sortable lists; announce change via wp.a11y.speak.
* Bug fix: If no previous or next event, generated numerous PHP errors.
* Stylesheet previewer in Design manager.
* Only show "special scheduling options" when relevant.
* Add Help tab to explain statuses.
* Add color picker to CSS variable UI

Frontend changes:

* Add front-end location view.
* Update default custom templates.
* Support filtering by multiple locations in calendar shortcodes or by filter.
* Change: use a stateful heading for all calendar views.
* Change: Support AJAX navigation on date select form.
* Bug fix: Override custom select styles from Twenty Twenty One
* New: recurring-event classes in event lists.
* Rewrote Google Maps scripting
* New SVG category icons, sourced from Font Awesome (https://fontawesome.com/license)
* New default stylesheet: twentytwentyone.css
* Individual display settings for different calendar views
* Creates demo content on initial installation.
* Enable pretty permalinks by default on new installations
* Add accessibility fields as a default event output.
* Removed RSS feeds.
* Always show event title in pop-up.
* Update default date/time formatting.
* Support search in calendar navigation.
* Support category dropdown in calendar navigation.
* Support location dropdown in calendar navigation.
* Support accessibility feature dropdown in calendar navigation.
* Support ld+json schema.org data for events and locations.
* Changed heading structure for main calendar view.
* Add event number to list view and event number hint in mini view.
* 'Show recurring' flag in upcoming events list.
* Upcoming events list should not wrap empty value in `ul`
* New default stylesheet

Developer Changes:

* New actions: 'mc_event_happening', 'mc_event_future', 'mc_event_over' executed whenever an event is compared to the current time, usable for automatic transitions and notifications.
* Filter: 'mc_output_is_visible' to determine whether a given display feature should be shown on calendar.
* Disable sending email notifications for Spam events. Add action to optionally handle spam notifications.
* Remove the process shortcodes option. Shortcodes can be disabled using 'mc_process_shortcodes' filter.
* Published documentation to https://docs.joedolson.com/my-calendar/
* New filter to add custom permissions. 'mc_capabilities'
* New filter for event details `mc_event_detail_{value}`
* Started work on documenting filters and actions.
* Code reorganization.
* PHP 8.0 compatibility.

= Future Changes =

* Refactor options storage

== Frequently Asked Questions ==

= Hey! Why don't you have any Frequently Asked Questions here! =

Because the majority of users end up on my web site asking for help anyway -- and it's simply more work to maintain two copies. Please visit [my web site FAQ](https://www.joedolson.com/my-calendar/faq/) to read my Frequently Asked Questions!

= This plugin is complicated. Why won't you help me figure out how to use it? =

I will! But not in person. Take a look at my [documentation website for My Calendar](https://docs.joedolson.com/my-calendar/) or the [developer hook documentation](https://joedolson.github.io/my-calendar/) before making your request, and consider [making a donation](https://www.joedolson.com/donate/) or [buying My Calendar Pro](https://www.joedolson.com/my-calendar/pro/)!

= Can my visitors or members submit events? =

I've written a premium plugin that adds this feature: My Calendar Pro. [Buy it today](https://www.joedolson.com/my-calendar/pro/)!

= Is there an advanced search feature? =

The search feature in My Calendar is pretty basic; but [buying My Calendar Pro](https://www.joedolson.com/my-calendar/pro/) gives you a richer search feature, where you can narrow by dates, categories, authors, and more to refine your event search.

== Screenshots ==

1. Monthly Grid View
2. List View
3. Event management page
4. Category management page
5. Settings page
6. Location management
7. Style editing
8. Template editing

== Upgrade Notice ==

Security Update: Please update to version 3.3.17 or later as soon as possible.