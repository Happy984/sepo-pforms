# Sepo Forms

Multi-step forms for WordPress, with sections, file uploads, response charts and CSV export.
Sepo Forms walks people through a form one section at a time. Every response stays in your own database. Form managers get a summary of the answers with charts, a searchable table and a CSV download. It runs on WordPress alone.

<p align="center">
  <a href="https://edu.sepodesk.com/sponsor/projects-maintenance/">
    <img src="https://img.shields.io/badge/%E2%9D%A4%20SUPPORT%20OUR%20OPEN--SOURCE%20PROJECTS-0b6b45?style=for-the-badge&logo=githubsponsors&logoColor=white" alt="Support Our Open-Source Projects" />
  </a>
</p>

<p align="center">
  Your support helps us maintain, improve and build open-source software for everyone.
  <br />
  <strong>Every contribution makes a difference. Thank you for supporting our work!</strong>
</p>

![Version](https://img.shields.io/badge/version-1.0.0-0b3d33)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)
https://edu.sepodesk.com/sponsor/projects-maintenance/
![Version](https://img.shields.io/badge/version-1.0.0-0b3d33)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)


## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
- [Shortcodes, blocks and widgets](#shortcodes-blocks-and-widgets)
- [Google Forms (optional)](#google-forms-optional)
- [Privacy](#privacy)
- [Uninstalling](#uninstalling)
- [FAQ](#faq)
- [For developers](#for-developers)
- [Changelog](#changelog)
- [License](#license)

## Features

### Building forms

- Questions are grouped in sections, and each section becomes one step. Sections can carry help text.
- Question types:
  - short text and paragraph;
  - multiple choice and checkboxes;
  - number and date;
  - email, phone and web address.
- Each question can be required, and can have help text, a placeholder and an image.
- Drag questions by their handle to reorder them, within a section or across sections. This works with a mouse, a finger or a pen. Every question also has up, down, duplicate and delete buttons.
- Start from a template: contact, course feedback, expense claim, job application, team registration, visitor card or event RSVP.
- You can also paste a form written as text with **Import**. **Copy as text** gives the same text back.

### Form settings

- Open or closed.
- Public, or private (shown only to people who can edit the form).
- Require sign-in, and allow one response per signed-in user.
- Collect the respondent's email.
- File uploads:
  - name the files you want, one per line;
  - choose which are required;
  - set a size limit.

  Files are kept out of the public uploads listing, and only form managers can download them.
- A thank-you message, or a page to send people to after they submit.
- A sort order for the forms list.

### Responses

Open **Forms → Responses**, or a form's **Responses** link. It shows:

- totals for responses, signed-in users and guests, and the latest response;
- a summary for each question: doughnut charts for choices, figures for numbers and counts for written answers;
- a table with search, sorting, column choice and pages;
- a detailed view of each response with its files, and an option to remove it;
- a CSV export of every response.

### Design

- Flat design: tinted panels and outlines, no shadows.
- Set the accent colour under **Forms → Settings**.
- Dark mode follows the page. It works with any theme that marks the page as dark (for example a `dark` class or `data-theme="dark"`), or when the page background itself is dark.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 8.1 |

## Installation

### Upload the zip (recommended)

1. Download `sepo-forms.zip`.
2. In your WordPress dashboard, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the zip file and click **Install Now**.
4. Click **Activate**. A **Forms** page is created that lists your public forms.

### Upload the folder

1. Unzip the download.
2. Copy the `sepo-forms` folder into `wp-content/plugins/` on your server.
3. Go to **Plugins** and activate **Sepo Forms**.

> [!NOTE]
> GitHub's **Code → Download ZIP** names the folder after the branch, for example `sepo-forms-main`. Rename it to `sepo-forms` before uploading.

## Getting started

1. Go to **Forms → Add form**, or pick a template.
2. Add sections and questions. The title is the form title. The short description sits above the questions.
3. Publish the form. It has its own address at `/forms/<form-slug>/`, and it appears on the Forms page.
4. Read the answers under **Forms → Responses**.

To use a different page for the forms list, choose it under **Forms → Settings**.

## Shortcodes, blocks and widgets

| Shortcode | What it shows |
|---|---|
| `[sepo_form id="123"]` or `[sepo_form slug="course-feedback"]` | One form. Add `title="no"` to hide its heading. |
| `[sepo_forms]` | The forms list. Options: `per_page="12"`, and `view="grid"` or `view="list"`. |

The plugin also includes:

- the **Sepo form** and **Sepo forms list** blocks;
- the **Sepo form** and **Sepo forms list** Elementor widgets, in their own "Sepo forms" category.

## Google Forms (optional)

Form managers can copy a form to Google Forms with one click. This needs your own Google Cloud OAuth client, and the steps are on **Forms → Settings**.

While this is switched off, the plugin never contacts Google.

## Privacy

- Responses are stored in your database, in the `{prefix}sepo_form_submissions` table.
- Each respondent's IP address and browser are kept with the response, for spam control.
- Uploaded files are kept in `wp-content/uploads/sepo-forms-private/`.

## Uninstalling

**Deactivating** the plugin (**Plugins → Deactivate**) stops it and keeps everything.

**Deleting** the plugin (**Plugins → Deactivate**, then **Delete**) removes the plugin files.

- **By default your forms and responses are kept.**
- **To remove everything**, first tick **When the plugin is deleted, also delete every form, response and uploaded file** under **Forms → Settings** and save, then delete the plugin. This removes:
  - every form;
  - the responses table;
  - the private uploads folder;
  - the Forms page the plugin created, as long as it still holds the forms list;
  - the settings.

## FAQ

**Who can see responses?**
Anyone who can edit the form. By default that is administrators and editors, and authors for their own forms.

**How is spam kept out?**
There is a hidden field that people never see but bots fill in. There is also a limit of 20 submissions per address every 10 minutes.

**What happens to responses when a form is deleted?**
A form in the bin keeps its responses. Deleting the form permanently also deletes its responses and files.

**Are uploaded files private on nginx?**
On Apache and IIS, the private folder is closed by the `.htaccess` and `web.config` files the plugin writes. nginx ignores those files. There, the protection comes from long random file names and a folder with no listing. For full protection, ask your host to add this to the server block:

```nginx
location ^~ /wp-content/uploads/sepo-forms-private/ { deny all; }
```

## For developers

| Hook | Type | Arguments / purpose |
|---|---|---|
| `sepo_forms_submitted` | action | `( array $record, int $formId )` runs after a response is saved. |
| `sepo_forms_submission_deleted` | action | `( string $id, int $formId )` runs after a response is removed. |
| `sepo_forms_templates` | filter | Adds or changes the builder templates. |
| `sepo_forms_upload_mimes` | filter | Sets the file types allowed for uploads. |
| `sepo_forms_ip` | filter | Gives the respondent's IP, for sites behind a proxy. |
| `sepo_forms_show_title_on_form_page` | filter | Return `true` to show the form's own heading as well as the theme's title. |

## Changelog

### 1.0.0
- First release.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
