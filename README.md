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

**❗Very Important Notice:** *None* - repeat: **_none_** - of the MyDocpile PHP code or its data is to be stored within the www-root. From the very beginning, it was designed to, except for a small index.php stub and some js, css and image files needed, **_completely live outside of www-root_**. This was a deliberate design decision to elimiate many security risks commercial products are suffering from. Make sure to adjust your PHP "open_basedir" setting accordingly (See the installation guide; the install.sh will show you the necessary changes to make). 

## 🚀 Features

### 1. Layouts, UI & Navigation
* **Multiple View Modes:**
  * **Tree View Pane:** A draggable, resizable directory tree. You can use it almost like the Explorer/Finder/Nautilus on your own device.
  * **List View:** Detailed data table with sortable columns, custom checkboxes, and sticky headers.
  * **Gallery View:** Grid-based masonry layout prioritizing image thumbnails with hover-zoom interactions. Perfect for mobile devices. No Tree View Pane here.
  * **Icon View:** In the details pane quite similar to Gallery View, but more "Explorer-like". Also displays the Tree View pane.
  * **Commander Mode:** A dual-pane layout for power users to manage files side-by-side with independent view states and split-ratio tracking. 
  * **Webmail Mode:** Full featured secure webmail app. Users can - as the rights are granted - also add mailboxes on other servers (optionally even outlook.com). Also, mails can be saved to the clouds either as EML or as PDF files. 
* **Multi-Cloud Switcher:** Top-level tabs allow users to seamlessly hot-swap between multiple cloud accounts without reloading the application. Cave: Since these clouds are security boundaries, you cannot copy and paste between them.
* **Command Palette:** A keyboard-driven command interface (invoked via `>` or typing directly) to search files or trigger commands (e.g., `>Upload File`, `>Open SSH Terminal`).
* **Device Intelligence:** Evaluates the type of hardware running on the user side to adapt spacing and interactions for Desktop, Touch-Laptops, Tablets, Phones, and even Foldable devices. Most settings can be done independently for **desktop** (including touch support), **tablet** (including foldables), and **phones** to suit your needs on different hardware.
* **Multi-Language support:**
  *  **Full RTL Support:** Automatically mirrors layouts, arrows, and toggle behaviors if the active language is Right-to-Left (e.g., Arabic...).
  *  **Many languages included:** Major languages are included (most of them AI translated, so feel free to change the translations if needed) 

### 2. Core File & Directory Management
* **Standard Operations:**
  * Full support for Create files and folders, Delete, Move, Copy, and Rename... Much of that also in a batch mode.
* **Advanced Upload System:**
  * Drag-and-drop file uploads *directly* into the UI interface
  * Preserves modified timestamps from the client
* **Deep Search Engine:**
  * **Several Filters:** Granular filters for names, date and size ranges
  * **Optional full-text search:** Full text index search with OCR features and several parameters available.
* **File Tagging & Favorites:**
  * You can tag files with custom color-coded labels or add them to a personal favorites list.
  * These tags can be filtered afterwards, e.g. to separte projects or private/work folders and files
  * Favorites can help you jump directly to a folder or file. You can rename and sort them in the menu
* **SFTP Clouds:**
  * It is possible to configure a user for a SFTP cloud, either to administer a server, or for file access to another cloud. SSH key authentication is deliberately not implemented as this could easily end up to be a security nightmare in this context - a password the user can be asked for every time, but a SSH key?
* **End-to-End encrypted vaults:**
  * Uses state-of-the-art encryption to generate fully encrypted directories. It uses a per-file encryption, thereby also encrypting the file names.

### 3. Archiving & Compression
* **Dynamic Zipping:** Download entire folders as `.zip` files generated dynamically on the server. 
* **Native Archive Browsing:** The file tree and list views can "step into" `.zip` files and browse their internal file structure identically to standard folders without extracting them to disk.
* **Surgical Extraction:** Extract specific files from within a `.zip` directly to the active folder, or extract the entire archive natively.

### 4. Media & Document Previews
* **High-Performance Smart Thumbnails:** Generates lightweight thumbnail caches on the server to prevent CPU load on repeat visits.
* **EXIF Metadata Reader:** Extracts and displays rich photography data in a native modal (Camera Make/Model, Exposure Time, Aperture `f/`, ISO, Dimensions, and Original Date).
* **Native Video/Audio Streaming:** Supports scrub and stream of `.mp4`, `.mkv`, `.webm`, and `.mp3` files natively.
* **Interactive Image Previewer:** Includes a bottom "filmstrip" showing sibling images in the same folder.
* **In-Browser Document Rendering:**
  * Renders e.g. `.pdf`, `.eml`, `.docx` and `.xlsx` as well
  * Floating toolbars for paging and scaling natively.
  * Arrows to navigate between files without leaving the preview 

### 5. Advanced System & Power User Tools
* **Integrated SSH Terminal:**
  * A `Xterm` emulator wrapped inside a window in SFTP mode.
  * Not the fastest on the market, but for executing a brief command line task it will do
  * Rights depend on the user the SSH/SFTP cloud is connecting with
* **Directory Statistics (Treemap):** Recursively calculates deep directory metrics (total files, total size, child distribution), rendered into an interactive UI Treemap.

### 6. Webmail
* **Multi-account:** You can have multiple accounts connected here, all nicely brought together into a "SmartBox"
* **Security first:**
  * Phishing resistance: Many security measures implemented to limit phishing
  * Spam protection: Shows the DMARC result and whether the mail was transmitted via TLS
  * Tracking protection: All pictures (of course except identified tracking) are proxied by the server, thereby making sender tracking profiles almost useless
  * Scripting protection: Heavily sandboxed mail display. Therefore, no code whatsoever can be executed by emails. 

### 7. Security Infrastructure
* **Security first:** Follows a consequent security first strategy
* **Login security:**
  * Several measures taken against brute forcing
  * Also, the login process is isolated from the logged in context
  * Password hashes are consequently checked against HIBP at every single login
* **Zero Trust (almost):** Implements a strict regime for all server calls; most of the calls are even following zero trust principles
* **CSRF Protection:** Tokens and nonces are generated using multi-entropy sources and required on every single write or sensitive read operation 
* **Role-Based Access Control (RBAC):** All operations strictly check against fine grained user permissions 
* **Path Jailing:** Uses aggressive validation to ensure bad actors cannot execute directory traversal attacks 
* **Cache Management:** Mail caches, address books and other sensitive private data is stored encrypted on the disk. This currently is not end-to-end (maybe in a future release), but at least way more secure than other apps do (by putting this clear-text into a database) 

## ⚠️ Limitations

* **Tightly Coupled Architecture:** The frontend CSS, JavaScript, and HTML are delivered entirely via inline PHP includes. On the other hand, the MyDocpile itself does not use any big pictures and loads itself exactly once per session. The bytes delivered over the network are comparable or even less than Nextcloud (depending on the Nextcloud setup even dramatically less).
* **Resource Intensive Processing:** On-the-fly zip generation and recursive directory stat calculations can consume significant CPU and RAM on large directories, despite built-in timeout and memory limiters. Keep that in mind or edit the limits in the config if needed.
* **Statefulness:** Relies heavily on PHP Sessions (`$_SESSION`). Does CSRF validation and role authorization. For cluster load-balancing: This requires sticky sessions if deployed across a cluster.
* **External CDN Dependencies:** Relies on third-party CDNs for document previewers and webmail. These assets must be downloaded and hosted locally. (will be done automatically during setup)
* **Zip File Limits:** Folder downloads are restricted by a predefined size limit (`$zip_warn_limit`) to prevent server memory exhaustion.

---

## 🛠️ Setup & Installation

### 1. Requirements
* **Operating System:** As it needs some executable code outside of PHP to function (mostly for PHP and image processing), sorry to say currently it's *running on Linux systems only*. Actually, it was tested on Ubuntu only.
* **PHP:** *PHP 8.4* or higher
* **Webserver:** Any webserver software would do, with *Nginx* preferred.
* **Mailserver:** For 2FA, for security reasons, a local mailserver account is needed. If you do not use the SFTP "Admin Mode" plugin, 2FA is not used and a local mailserver is not needed. 
* **Extensions:** `zip`, `mbstring`, `fileinfo` (for EXIF data). `Imagick` or `GD` (for image processing). Also further ubuntu/debian packages are required, see install.sh for a detailed list. All dependencies will be checked and installed automatically. 
* **Composer:** Is being automatically installed within the installation directory of the main app only. No  system modifications here.

### 2. Configuration
The system expects several config.php variables to be defined. Most of them are automatically set up during installation. 

### 3. Integration Into Other Applications
The login UI submitted here is just an excerpt of the login used in my much larger (and safer) real-life application login; however, it gives you an impression of the needs of the MyDocpile application itself and should be sufficiently secure for smaller implementations.  

### 4. Installation on Your Server

1. Create a domain with an empty www-root on your webserver.
2. Secure it with a certificate.
3. Get PHP 8.4 running on that domain.
4. Now download the whole code into an empty directory (or, download and unpack an release ZIP file):

    `git clone https://github.com/docpile/MyDocpile.git`
   
6. then execute the file install.sh:

    `cd ./MyDocpile`

     `bash ./install.sh`

7. Follow the on-screen instructions. 
8. Add the open_basedir as requested by the installation and restart PHP.
9. Implement the changes as layed out in the respective .sample files here (e.g. to get webdav and/or OnlyOffice running). 

That's it.

After your first login, do not forget to enter the `Options`, `Admin` tab to configure the clouds for your users as needed. Here, you can also rename the `cloudadmin` user.






