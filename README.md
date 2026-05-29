# My Document Pile - MyDocpile
![Main UI](https://github.com/docpile/MyDocpile/blob/main/images/02_main_cloud_ui.png?raw=true)

**Feature rich PHP cloud and webmail software created mainly using AI**

Actually, I wanted to see how far one could come using AI to build a feature rich cloud software. The idea came up as I worked with AI to solve certain code problems. This is how far I came after about 6 months of part time work.

Nobody said the resulting code would be beautiful (in fact, in most places it's quite ugly)... But surprisingly, it works pretty well! It almost became an "Operating System in the browser"!

Where security critical, I also intervened and even did some coding myself. The login handler here is just a simplified version of the one I use in reality (would be to complex in the setup to include all the stuff here). The code published here is not "bullet proof" - but likely more than sufficient for your "private NAS" at home.

[Changelog](cloud%20(not%20on%20www-root!)/cloud/versioninfo.txt)

[Skip screenshots](#mydocpile)

## Screenshots

**Login**

![Login](https://github.com/docpile/MyDocpile/blob/main/images/01_login.png?raw=true)

**Webmail UI**

![Webmail UI](https://github.com/docpile/MyDocpile/blob/main/images/03_main_webmail_ui.png?raw=true)

**Gallery Mode** - especially handy on mobile devices

![Gallery Mode](https://github.com/docpile/MyDocpile/blob/main/images/04_gallery_mode.png?raw=true)

**File Preview** - for many different file types

![File Preview](https://github.com/docpile/MyDocpile/blob/main/images/05_file_preview_for_many_filetypes.png?raw=true)

Optional: **OnlyOffice integration** - makes the cloud almost a desktop replacement

![OnlyOffice](https://github.com/docpile/MyDocpile/blob/main/images/06_optional_onlyoffice.png?raw=true)

**PDF Management** (also stacking/unstacking documents, print e.g. Word documents to PDF and so on)

![PDF Management](https://github.com/docpile/MyDocpile/blob/main/images/07_pdf_toolkit.png?raw=true)

**Document Management** (called Office View)

![Document Management](https://github.com/docpile/MyDocpile/blob/main/images/08_office_view_-_document_management.png?raw=true)

**Commander View** - Similar to Total Commander

![Commander View](https://github.com/docpile/MyDocpile/blob/main/images/09_commander_view.png?raw=true)

**End-To-End File Encryption** - Encrypted vaults

![End-To-End File Encryption](https://github.com/docpile/MyDocpile/blob/main/images/10_e2e_encrypted_vaults.png?raw=true)

**Dark Mode** - For mobile devices or people with impaired vision

![End-To-End File Encryption](https://github.com/docpile/MyDocpile/blob/main/images/11_dark_mode.png?raw=true)

**Many more Options** - Mostly, for each device type different settings. E.g. on your tablet larger fonts, on your mobile dark mode, and so on.

![Options](https://github.com/docpile/MyDocpile/blob/main/images/12_options.png?raw=true)

**Multilingual Support** - As many as 20 languages, also RTL. Mostly AI translated. If more are needed, simply request them. Or help out where the AI was lacking...

![Multilingual](https://github.com/docpile/MyDocpile/blob/main/images/13_multilingual_with_rtl_support.png?raw=true)

Now let's come to the software:

## MyDocpile

MyDocpile is a highly responsive, AJAX-only web file explorer and web based mail app that provides a native Windows-style layout directly in the browser. It features a rich set of file management tools, real-time media streaming, document previews, and if wanted even an integrated SSH terminal.

The MyDocpile software is designed to have **the least depenencies possible**. So, no big MySQL database (in fact, as for the app itself, no db at all). Of course, this does not scale much. On the other hand, *it was never a design goal to write a second Nextcloud!* 

**❗Very Important Notice:** *None* - repeat: **_none_** - of the MyDocpile PHP code or its data is to be stored within the www-root. From the very beginning, it was designed to, except for a small index.php stub and some js, css and image files needed, **_completely live outside of www-root_**. This was a deliberate design decision to elimiate many security risks commercial products are suffering from. And as you cannot trust AI too much security-wise, better safe than sorry. Make sure to adjust your PHP "openbasedir" setting accordingly (See the installation guide; the install.sh will show you the necessary changes to make). 

## 🚀 Features

### 1. Core File & Directory Management
* **Standard Operations:**
  * Full support for Create (files/folders), Delete, Move, Copy, and Rename.
* **Batch Operations:**
  * Supports sequential batch processing for moving, copying, or deleting multiple files simultaneously with intelligent state tracking.
* **Conflict Resolution:**
  * Built-in alerts for file conflicts (e.g., file already exists)
* **Advanced Upload System:** * Drag-and-drop file uploads *directly* into the UI interface.
  * Preserves modified timestamps from the client.
  * Verifies e.g. disk space before initiating the upload.
  * Hard-blocks system configuration overrides (e.g., `.htaccess`, `web.config`, `.user.ini`).
* **Deep Search Engine:**
  * **Text Matching:** Searches recursively through directories.
  * **Time Filters:** Granular filters including `1h`, `4h`, `24h`, `week`, `month`, `3months`, `year`, or entirely custom date ranges.
  * **Size Filters:** Pre-defined ranges (`Small <100KB`, `Medium 100KB-10MB`, `Large 10MB-1GB`, `Huge >1GB`) and custom MB ranges.
* **Smart Recycle Bin:**
  * Safe deletion moves files to an isolated `.recycle_bin` directory.
  * Generates `.meta` JSON files to track the exact timestamp of deletion and the original source path.
  * One-click restore functionality accurately places the file back in its original location or a custom chosen path.
* **File Tagging & Favorites:**
  * Users can tag files with custom color-coded labels or add them to a personal favorites list, stored in isolated JSON profile configs.
  * These tags can be filtered afterwards, e.g. to separte projects or private/work folders and files
  * Favorites can help you jump directly to a folder or file. You can rename and sort them in the menu
* **SFTP Clouds:**
  * It is possible to configure a user for a SFTP cloud, either to administer a server, or for file access to another cloud. SSH key authentication is deliberately not implemented as this could easily end up to be a security nightmare in this context (a password the user can be asked for every time, but a SSH key?). Of course, if you like, you can simply build in that as well. It's just a few lines of code.

### 2. Archiving & Compression
* **Dynamic Zipping:** Download entire folders as `.zip` files generated dynamically on the server. Includes a safety threshold (`$zip_warn_limit`, defaulting to 300MB) to prevent server memory exhaustion.
* **Native Archive Browsing:** The file tree and list views can "step into" `.zip` files and browse their internal file structure identically to standard folders without extracting them to disk.
* **Surgical Extraction:** Extract specific files from within a `.zip` directly to the active folder, or extract the entire archive natively.

### 3. Layouts, UI & Navigation
* **Multi-Language support:**
  *  **Full RTL Support:** Automatically mirrors layouts, arrows, and toggle behaviors if the active language is Right-to-Left (e.g., Arabic...).
  *  **Many languages included:** Major languages are included (most of them AI translated, so feel free to change the translations) 
* **Multiple View Modes:**
  * **List View:** Detailed data table with sortable columns, custom checkboxes, and sticky headers.
  * **Gallery View:** Grid-based masonry layout prioritizing image thumbnails with hover-zoom interactions. Perfect for mobile devices.
  * **Icon View / Icon Dark:** Icon-centric desktop layout.
* **Commander Mode:** A dual-pane layout for power users to manage files side-by-side with independent view states and split-ratio tracking. 
* **Multi-Cloud Switcher:** Top-level ribbon tabs that allow users to seamlessly hot-swap between multiple cloud accounts or server mount points without reloading the application. Cave: Since these clouds are security boundaries, you cannot copy and paste between them.
* **Resizable Tree View:** A draggable, resizable directory tree. You can use it almost like the explorer on your own device.
* **Command Palette:** A keyboard-driven command interface (invoked via `>` or typing directly) to search files or trigger commands (e.g., `>Upload File`, `>Open SSH Terminal`).
* **Device Intelligence:** Evaluates the type of hardware running on the user side to adapt spacing and interactions for Desktop, Touch-Laptops, Tablets, Phones, and even Foldable devices. Most settings can be done independently for **desktop** (including touch support), **tablet** (including foldables), and **phones** to suit your needs on different hardware.
* **Marquee Selection:** Click-and-drag "lasso" box selection for desktop users (because it's a mouse interaction).
*

### 4. Media & Document Previews
* **High-Performance Smart Thumbnails:** * Generates lightweight thumbnail caches on the server to prevent CPU load on repeat visits.
  * Hardware-accelerated processing via `Imagick` (supports heavy formats like RAW, PSD, TIFF, PDF) with a fallback to `GD` for standard web images.
  * Automatically reads EXIF orientation data to instantly correct rotated images.
* **EXIF Metadata Reader:** Extracts and displays rich photography data in a native modal (Camera Make/Model, Exposure Time, Aperture `f/`, ISO, Dimensions, and Original Date).
* **Native Video/Audio Streaming:** Supports HTTP 206 Partial Content (Range requests) allowing users to scrub and stream `.mp4`, `.mkv`, `.webm`, and `.mp3` files natively without downloading the entire payload.
* **Interactive Image Previewer:** * Includes a bottom "filmstrip" showing sibling images in the same folder.
  * Pan, zoom, rotate, and flip capabilities handled by a custom matrix transform engine.
* **In-Browser Document Rendering:**
  * Renders `.docx` using `docx-preview`.
  * Renders `.xlsx` using `SheetJS`, complete with a ribbon bar to swap between Excel sheet tabs.
  * Floating PDF toolbars for paging and scaling natively.

### 5. Advanced System & Power User Tools
* **Integrated SSH Terminal:** * A `Xterm.js` emulator wrapped inside a movable, minimizable modal window.
  * Maintains a background stream with chunk processing for high-frequency updates (e.g., running `htop`).
  * Not the fastest on the market, but for executing a brief command line task it will do.
  * Rights depend on the user the SSH/SFTP cloud is connecting with.
  * SSH key authentication is deliberately not implemented as this could easily end up to be a security vulnerability in this context.
* **Directory Statistics (Treemap):** Recursively calculates deep directory metrics (total files, total size, child distribution) with multi-layered limits (timeout, max memory, max files) to prevent server hangs, rendered into an interactive UI Treemap.
* **Built-in Ticketing System:** A native bug tracker and feature request form directly within the file explorer.
* **Changelog Generator:** Admins can close tickets and immediately increment the semantic version (Major, Minor, Patch), which dynamically updates the application's `versioninfo.txt` file.

### 6. Security Infrastructure
* **Role-Based Access Control (RBAC):** Filesystem operations strictly check against user permissions (`full`, `modify`, `read-only`, `no-access`, `admin_mode`). 
* **Path Jailing:** Uses aggressive validation to ensure bad actors cannot execute directory traversal attacks (`../../`) outside their designated `$cloud_path`.
* **Cryptographic CSRF Validation:** Tokens and nonces are generated using multi-entropy sources and required on every single write or sensitive read operation. 
---

## ⚠️ Limitations

* **Tightly Coupled Architecture:** The frontend CSS, JavaScript, and HTML are delivered entirely via inline PHP includes. On the other hand, the MyDocpile itself does not use any big pictures and loads itself exactly once per session. The bytes delivered over the network are comparable or even less than Nextcloud (depending on the Nextcloud setup even dramatically less).
* **Resource Intensive Processing:** On-the-fly zip generation and recursive directory stat calculations can consume significant CPU and RAM on large directories, despite built-in timeout and memory limiters. Keep that in mind or edit the limits in the config if needed.
* **Statefulness:** Relies heavily on PHP Sessions (`$_SESSION`). Does CSRF validation and role authorization. For cluster load-balancing: This requires sticky sessions if deployed across a cluster.
* **External CDN Dependencies:** Relies on third-party CDNs for document previewers and webmail. These assets must be downloaded and hosted locally. (will be done automatically during setup)
* **Zip File Limits:** Folder downloads are restricted by a predefined size limit (`$zip_warn_limit`) to prevent server memory exhaustion.

---

## 🛠️ Setup & Installation

### 1. Requirements
* **Operating System:** As it needs some executable code outside of PHP to function (mostly for PHP and image processing), sorry to say currently it's running on Linux systems only. Actually, it was tested on Ubuntu only.
* **PHP:** PHP 8.4 or higher
* **Webserver:** Any webserver software would do, with Nginx preferred.
* **Mailserver:** For 2FA, for security reasons, a local mailserver account is needed. If you do not use the SFTP "Admin Mode" plugin, 2FA is not used and a local mailserver is not needed. 
* **Extensions:** `zip`, `mbstring`, `fileinfo` (for EXIF data). `Imagick` or `GD` (for image processing). Also further packages are required, see install.sh for a detailed list. 
* **Composer:** Is being autofilled within the installation directory of the main app during setup.

### 2. Configuration
The system expects several config.php variables to be defined. Most of them are automatically set up during installation. 

### 3. Integration Into Other Applications
The login UI submitted here is just an excerpt of the login used in my much larger (and safer) real-life application login; however, it gives you an impression of the needs of the MyDocpile application itself and should be sufficiently secure for smaller implementations.  

### 4. Installation on Your Server

1. Create a domain with an empty www-root on your webserver.

2. Secure it with a certificate.

3. Get PHP 8.4 running on that domain.

4. Now download the whole code into an empty directory (or, download and umpack an release ZIP file):

`git clone https://github.com/docpile/MyDocpile.git`

then execute the file install.sh

`cd ./MyDocpile`

`bash ./install.sh`

Follow the on-screen instructions. 

That's it.

After your first login, do not forget to enter the `Options`, `Admin` tab to configure the clouds for your users as needed. Here, you can also rename the `cloudadmin` user.






