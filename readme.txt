=== Sepo Forms ===
Contributors: euneolink
Tags: forms, survey, feedback, multi-step form, file upload
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-step forms with sections, file uploads, response analytics and CSV export. Runs on WordPress alone, with no outside services.

== Description ==

Sepo Forms builds forms that walk people through one section at a time, keeps every response in your own database, and gives form managers a summary of the answers with charts, a searchable table and a CSV download.

= Building forms =

* Forms live under **Forms → Add form**. The title is the form title and the short description sits above the questions.
* Questions are grouped in sections; each section becomes one step. Sections can carry help text.
* Question types: short text, paragraph, multiple choice, checkboxes, number, date, email, phone and web address.
* Each question can be required, have help text, a placeholder and an image.
* Drag questions by their handle to reorder them, within a section or across sections. This works with a mouse, a finger or a pen. Up, down, duplicate and delete buttons are on every question too.
* Start from a template (contact, course feedback, expense claim, job application, team registration, visitor card, event RSVP), or paste a form written as text with **Import**. **Copy as text** gives the same text back.

= Form settings =

* Open or closed; public or private (private forms show only to people who can edit them).
* Require sign-in, and allow one response per signed-in user.
* Collect the respondent's email.
* File uploads: name the files you want (one per line), choose which are required, and set a size limit. Files are stored outside the public uploads listing and can only be downloaded by form managers.
* A thank-you message, or a page to send people to after they submit.
* A sort order for the forms list.

= Responses =

**Forms → Responses** (and each form's "Responses" link) shows:

* Totals for responses, signed-in users, guests and the latest response.
* A summary per question: doughnut charts for choices, figures for numbers, and counts for written answers.
* An individual view: a table with search, sorting, column choice and pages, a detailed view of each response with its files, and removal.
* CSV export of every response.

= Showing forms on the site =

* Each form has its own address: `/forms/<form-slug>/`.
* On activation a "Forms" page is created that lists every public form, with search and a grid or list view. Choose another page under **Forms → Settings**.
* Shortcodes:
  * `[sepo_form id="123"]` or `[sepo_form slug="course-feedback"]` shows one form. Add `title="no"` to hide its heading.
  * `[sepo_forms]` lists the forms. Options: `per_page="12"` and `view="grid"` or `view="list"`.
* Blocks: **Sepo form** and **Sepo forms list**.
* Elementor: the **Sepo form** and **Sepo forms list** widgets, in their own "Sepo forms" category.

= Design =

The forms keep a flat look: tinted panels and outlines, no shadows. Set the accent colour under **Forms → Settings**. Dark mode follows the page: the forms switch to their dark palette when the theme marks the page as dark (a `dark`, `dark-mode` or `theme-dark` class, a `data-theme="dark"` or `data-bs-theme="dark"` attribute and similar), or when the page background itself is dark. This works with any theme.

= Google Forms (optional) =

Form managers can copy a form to Google Forms with one click. This needs your own Google Cloud OAuth client; the steps are on **Forms → Settings**. Leave it switched off and the plugin never contacts Google.

= Privacy =

Responses are stored in your database in the `{prefix}sepo_form_submissions` table. The IP address and browser of each respondent are kept with the response for spam control. Uploaded files are kept in `wp-content/uploads/sepo-forms-private/`.

== Installation ==

1. Upload the `sepo-forms` folder to `/wp-content/plugins/`, or upload the zip under **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. A "Forms" page is created for the forms list.
3. Go to **Forms → Add form**, or pick a template.

== Frequently Asked Questions ==

= Are uploaded files private on nginx? =

On Apache and IIS the private folder is closed by the `.htaccess` and `web.config` files the plugin writes. nginx ignores those files, so there the protection comes from long random file names that cannot be guessed and a folder with no listing. For full protection on nginx, add this to the server block:

`location ^~ /wp-content/uploads/sepo-forms-private/ { deny all; }`

= Who can see responses? =

Anyone who can edit the form: by default administrators, editors, and authors for their own forms.

= How is spam kept out? =

A hidden field that people never see but bots fill in, and a limit of 20 submissions per address every 10 minutes.

= What happens to responses when a form is deleted? =

A form in the bin keeps its responses. Deleting it permanently also deletes its responses and files.

== Developer hooks ==

* `sepo_forms_submitted` (action): `( array $record, int $formId )` after a response is saved.
* `sepo_forms_submission_deleted` (action): `( string $id, int $formId )` after a response is removed.
* `sepo_forms_templates` (filter): add or change the builder templates.
* `sepo_forms_upload_mimes` (filter): the file types allowed for uploads.
* `sepo_forms_ip` (filter): the respondent IP, for sites behind a proxy.
* `sepo_forms_show_title_on_form_page` (filter): return true to show the form's own heading on its page as well as the theme's title.

== Screenshots ==

1. A multi-step form with sections.
2. Responses summarised question by question.

== Changelog ==

= 1.0.4 =
* The plugin is by Euneolink Solutions. Its plugin page and author page are edu.sepodesk.com.

= 1.0.3 =
* Readme: screenshots and their captions for the plugin directory.

= 1.0.2 =
* Admin screens: settings shown as cards, lists in a rounded frame, rounded fields and buttons, pill-shaped tabs and filters, and notices on tinted backgrounds.

= 1.0.1 =
* Uploaded files are stored and served through the WordPress file-system API.
* Uninstall clean-up runs inside a function, so it leaves no global variables behind.
* Added the languages folder with a translation template.
* Passes the WordPress Plugin Check: translator comments on every string with placeholders, escaped output, sanitised request data and documented database access to the plugin's own tables.
* Tested up to WordPress 7.1.

= 1.0.0 =
* First release.
