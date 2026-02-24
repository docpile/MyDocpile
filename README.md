# MyCloud
Feature rich PHP cloud software created mainly using AI

Actually, I wanted to see how far one could come using AI to build a feature rich cloud software. The idea came as I worked with AI to solve certain code problems. This is how far I came after about 6 weeks of part time work.

Nobody said the resulting code would be beautiful (in fact, it's quite ugly)... But surprisingly, it works pretty well!

Where security critical, I also intervened and even did some coding myself. The login handler here is just a simplified version of the one I use in reality (would be to complex in the setup to include all the stuff here). The code published here is not "bullet proof" - but likely more than sufficient for your "private NAS" at home.

# MyCloud - Advanced AJAX File Explorer

MyCloud is a highly responsive, AJAX-only web file explorer that provides a native Windows-style layout directly in the browser. It features a rich set of file management tools, real-time media streaming, document previews, and an integrated SSH terminal.

**Very Important Notice:** *None* - I repeat: *none* - of the MyCloud PHP code is to be stored within the www-root. From the very beginning, it was designed to, except a small index.php stub, completely live outside of www-root. This was a deliberate design decision to elimiate many security risks. 

## 🚀 Features

### Core File Management
* **Rich UI & Layouts:** Windows-style layout with a resizable Sidebar Tree, List view, Gallery view, and Symbol view.
* **Standard Operations:** Create, Delete, Rename, Batch Rename, Move, Copy, and Upload files/folders.
* **Recycle Bin:** Safe deletion system with an isolated `.recycle_bin` directory and meta-tracking for easy restoration.
* **Advanced Search & Filtering:** Search files globally with filters for date ranges (`1h`, `24h`, `week`, etc.) and size limits.
* **Command Palette:** Keyboard-driven command palette (invoked via `>` or typing) for quick navigation and operations.

### Media & Document Handling
* **Smart Previews:** Generates image thumbnails on-the-fly with hardware-accelerated fallbacks (`Imagick` -> `GD`), and caches them for performance.
* **EXIF Extraction:** Reads and displays image metadata (Camera, Exposure, Aperture, ISO, Dimensions) natively.
* **Video/Audio Streaming:** HTTP Range support (`206 Partial Content`) for native, scrubbable streaming of `.mp4`, `.webm`, `.mp3`, and `.mkv` files.
* **Integrated Editors & Viewers:**
  * Native text editing for code and text files.
  * In-browser rendering of `.docx` (via `docx-preview`) and `.xlsx` (via `SheetJS`).
  * PDF viewer with floating toolbars.

### Archive Management
* **Zip Extraction:** Browse the contents of `.zip` archives natively without extracting them, or extract specific files on the fly.
* **Dynamic Zipping:** Download entire folders as `.zip` files generated dynamically on the server.

### Admin & Power-User Tools
* **Commander Mode:** Dual-pane file management mode for power users.
* **Admin Mode:** Option to connect to (user/password protected) SSH/SFTP as a separate "cloud". 
* **SSH Terminal:** For "Admin Mode" cloud tabs, fully integrated SSH terminal emulator using `Xterm.js` with auto-fitting and background stream maintenance. Not the fastest, but enough for small tasks.
* **Built-in Ticketing System:** Lightweight JSON-based bug tracker and changelog generator for project management.
* **Device Intelligence:** Automatically detects OS, touch capabilities, and screen folding to adapt the UI dynamically.

---

## ⚠️ Limitations

* **Tightly Coupled Architecture:** The frontend CSS, JavaScript, and HTML are delivered entirely via inline PHP includes (`init.php`, `styles.php`), making the initial document payload large and difficult to cache natively via CDNs or web servers.
* **Resource Intensive Processing:** On-the-fly zip generation and recursive directory stat calculations can consume significant CPU and RAM on large directories, despite built-in timeout and memory limiters. Keep that in mind or change that if needed.
* **Statefulness:** Relies heavily on PHP Sessions (`$_SESSION`) for CSRF validation and role authorization. This requires sticky sessions if deployed across a load-balanced cluster.
* **External CDN Dependencies:** Relies on third-party CDNs (unpkg, cdn.sheetjs.com) for document previewers. These assets must be downloaded and hosted locally (see below under "Setup & Installation".
* **Zip File Limits:** Folder downloads are restricted by a predefined size limit (`$zip_warn_limit`) to prevent server memory exhaustion.

---

## 🛠️ Setup & Installation

### 1. Requirements
* **PHP:** PHP 7.4+ or 8.x
* **Extensions:** `zip`, `mbstring`, `fileinfo` (for EXIF data). `Imagick` or `GD` (for image processing).
* **Composer:** A `vendor/autoload.php` is required (specifically expecting `MatthiasMullie\Minify`).

### 2. Configuration
The system expects several global variables to be defined **before** the code is included in your main application flow.

```php
// Define core paths
$cloud_path = '/path/to/user/files/';       // Absolute path to the user's root folder
$cloud_dir  = __DIR__ . '/mycloud_source/'; // Path to the MyCloud source files
$work_dir   = __DIR__ . '/';                // Web root or project root

// Define constraints
$cloud_max_preview_size = 10485760; // 10MB
$zip_warn_limit = 314572800;        // 300MB

// Access Roles (Configured via $GLOBALS['user_details'])
// Roles determine permissions: 'full', 'modify', 'read-only', 'no-access', 'admin_mode'



