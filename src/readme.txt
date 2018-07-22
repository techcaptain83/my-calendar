=== My Calendar ===
Contributors: joedolson
Donate link: http://www.joedolson.com/donate/
Tags: calendar, dates, times, event, events, scheduling, schedule, event manager, event calendar, class, concert, venue, location, box office, tickets, registration
Requires at least: 4.4
Tested up to: 4.9
Requires PHP: 5.3
Stable tag: 3.0.13
Text domain: my-calendar
License: GPLv2 or later

Accessible WordPress event calendar plugin. Show events from multiple calendars on pages, in posts, or in widgets.

== Description ==

My Calendar does WordPress event management with richly customizable ways to display events. The plug-in supports individual event calendars within WordPress Multisite, multiple calendars displayed by categories, locations or author, or simple lists of upcoming events.

Easy to use for anybody, My Calendar provides enormous flexibility for designers and developers needing a custom calendar. My Calendar is built with accessibility in mind, so all your users can get equal access and experience in your calendar.

= Premium Event Management =
Looking for more? [Buy My Calendar Pro](https://www.joedolson.com/my-calendar/pro/), the premium extension for My Calendar to add support for user-submitted events, integration between posting and event creation, and import events from outside sources.

= Selling event tickets? =
Do you need to sell tickets for events? [Use My Tickets](https://wordpress.org/plugins/my-tickets/) and sell tickets for your My Calendar events. Set prices, ticket availability, and sell multiple events at the same time using My Tickets.

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

> * Let your site visitors submit events to your site (pay to post or free!).
> * Let logged-in users edit their events from the front-end.
> * Create events when you publish a blog post
> * Publish a blog post when you create an event
> * Advanced search features
> * Responsive mode
> * Import events from .ics or .csv formats via file or URL.

= Translations =

Visit [Wordpress Translations](https://translate.wordpress.org/projects/wp-plugins/my-calendar) to check progress or contribute to your language.

Translating my plug-ins is always appreciated. Visit <a href="https://translate.wordpress.org/projects/wp-plugins/my-calendar">WordPress translations</a> to help get your language to 100%!

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

= 3.0.14 =

* Bug fix: incorrect value passed for instance parameter on single event shortcode.
* Bug fix: hide HTML wrapper for category color when colors disabled.
* Bug fix: Remove transparent background in Twenty eighteen; blocks category colors
* Bug fix: Invalid ordering parameter for location lists
* Feature: Ability to select multiple categories (props Josef Fällman)
* Moved changelogs for 2.5 & earlier to changelog.txt

= 3.0.13 =

* Bug fix: missing function call when accessing custom mini templates
* Bug fix: Syntax error in SQL query checking for conflicts
* Change: pass short description to Akismet if long desc absent

= 3.0.12 =

* Bug fix: My Calendar could prevent canonical link from displaying if canonical link being filtered by another application.
* Modernize & improve Akismet integration.
* Add filter to disable Akismet checks.

= 3.0.11 =

* SECURITY: XSS - Canonical URL not properly sanitized. Affects 3.0.0 and up. 

= 3.0.10 =

* Bug fix: invalid method used to sort location lists.
* Bug fix: shortcode generator missing input value
* Bug fix: datepicker did not reflect start of week settings
* Stylesheet CSS change

= 3.0.9 =

* Bug fix: Error thrown if Akismet had previously been configured, then deleted.
* Bug fix: location type was added to params if category key was set.
* Bug fix: remove a couple notices
* Bug fix: category relationships not carried over when recurring events split

= 3.0.8 =

* Bug fix: need to allow <a> elements in mc_strip_tags so calendar linkscan point to non-calendar URLs

= 3.0.7 =

* Bug fix: Case where events ending at midnight (AM) of current day were displayed
* Bug fix: trim spaces from values earlier when parsing filter elements
* Change: don't declare font-family in older stylesheets. 

= 3.0.6 =

* Bug fix: Shortcode for locations forms always rendered as if in a group filter.
* Bug fix: If the default length 1 hr event pushes into next day, adjust length.
* Bug fix: Incorrectly nested parentheses caused math error in months-by-day recurrence

= 3.0.5 =

* Bug fix: If only one event on a day, event title did not show in list view with show title option.
* Bug fix: Incorrect array key for fallback parameter in widget
* Bug fix: custom template query expected 25 chars instead of 32
* Re-allow <br> in event titles.

= 3.0.4 =

* Bug fix: aria-current test was broken for current date
* Bug fix: Private categories not disambiguated in MySQL query when excluded
* Improve: Rewrite my_calendar_copyr backup functions to use WP core functions.

= 3.0.3 =

* Bug fix: Category key needed to use a 'WHERE' not an 'AND'; broke output if limiting by category
* Bug fix: Error thrown in style editor & category editor if custom directory did not exist

= 3.0.2 =

* 3.0.1 did not correct the right error. Correct fix.

= 3.0.1 =

* Bug fix: install error on update.

= 3.0.0 =

* Bug fix: If category deleted, set events with that category to default cat, not cat ID 1.
* Bug fix: Date/time comparison used front-end date value instead of dtstamp in upcoming events.
* Bug fix: Navigation issue if beginning of week is in previous month
* Bug fix: Event conflict didn't catch events 100% contained inside other events.
* Bug fix: Private categories should not be visible to public users in submission forms or category lists
* Bug fix: aria-current key term value was translatable
* Bug fix: If editing single instance, location is removed
* Bug fix: don't show location control notices on front-end
* Bug fix: correcting event recurrence did not always remove meta flag
* Bug fix: Only output map HTML if API key provided
* Bug fix: character set and collation determination on install & update
* Bug fix: When changing recurring events, only change instance IDs if the date of the instance has changed.
* Bug fix: Event post should not change post date on update
* Bug fix: All day events should export correctly to Outlook & Apple Calendar
* Bug fix: Location control accordion was not accessible.
* Bug fix: Term ID was not set in category manager if term already existed.
* Bug fix: Make sure that the 's' query var is not automatically added to My Calendar URLs

* Add: several new filters
* Add: notice to alert users if their calendar configured for remote event source.
* Add: map display to back-end location manager.
* Add: location search in location manager
* Add: ability to filter location lists used to submit data 'mc_get_locations'
* Add: Support for multiple categories on events.
* Add: stylesheet (Twenty Eighteen)
* Add: CSS variables support
* Add: list of problem events in Manage Events sidebar
* Add: add months shown in list view to shortcode parameters
* Add: support for auto-refresh of cache for a variety of caching plug-ins.
* Add: Option to remove event data on uninstall
* Add: filter to define events as private via custom methods
* Add: event preview
* Add: location support to mini calendar widget
* Add: CSS code editor available in Style editing
* Add: HTML code editor available in Template editing
* Add: Schema.org address markup
* Add: Schema.org event markup
* Add: Include event link in 'Add to Google Cal' content.
* Add: date format for multi-day dates in grid view.

* Removed: event open & event closed text settings
* Removed: event_open event status (little used and confusing; replaced by My Tickets)
* Removed: guessing calendar install location
* Removed: event cache code
* Removed: upgrade routines from 1.11.x
* Removed: mc_widget_defaults option
* Removed: user's guide references

* Change: default image sizes from 'medium' to 'large'
* Change: Remove ability to disable event approval; remap "approval" to "draft"
* Change: default number of results to show in advanced event search.
* Change: Switched from image to icon font for close button
* Change: Major changes to event fetching
* Change: Major changes to code organization
* Change: Added caching on database engine query
* Change: if event location set in dropdown, event will always display location as shown in location manager
* Change: changed argument style for major functions to arrays of arguments
* Change: move Location Manager to separate page; add location sorting.
* Change: Move exif_ fallback function into utilities include
* Change: Moved location & category specific settings
* Change: Simplified texts in several locations
* Change: Clearer UI on location input limits
* Change: autotoggle end date minimum input when start date set
* Change: Reorganized input fields
* Change: Generate separate iCal exports for Google Calendar or Outlook
* Change: Constrain tabbing within details pop-up
* Change: Close details pop-up with Esc key
* Change: Audited options to remove unused or unneeded options
* Change: Create a referential template when shortcode generated
* Change: Feeds nav panel now shows subscription links; exports are available in 'exports' panel.

= Future Changes =

* Refactor options storage
* Revise month by day input & calculation methods
* Bug: if save generates error, creates ton of notices. [eliminate $submission object and use single object model]
* Add ability to limit by multiple locations (e.g., view all events in Location 1 & Location 2; only on lvalue)
* JS to delete events from front-end when logged-in
* TODO: delete this instance and all subsequent instances

== Frequently Asked Questions ==

= Hey! Why don't you have any Frequently Asked Questions here! =

Because the majority of users end up on my web site asking for help anyway -- and it's simply more difficult to maintain two copies of my Frequently Asked Questions. Please visit [my web site FAQ](http://www.joedolson.com/my-calendar/faq/) to read my Frequently Asked Questions!

= This plug-in is really complicated. Why can't you personally help me figure out how to use it? =

I can! But not in person. Take a look at my [documentation website for My Calendar](http://docs.joedolson.com/my-calendar/) before making your request, and consider [making a donation](https://www.joedolson.com/donate.php)!

= Can my visitors or members submit events? =

I've written a paid plug-in that adds this feature to My Calendar, called My Calendar Pro. [Buy it today](https://www.joedolson.com/my-calendar/pro/)!

= Is there an advanced search feature? =

The search feature in My Calendar is pretty basic; but buying My Calendar Pro gives you a richer search feature, where you can narrow by dates, categories, authors, and more to refine your event search.

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

* 3.0.11 URGENT: Security fix - XSS scripting vulnerability resolved.