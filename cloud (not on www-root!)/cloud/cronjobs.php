<?php
/**
 * myCloudHousekeeper Class (Optimized for Large Datasets >100k files)
 * Background process for thumbnail generation and cache cleanup.
 *
 * OPTIMIZATIONS:
 * 1. Hybrid Memory/IO Strategy: Builds an in-memory map of valid files for instant orphan detection.
 * Falls back to disk checks if RAM is exhausted.
 * 2. Iterators: Uses raw SplFileInfo for speed.
 * 3. Lookups: Uses Hash Maps (flipped arrays) for O(1) extension checking.
 * * USAGE: php /path/to/cloud/cron_housekeeping.php
 * sudo -u username php -d memory_limit=10240M -d max_execution_time=0 /path/to/cloud/cron_housekeeping.php >> /var/log/cloud_housekeeping.log 2>&1
 */

class myCloudHousekeeper {
    
    // Configuration
    private $workDir;
    private $iconCachePath;
    private $iconMaxPixel;
    private $iconQuality;
    private $previewCachePath;
    private $previewMaxPixel;
    private $previewQuality;
    private $usersDir;
    
    private $retentionDays;

    // State
    private $validSourcePaths = []; // Hash map for fast orphan detection
    private $memoryLimitReached = false;
    private $validExts = []; // Flipped array for O(1) lookup
    private $stopRequested = false;

    // Multi-Processor State
    private $maxWorkers = 1;
    private $pids = [];
    private $batchSize = 50;
    private $verbose = false;
    
    // Locking & Heartbeat
    private $lockFileHandle;
    private $lockFilePath;
    private $heartbeatInterval = 5; // Seconds between touches
    private $lastHeartbeat = 0;
    private $stalledThreshold = 600; // 10 Minutes = Stalled

    // Stats
    private $stats = [
        'scanned' => 0,
        'icons_created' => 0,
        'previews_created' => 0,
        'orphans_removed' => 0,
        'skipped' => 0,
        'start_time' => 0
    ];

    /**
     * Constructor
     */
    public function __construct($workDir) {
        if (php_sapi_name() !== 'cli') {
            die("Error: myCloudHousekeeper must be run via command line.");
        }

        // Check for PCNTL
        if (!function_exists('pcntl_fork')) {
            die("Error: PCNTL extension is required for multi-processor support.");
        }

        $this->workDir = rtrim($workDir, '/\\');
        $this->usersDir = $this->workDir . '/configuration';
        $this->loadConfig();
        
        // Flip extensions for O(1) lookup speed (PDF REMOVED as requested)
        $exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif', 'mp4', 'webm', 'mov', 'mkv', 'avi'];
        $this->validExts = array_fill_keys($exts, true);
        
        // Detect CPUs and set Workers (N-1)
        $cpuCount = 1;
        if (is_file('/proc/cpuinfo')) {
            $cpuInfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuInfo, $matches);
            $cpuCount = count($matches[0]);
        } elseif (function_exists('exec')) {
            $nproc = @exec('nproc');
            if ($nproc) $cpuCount = (int)$nproc;
        }
        $this->maxWorkers = max(1, $cpuCount - 1);

        // Set Lock File Path (System Temp)
        $this->lockFilePath = sys_get_temp_dir() . '/MyCloud_Housekeeper.lock';

        // Set Lowest Priority (Nice 19)
        @pcntl_setpriority(19, getmypid(), 0);
        
        $this->stats['start_time'] = microtime(true);
        
        // [NEW] Register Signal Handlers (Ctrl+C)
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
        
        $this->log("Initialized with {$this->maxWorkers} workers at lowest priority.");
    }

    /**
     * Load and Validate Config
     */
    private function loadConfig() {
        global $cloud_icon_cache, $cloud_icon_maxpixel, $cloud_icon_quality;
        global $cloud_preview_cache, $cloud_preview_maxpixel, $cloud_preview_quality;

        if (empty($cloud_icon_cache) || empty($cloud_preview_cache)) {
            $this->log("Error: Cache paths not defined in config.");
            exit(1);
        }

        $this->iconCachePath     = rtrim($cloud_icon_cache, '/');
        $this->iconMaxPixel      = $cloud_icon_maxpixel ?? 90;
        $this->iconQuality       = $cloud_icon_quality ?? 60;
        
        $this->previewCachePath  = rtrim($cloud_preview_cache, '/');
        $this->previewMaxPixel   = $cloud_preview_maxpixel ?? 800;
        $this->previewQuality    = $cloud_preview_quality ?? 66;
        
        // Retention Days (Default 7 if missing)
        global $cloud_recycle_bin_retention_days;
        $this->retentionDays = isset($cloud_recycle_bin_retention_days) ? (int)$cloud_recycle_bin_retention_days : 7;

        $this->ensureDir($this->iconCachePath);
        $this->ensureDir($this->previewCachePath);
    }

    /**
     * [NEW] Handle Interrupt Signals
     */
    public function handleSignal($signo) {
        $this->stopRequested = true;
        $this->log("\n!!! Interrupt Received ($signo). Finishing current item then stopping...");
    }

    /**
     * Main Execution Loop
     * @param bool $doCache Perform cache generation and orphan cleanup
     * @param bool $doRecycler Perform recycle bin cleanup
     * @param bool $doSearchIndex Perform search indexing
     * @param bool $doOutbox Process stranded email queue files
     * @param bool $verbose Log detailed progress
     */
    public function run($doCache = true, $doRecycler = true, $doSearchIndex = true, $doOutbox = true, $verbose = false) {
        $this->verbose = $verbose; // Set verbose flag
        // 0. Robust Single-Instance Check
        if (!$this->acquireLock()) {
            // Log message handled in acquireLock
            exit(0); // Exit silently/cleanly
        }

        $this->log("--- Starting myCloud Housekeeping ---");
        $this->log("Mode: " . ($doCache ? "[Cache Refresh] " : "") . ($doRecycler ? "[Recycler Cleanup] " : "") . ($doOutbox ? "[Outbox Sweeper]" : ""));
        
        // Optimize PHP Environment
        set_time_limit(0);
        ini_set('memory_limit', '10240M'); 
        gc_disable();

        // 1. Get Roots
        $roots = $this->getCloudRoots();
        $this->log("Found " . count($roots) . " cloud root directories.");

        // --- RECYCLER CLEANUP ---
        if ($doRecycler) {
            $this->log(">>> Task: Cleaning Recycle Bins (Retention: {$this->retentionDays} days)");
            $this->cleanRecycleBins($roots);
            
            $this->log(">>> Task: Cleaning Stale Temporary Files");
            $this->cleanTempFiles();
        }

        $this->log(">>> Task: Cleaning Expired Shares & Tickets");
        $this->cleanExpiredDatabases();
        
        // --- SEARCH INDEXING ---
        if ($doSearchIndex) {
            $searchRoots = $this->getSearchIndexRoots();
            $this->log(">>> Task: Updating/Creating Search Indexes (Recoll) for " . count($searchRoots) . " default roots");
            $this->updateSearchIndexes($searchRoots);
        }

        // --- OUTBOX SWEEPER ---
        if ($doOutbox) {
            $this->log(">>> Task: Sweeping Stranded Email Outboxes");
            $this->processAllOutboxes();
        }

        // --- CACHE REFRESH ---
        if ($doCache) {
            $this->log(">>> Task: Scanning Sources & Building Caches");
            foreach ($roots as $root) {
                $this->log("-> Scanning Root: " . $root);
                $this->processSourceDirectory($root);
            }

            // Wait for final workers to finish Phase 1
            $this->waitForWorkers();
            
            $duration = round(microtime(true) - $this->stats['start_time'], 2);
            $this->log("Scan Done in {$duration}s. Scanned: {$this->stats['scanned']}, Icons: {$this->stats['icons_created']}, Previews: {$this->stats['previews_created']}");

            $this->log(">>> Task: Cleaning Orphans");
            
            if ($this->memoryLimitReached) {
                $this->log("Note: High file count detected. Using Disk-Check mode.");
            } else {
                $this->log("Note: Using RAM-Map mode. Tracked " . count($this->validSourcePaths) . " valid paths.");
            }

            $this->cleanCacheDirectory($this->iconCachePath, true);
            $this->cleanCacheDirectory($this->previewCachePath, false);

            $this->log("Cache Cleanup Done. Removed {$this->stats['orphans_removed']} orphans.");
        }

        $this->log("--- Finished ---");
        $this->releaseLock();
    }

    /**
     * Iterates over all users and triggers their outbox processor asynchronously
     * This acts as a safety net for any jobs that failed to spawn via the web UI.
     */
    private function processAllOutboxes() {
		global  $work_dir;
        $serverScript = __DIR__ . '/controller.server.email.php';
        $outboxRoots = glob($this->usersDir . '/*_outbox', GLOB_ONLYDIR);
        $processedCount = 0;
        
        foreach ($outboxRoots as $box) {
            $jobs = glob($box . '/*.job');
            if (!empty($jobs)) {
                $baseName = basename($box);
                $username = substr($baseName, 0, -7); // Remove '_outbox'
                $logFile = $workdir . '/data/cronjob_mail_worker.log';
				
                $cmd = sprintf(
                    'php %s "myCloud_action=email_process_outbox&myCloud_cli_user=%s" >> %s 2>&1 &',
                    escapeshellarg($serverScript),
                    escapeshellarg($username),
                    escapeshellarg($logFile)
                );
                
                if ($this->verbose) $this->log("Spawning outbox processor for user: $username");
                @exec($cmd);
                $processedCount++;
            }
        }
        
        if ($this->verbose && $processedCount > 0) {
            $this->log("Triggered $processedCount queue processors.");
        }
    }

    /**
     * Robust Locking with Stalled Process Detection
     */
    /**
     * Robust Locking with Stalled Process Detection
     */
    private function acquireLock() {
        $this->lockFileHandle = fopen($this->lockFilePath, 'c+');
        if (!$this->lockFileHandle) {
            $this->log("Error: Could not open lock file.");
            return false;
        }

        // 1. Try to get Exclusive Lock (Non-Blocking)
        if (flock($this->lockFileHandle, LOCK_EX | LOCK_NB)) {
            // Success: We are the only one. Write PID.
            ftruncate($this->lockFileHandle, 0);
            fwrite($this->lockFileHandle, getmypid());
            $this->updateHeartbeat(true); // Initial touch
            return true;
        } 
        
        // 2. Lock Failed: Someone else is running. Check if they are STALLED.
        $this->log("Lock busy. Checking for stalled process...");
        
        // Read the PID of the other process
        rewind($this->lockFileHandle);
        $otherPid = (int)stream_get_contents($this->lockFileHandle);
        
        // Check Last Heartbeat (File Modification Time)
        clearstatcache(true, $this->lockFilePath);
        $mtime = filemtime($this->lockFilePath);
        $age = time() - $mtime;

        if ($age > $this->stalledThreshold) {
            $this->log("!! DETECTED STALLED PROCESS (PID: $otherPid). Last heartbeat: {$age}s ago.");
            
            // Check if PID actually exists in OS
            if ($otherPid > 0 && posix_kill($otherPid, 0)) {
                $this->log("!! Killing stalled process $otherPid...");
                posix_kill($otherPid, SIGKILL);
                sleep(2); // Wait for OS to clean up
            } else {
                $this->log("!! Stalled process $otherPid is already dead.");
            }

            // The lock is likely held by an orphaned child process that inherited the file descriptor.
            // We must forcibly break the lock by destroying the old file and creating a new one.
            fclose($this->lockFileHandle);
            @unlink($this->lockFilePath);
            $this->lockFileHandle = fopen($this->lockFilePath, 'c+');

            // Retry Lock
            if (flock($this->lockFileHandle, LOCK_EX | LOCK_NB)) {
                $this->log("!! Lock acquired after cleanup.");
                ftruncate($this->lockFileHandle, 0);
                fwrite($this->lockFileHandle, getmypid());
                $this->updateHeartbeat(true);
                return true;
            }
        }

        $this->log("Job already running (PID: $otherPid, Heartbeat: {$age}s ago). Exiting.");
        return false;
    }

    private function releaseLock() {
        if ($this->lockFileHandle) {
            flock($this->lockFileHandle, LOCK_UN);
            fclose($this->lockFileHandle);
            @unlink($this->lockFilePath); // Optional: Remove file to clean up
        }
    }

    private function updateHeartbeat($force = false) {
        $now = time();
        if ($force || ($now - $this->lastHeartbeat) > $this->heartbeatInterval) {
            // Touching the file updates mtime, proving we are alive
            if ($this->lockFilePath && file_exists($this->lockFilePath)) {
                @touch($this->lockFilePath);
            }
            $this->lastHeartbeat = $now;
        }
    }

    /**
     * Recycle Bin Cleaner
     */
    private function cleanRecycleBins($roots) {
        $deletedCount = 0;
        $now = time();
        $retentionSeconds = $this->retentionDays * 86400;

        foreach ($roots as $root) {
            $recycleDir = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . '.recycle_bin';
            
            if (!is_dir($recycleDir)) continue;

            $files = scandir($recycleDir);
            foreach ($files as $f) {
                $this->updateHeartbeat(); // Keep lock alive
                if ($f === '.' || $f === '..') continue;

                // We drive cleanup via .meta files
                if (substr($f, -5) === '.meta') {
                    $metaPath = $recycleDir . DIRECTORY_SEPARATOR . $f;
                    $targetName = substr($f, 0, -5);
                    $targetPath = $recycleDir . DIRECTORY_SEPARATOR . $targetName;

                    // 1. Check for orphaned meta files
                    if (!file_exists($targetPath)) {
                        @unlink($metaPath);
                        continue;
                    }

                    // 2. Read Metadata
                    $metaContent = @file_get_contents($metaPath);
                    $metaData = json_decode($metaContent, true);

                    // Use 'time' from JSON, fallback to mtime if corrupted
                    $recycleTime = isset($metaData['time']) ? $metaData['time'] : filemtime($metaPath);

                    // 3. Check Age
                    if (($now - $recycleTime) > $retentionSeconds) {
                        // Delete Meta
                        @unlink($metaPath);
                        
                        // Delete Content (Recursive if directory)
                        if (is_dir($targetPath)) {
                            $this->rrmdir($targetPath);
                        } else {
                            @unlink($targetPath);
                        }
                        $deletedCount++;
                    }
                }
            }
        }
        $this->log("Recycler: Removed $deletedCount expired items.");
    }

    /**
     * Actively prunes expired items from the flat-file JSON databases
     */
    private function cleanExpiredDatabases() {
        // Assuming global database variables are available from config inclusion
        global $cloud_shares_db, $cloud_tickets_db;
        
        $databases = [
            'shares' => $cloud_shares_db ?? dirname($this->workDir) . '/configuration/shares.json',
            'tickets' => $cloud_tickets_db ?? dirname($this->workDir) . '/configuration/tickets.json'
        ];

        $now = time();

        foreach ($databases as $name => $dbPath) {
            if (!file_exists($dbPath)) continue;

            $fp = @fopen($dbPath, 'c+');
            if ($fp && @flock($fp, LOCK_EX)) {
                $size = filesize($dbPath);
                $data = ($size > 0) ? (@json_decode(fread($fp, $size), true) ?: []) : [];
                $originalCount = count($data);
                
                // Filter out entries where the 'expire' timestamp is set and is in the past
                $newData = [];
                foreach ($data as $item) {
                    $isExpired = !empty($item['expire']) && (int)$item['expire'] !== 0 && $item['expire'] <= $now;
                    
                    if ($isExpired) {
                        // Automatically delete Smart Cloud Attachment folders when their share expires
                        if ($name === 'shares' && !empty($item['path']) && strpos(str_replace('\\', '/', $item['path']), '/.mail/attachments/') !== false) {
                            if (file_exists($item['path'])) {
                                if (is_dir($item['path'])) {
                                    $this->rrmdir($item['path']);
                                } else {
                                    @unlink($item['path']);
                                }
                                $this->log("Smart Attachment Cleanup: Deleted expired email attachments at " . $item['path']);
                            }
                        }
                    } else {
                        $newData[] = $item;
                    }
                }
                $data = $newData;
                
                if (count($data) < $originalCount) {
                    ftruncate($fp, 0);
                    rewind($fp);
                    fwrite($fp, json_encode(array_values($data), JSON_PRETTY_PRINT));
                    $pruned = $originalCount - count($data);
                    $this->log("Database Pruning: Removed $pruned expired items from $name.json");
                }
                
                fflush($fp);
                @flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }
    
    /**
     * Cleans stale download zips and progress files from system temp
     */
    private function cleanTempFiles() {
        $tmpDir = sys_get_temp_dir();
        if (!$tmpDir || !is_dir($tmpDir)) return;

        $files = scandir($tmpDir);
        $now = time();
        $limit = 86400; // 24 Hours
        $count = 0;

        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            
            // Check for App Temp Files AND CLI Tool Leaks (ImageMagick, Ghostscript, QPDF, OCR)
            if (preg_match('/^(ce_dl_|cloudex_prog_|myCloud_dl_|myCloud_office_|myCloud_rl_|pdf_qpdf_|pdf_gs_|mycloud_ocr_|xfdf_|magick-)/', $f)) {
                $this->updateHeartbeat(); // Keep lock alive
                $fullPath = $tmpDir . DIRECTORY_SEPARATOR . $f;
                
                // Handle orphaned OCR directories specifically
                if (is_dir($fullPath) && strpos($f, 'mycloud_ocr_') === 0) {
                     if (($now - filemtime($fullPath)) > $limit) { $this->rrmdir($fullPath); $count++; }
                     continue;
                }

                if (is_file($fullPath)) {
                    if (($now - filemtime($fullPath)) > $limit) {
                        @unlink($fullPath);
                        $count++;
                    }
                }
            }
        }
        
        if ($count > 0) $this->log("Temp Cleanup: Removed $count stale temporary files.");
    }

    /**
     * Helper: Recursive Directory Delete
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . DIRECTORY_SEPARATOR . $object))
                        $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        @unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            @rmdir($dir);
        }
    }
    
    /**
     * Phase 1: Process Source Files
     */
    private function processSourceDirectory($path) {
        if (!is_dir($path)) return;

        // Fastest Iterator Configuration
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, 
                RecursiveDirectoryIterator::SKIP_DOTS | 
                RecursiveDirectoryIterator::FOLLOW_SYMLINKS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $batchJobs = [];

        foreach ($iterator as $file) {
            // [NEW] Check for Stop Request between files
            if ($this->stopRequested) { $this->log("--- Stopped safely by user ---"); break; }

            // [NEW] Clean up stale partial uploads/failed ClamAV scans (.uploading.xxxxx)
            if (strpos($file->getFilename(), '.uploading.') !== false) {
                if ((time() - $file->getMTime()) > 86400) {
                    @unlink($file->getRealPath());
                    if ($this->verbose) $this->log("Removed stale upload: " . $file->getFilename());
                }
                continue;
            }

            // Extension Check (O(1))
            // SplFileInfo::getExtension is fast
            if (!isset($this->validExts[strtolower($file->getExtension())])) continue;
            
            $this->updateHeartbeat(); // Keep lock alive

            $this->stats['scanned']++;
            $fullPath = $file->getRealPath();
            $mtime = $file->getMTime();

            // 1. Generate Safe Path Key (The "Mirror" Path)
            // Removes ':' (Windows drive) and leading slashes for a clean relative structure
            $safeRelPath = ltrim(str_replace(':', '', $fullPath), '/\\');

            // 2. Add to Memory Map (Fast Orphan Detection)
            if (!$this->memoryLimitReached) {
                $this->validSourcePaths[$safeRelPath] = true;
                
                // Safety valve: Check memory every 10,000 files
                if ($this->stats['scanned'] % 10000 === 0) {
                    if (memory_get_usage() > 800 * 1024 * 1024) { // > 800MB
                        $this->memoryLimitReached = true;
                        $this->validSourcePaths = []; // Dump array to free RAM
                        $this->log("!! Memory limit approaching. Switching to Disk-Check mode.");
                    }
                }
            }

            // 3. Check/Gen Icon
            $iconFile = $this->iconCachePath . '/' . $safeRelPath . '_thumb.jpg';
            if ($this->shouldGenerate($iconFile, $mtime)) {
                $batchJobs[] = ['type' => 'icon', 'src' => $fullPath, 'dest' => $iconFile];
            }

            // 4. Check/Gen Preview
            $prevFile = $this->previewCachePath . '/' . $safeRelPath . '.jpg';
            if ($this->shouldGenerate($prevFile, $mtime)) {
                $batchJobs[] = ['type' => 'prev', 'src' => $fullPath, 'dest' => $prevFile];
            }

            // Batch Processing: If batch size reached, launch worker
            if (count($batchJobs) >= $this->batchSize) {
                $this->launchWorker($batchJobs);
                $batchJobs = [];
            }

            // Periodic housekeeping
            if ($this->stats['scanned'] % 2000 === 0) {
                // Check workers (non-blocking) to reap zombies
                $this->checkWorkers(false);
                gc_collect_cycles();
                if ($this->verbose) {
                    $mem = number_format(memory_get_usage() / 1024 / 1024, 1);
                    $this->log(".. scanned {$this->stats['scanned']} files [Mem: {$mem}MB] ..");
                }
            }
        }

        // Process remaining jobs
        if (!empty($batchJobs)) {
            $this->launchWorker($batchJobs);
        }
    }

    /**
     * Helper: Launch Worker Process
     */
    private function launchWorker($jobs) {
        // If max workers reached, wait for one to finish
        if (count($this->pids) >= $this->maxWorkers) {
            $this->checkWorkers(true); // Blocking
        }

        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->log("Error: Fork failed");
        } elseif ($pid) {
            // Parent
            $this->pids[$pid] = true;
            // Update stats estimates
            foreach($jobs as $job) {
                if ($job['type'] === 'icon') $this->stats['icons_created']++;
                else $this->stats['previews_created']++;
            }
        } else {
            // Child
            $this->pids = []; // Clear parent pids from memory
            ini_set('memory_limit', '2048M');
            gc_disable();
            while (ob_get_level()) ob_end_clean();
            $this->workerExecuteBatch($jobs);
            // [CRITICAL FIX] Kill process instantly to avoid PHP shutdown crashes
            // Skipping the cleanup prevents 'double free' errors in shared memory
            if (function_exists('posix_kill')) {
                posix_kill(getmypid(), SIGKILL);
            }
            // Fallback for systems without posix extension
            @exec("kill -9 " . getmypid() . " > /dev/null 2>&1 &");
            sleep(5); // Pause execution until death occurs
            exit(0);  // Should never be reached
        }
    }

    /**
     * Helper: Worker Execution Logic
     */
    private function workerExecuteBatch($jobs) {
        // Re-seed RNG
        mt_srand();
        // [CRITICAL FIX] Disable OpenMP threading to prevent heap corruption
        // We are already parallel at the process level; ImageMagick must stay single-threaded.
        putenv('MAGICK_THREAD_LIMIT=1');
        putenv('OMP_NUM_THREADS=1');
       // Environment variables are often ignored if the library is already loaded.
        if (class_exists('Imagick')) {
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
            // Allow Imagick up to 1GB of RAM before safely overflowing to disk cache
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 1024 * 1024 * 1024); 
            Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, 1024 * 1024 * 1024);
        }
        foreach ($jobs as $job) {
            $this->ensureDir(dirname($job['dest']));
            $maxPx = ($job['type'] === 'icon') ? $this->iconMaxPixel : $this->previewMaxPixel;
            $qual = ($job['type'] === 'icon') ? $this->iconQuality : $this->previewQuality;
            
            $this->generateImage($job['src'], $job['dest'], $maxPx, $qual);
        }
    }

    /**
     * Helper: Check Worker Status
     */
    private function checkWorkers($blocking) {
        $flags = $blocking ? 0 : WNOHANG;
        while (true) {
            // While waiting in blocking mode, we MUST update heartbeat or we die
            if ($blocking) {
                $this->updateHeartbeat();
                // To allow heartbeat updates, use non-blocking loop with sleep instead of pure block
                $pid = pcntl_wait($status, WNOHANG);
                if ($pid > 0) {
                    unset($this->pids[$pid]);
                    break; // One slot freed
                }
                usleep(500000); // 0.5s sleep
                continue;
            }

            // Normal non-blocking check
            $pid = pcntl_wait($status, WNOHANG);
            if ($pid > 0) {
                unset($this->pids[$pid]);
            } else {
                break;
            }
        }
    }

    /**
     * Helper: Wait for all workers
     */
    private function waitForWorkers() {
        while (count($this->pids) > 0) {
            $this->updateHeartbeat();
            $pid = pcntl_wait($status, WNOHANG);
            if ($pid > 0) {
                unset($this->pids[$pid]);
            } else {
                usleep(500000);
            }
        }
    }

    /**
     * Phase 2: Clean Cache
     */
    private function cleanCacheDirectory($cacheRoot, $isIconMode) {
        if (!is_dir($cacheRoot)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $suffix = $isIconMode ? '_thumb.jpg' : '.jpg';
        $lenSuffix = strlen($suffix);
        $rootLen = strlen(realpath($cacheRoot)) + 1; // +1 for trailing slash
        
        $cleanCounter = 0;

        foreach ($iterator as $file) {
            if ($this->stopRequested) { $this->log("--- Stopped safely by user ---"); exit; }
            $this->updateHeartbeat(); // Keep lock alive
            $cachedFullPath = $file->getRealPath();
            
            // [NEW] Cleanup stale .tmp files from failed atomic writes
            if (substr($cachedFullPath, -4) === '.tmp') {
                @unlink($cachedFullPath);
                $this->stats['orphans_removed']++;
                continue;
            }
            
            // Extract the "Safe Relative Path" from the cache file
            // E.g. /cache/var/data/img.jpg_thumb.jpg -> var/data/img.jpg
            $relPathWithSuffix = substr($cachedFullPath, $rootLen);
            
            // Basic validation: must end with suffix
            if (substr($relPathWithSuffix, -$lenSuffix) !== $suffix) continue;

            $originalSafePath = substr($relPathWithSuffix, 0, -$lenSuffix);

            $isOrphan = false;

            if (!$this->memoryLimitReached) {
                // FAST MODE: RAM Lookup
                if (!isset($this->validSourcePaths[$originalSafePath])) {
                    $isOrphan = true;
                }
            } else {
                // SLOW MODE: Disk Check (Fallback)
                // We must try to reconstruct the absolute path from the $originalSafePath
                // 1. Try Linux/Unix absolute (/var/...)
                $tryUnix = '/' . $originalSafePath;
                // 2. Try Windows Drive (C/Windows -> C:/Windows)
                $tryWin = preg_match('/^[a-zA-Z]\//', $originalSafePath) 
                          ? substr_replace($originalSafePath, ':/', 1, 1) 
                          : null;

                if (!file_exists($tryUnix) && (!$tryWin || !file_exists($tryWin))) {
                    $isOrphan = true;
                }
            }

            if ($isOrphan) {
                @unlink($cachedFullPath);
                $this->stats['orphans_removed']++;
                
                // Cleanup empty dir
                $dir = dirname($cachedFullPath);
                // Simple empty check: scandir count == 2 (. and ..)
                $c = @scandir($dir);
                if (is_array($c) && count($c) <= 2) {
                    @rmdir($dir);
                }
            }
            // [NEW] Progress Report
            $cleanCounter++;
            if ($this->verbose && $cleanCounter % 10000 === 0) {
                $mem = number_format(memory_get_usage() / 1024 / 1024, 1);
                $typeStr = $isIconMode ? "Icons" : "Previews";
                $this->log(".. checked {$cleanCounter} {$typeStr} [Mem: {$mem}MB] ..");
            }
        }
    }

    /**
     * Helper: Check generation needed
     */
    private function shouldGenerate($targetPath, $sourceMtime) {
        // Use clearstatcache rarely to avoid I/O overhead, but here we check distinct files mostly
        if (!file_exists($targetPath)) return true;
        if (filemtime($targetPath) < $sourceMtime) return true;
        return false;
    }

    /**
     * Helper: Generate Image (Imagick > GD)
     * * Features:
     * - Auto-rotation (fixes sideways phone photos)
     * - Color Space Correction (fixes CMYK/P3 washout)
     * - Preserves aspect ratio (no cropping, no padding)
     * - Atomic writes (prevents corruption)
     */
    private function generateImage($source, $dest, $maxDim, $quality) {
        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        // Skip tiny files (spacers/corrupt) to avoid error spam
        if (@filesize($source) < 32) return false;
        
        // Memory Safeguard: Calculate Megapixels to decide safe routing
        $isMassive = false;
        $imgInfo = @getimagesize($source);
        if ($imgInfo && isset($imgInfo[0], $imgInfo[1])) {
            // Anything over ~30 Megapixels is considered massive
            if (($imgInfo[0] * $imgInfo[1]) > 30000000) {
                $isMassive = true;
            }
        }

        // --- STRATEGY 0: FFMPEG (Video to Image Bridge) ---
        $videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi'];
        $isTempVideoFrame = false;
        if (in_array($ext, $videoExts)) {
            $ffmpegPath = 'ffmpeg';
            if (file_exists('/usr/local/bin/ffmpeg')) $ffmpegPath = '/usr/local/bin/ffmpeg';
            elseif (file_exists('/usr/bin/ffmpeg')) $ffmpegPath = '/usr/bin/ffmpeg';
            $tmpFrame = sys_get_temp_dir() . '/vid_' . uniqid() . '.jpg';
            // Extract a frame at the 2-second mark (-ss 00:00:02). Use fast seeking.
            $cmd = sprintf('%s -y -ss 00:00:01 -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
            exec($cmd);
            if (!file_exists($tmpFrame) || filesize($tmpFrame) === 0) {
                $cmdFallback = sprintf('%s -y -i %s -vframes 1 -q:v 2 %s 2>/dev/null', escapeshellcmd($ffmpegPath), escapeshellarg($source), escapeshellarg($tmpFrame));
                exec($cmdFallback);
            }
            
            if (file_exists($tmpFrame) && filesize($tmpFrame) > 0) {
                $source = $tmpFrame; // Re-route the source to the extracted frame
                $ext = 'jpg';        // Trick Imagick/GD into thinking it's a normal image
                $isTempVideoFrame = true;
            } else {
                return false; // Extraction failed
            }
        }

        // --- STRATEGY 1: IMAGICK (Primary) ---
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();

                // [CRITICAL OPTIMIZATION] For massive JPEGs, tell libjpeg to scale during the disk-read phase.
                // This slashes RAM usage for a 50MP JPEG from ~200MB down to ~5MB.
                if (in_array($ext, ['jpg', 'jpeg'])) {
                    $im->setOption('jpeg:size', ($maxDim * 2) . 'x' . ($maxDim * 2));
                }
                // Optimization: Read only first frame/page for multi-frame images
                $readPath = $source;
                if (in_array($ext, ['tiff', 'tif', 'gif'])) {
                    $readPath .= '[0]';
                }

                $im->readImage($readPath);

                // [FIX 1] Auto-Rotate: Fixes portrait/landscape orientation immediately
                // This bakes the rotation into the pixels so we can safely strip metadata later.
                $im->autoOrient();

                // [FIX 2] Color Space Correction (CMYK/P3 -> sRGB)
                // Must happen BEFORE stripping metadata to avoid neon/washed-out colors
                $colorspace = $im->getImageColorspace();
                if ($colorspace == Imagick::COLORSPACE_CMYK) {
                    $profiles = $im->getImageProfiles("icc", true);
                    if (!empty($profiles)) {
                        $im->profileImage("icc", null); // Strip existing first
                    }
                    $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
                } else {
                    // Ensure standard sRGB for web compatibility
                    if ($colorspace != Imagick::COLORSPACE_SRGB && $colorspace != Imagick::COLORSPACE_GRAY) {
                         $im->transformImageColorspace(Imagick::COLORSPACE_SRGB);
                    }
                }

                // Handle Transparency (PNG/GIF -> JPG white background)
                $im->setImageBackgroundColor('white');
                if ($ext !== 'jpg' && $ext !== 'jpeg') {
                    // Flatten layers to remove transparency
                    $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                }
                $im->setImageFormat('jpg');

                // [FIX 3] Resize while maintaining Aspect Ratio (No Cropping)
                $d = $im->getImageGeometry();
                if ($d['width'] > $maxDim || $d['height'] > $maxDim) {
                    // thumbnailImage($cols, $rows, $bestfit, $fill)
                    // $bestfit = true:  Scale down so it fits INSIDE the box.
                    // $fill    = false: Do NOT pad with background color (no square canvas).
                    $im->thumbnailImage($maxDim, $maxDim, true, false);
                }

                $im->setImageCompression(Imagick::COMPRESSION_JPEG);
                $im->setImageCompressionQuality($quality);

                // Optimization: Strip metadata (EXIF/Profiles) to reduce file size
                // Safe now because we autoOriented and converted ColorSpace
                $im->stripImage();

                // [FIX 4] Atomic Write: Write to temp file first
                $tmpDest = $dest . '.tmp';
                $im->writeImage($tmpDest);

                if (file_exists($tmpDest)) {
                    rename($tmpDest, $dest);
                }

                $im->clear();
                $im->destroy();
                if ($isTempVideoFrame) @unlink($source);
                $im = null;
                return true;

            } catch (Exception $e) {
                // Ignore common non-critical errors (headers, delegates)
                $msg = $e->getMessage();
                if (stripos($msg, 'improper image header') === false && stripos($msg, 'no decode delegate') === false) {
                    $this->log("Imagick Error [" . basename($source) . "]: " . $msg);
                }
            }
        }

        // --- STRATEGY 2: GD (Fallback) ---
        // GD cannot cache to disk. If the image is massive, it WILL cause a fatal memory crash.
        // By bypassing it, we let the file fail silently rather than killing the worker thread.
        if (!$isMassive && extension_loaded('gd') && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        	try {
                // Get dimensions
                list($w, $h) = @getimagesize($source);
                if (!$w) return false;

                // Load Source
                $srcImg = null;
                switch($ext) {
                    case 'jpg': case 'jpeg': $srcImg = @imagecreatefromjpeg($source); break;
                    case 'png': $srcImg = @imagecreatefrompng($source); break;
                    case 'webp': $srcImg = @imagecreatefromwebp($source); break;
                    case 'gif': $srcImg = @imagecreatefromgif($source); break;
                }
                if (!$srcImg) return false;

                // [FIX 1] GD Auto-Rotate Logic (Manual)
                if (function_exists('exif_read_data') && ($ext === 'jpg' || $ext === 'jpeg')) {
                    $exif = @exif_read_data($source);
                    if (!empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3: $srcImg = imagerotate($srcImg, 180, 0); break;
                            case 6: $srcImg = imagerotate($srcImg, -90, 0); break;
                            case 8: $srcImg = imagerotate($srcImg, 90, 0); break;
                        }
                        // Update dims
                        $w = imagesx($srcImg);
                        $h = imagesy($srcImg);
                    }
                }

                // [FIX 3] Calculate Aspect Ratio Dimensions
                $ratio = $w / $h;
                if ($w > $maxDim || $h > $maxDim) {
                    if ($ratio > 1) {
                        // Landscape: Width is max, Height scales
                        $nw = $maxDim;
                        $nh = $maxDim / $ratio;
                    } else {
                        // Portrait: Height is max, Width scales
                        $nh = $maxDim;
                        $nw = $maxDim * $ratio;
                    }
                } else {
                    $nw = $w;
                    $nh = $h;
                }

                // Create Target Canvas (Exact size, no padding)
                $dst = imagecreatetruecolor((int)$nw, (int)$nh);

                // Fill white background (useful if source had transparency)
                $bg = imagecolorallocate($dst, 255, 255, 255);
                imagefilledrectangle($dst, 0, 0, (int)$nw, (int)$nh, $bg);

                // Resample
                imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, (int)$nw, (int)$nh, $w, $h);

                // [FIX 4] Atomic Write
                $tmpDest = $dest . '.tmp';
                imagejpeg($dst, $tmpDest, $quality);

                if (file_exists($tmpDest)) {
                    rename($tmpDest, $dest);
                }

                imagedestroy($srcImg);
                imagedestroy($dst);
                if ($isTempVideoFrame) @unlink($source);
                return true;

            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }
    

    /**
     * Helpers
     */
    private function ensureDir($path) {
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true)) {
                $this->log("Error: Could not create directory $path");
            }
        }
    }

    private function getCloudRoots() {
        $roots = [];
        if (isset($GLOBALS['user_details']) && is_array($GLOBALS['user_details'])) {
            foreach ($GLOBALS['user_details'] as $user) {
                if (isset($user['cloud']) && is_array($user['cloud'])) {
                    foreach ($user['cloud'] as $c) {
                        if (!empty($c['path']) && is_dir($c['path'])) {
                            $roots[] = rtrim($c['path'], '/');
                        }
                    }
                }
            }
        }
        return array_unique($roots);
    }

    /**
     * Retrieves unique cloud roots, limited to the "default" interface
     */
    private function getSearchIndexRoots() {
        $roots = [];
        if (isset($GLOBALS['user_details']) && is_array($GLOBALS['user_details'])) {
            foreach ($GLOBALS['user_details'] as $user) {
                if (isset($user['cloud']) && is_array($user['cloud'])) {
                    foreach ($user['cloud'] as $c) {
                        if (isset($c['interface']) && $c['interface'] === 'default') {
                            if (!empty($c['path']) && is_dir($c['path'])) {
                                $roots[] = rtrim($c['path'], '/');
                            }
                        }
                    }
                }
            }
        }
        return array_unique($roots);
    }

    /**
     * Generates isolated Recoll search indexes for each cloud root
     */
    private function updateSearchIndexes($roots) {
        foreach ($roots as $root) {
            $this->updateHeartbeat();
            $recollDir = $root . DIRECTORY_SEPARATOR . '.recoll';
            
            if (!is_dir($recollDir)) {
                @mkdir($recollDir, 0755, true);
            }
            
            $confFile = $recollDir . DIRECTORY_SEPARATOR . 'recoll.conf';
            $conf = "topdirs = \"" . $root . "\"\n";
            $conf .= "skippedNames = .recycle_bin .recoll .git .svn\n";
            $conf .= "loglevel = 2\n";
            $conf .= "noContentSuffixes = .arw .avi .bmp .cr2 .db .dll .dng .exe .flv .gif .ico .jbf .jpeg .jpg .kmz .m4v .mid .modd .moff .mov .mp3 .mp4 .mpeg .mpg .pid .png .ppp .psd .pspimage .pto .qxd .qxp .spd .svg .sys .tif .ufo .vsd .wav .webp .wmf .wmv\n";
            
            // Apply changes automatically if the config is missing or outdated
            if (!file_exists($confFile) || file_get_contents($confFile) !== $conf) {
                file_put_contents($confFile, $conf);
                if ($this->verbose) $this->log("Updated search configuration for: " . $root);
            }
            
        $dbDir = $recollDir . DIRECTORY_SEPARATOR . 'xapiandb';
        if (!is_dir($dbDir)) {
            $this->log("Creating NEW index for: " . $root);
        } else {
            $this->log("Updating EXISTING index for: " . $root);
        }
            
            // ionice -c 3 (Idle IO priority) and nice -n 19 (Lowest CPU priority)
            $cmd = "ionice -c 3 nice -n 19 recollindex -c " . escapeshellarg($recollDir) . " 2>&1";
            shell_exec($cmd);
        }
    }

    private function log($msg) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
    }
}

/** * ==========================================
 * RUNNER LOGIC
 * ==========================================
 */

$work_dir = dirname(__DIR__);
require_once $work_dir . '/configuration/config.dist.php';             
require_once $work_dir . '/configuration/config.php';             
require_once $user_db;

// Parse CLI Arguments
$args = getopt("", ["cache-refresh", "delete-recyclers", "search-index", "process-outbox", "verbose"]);

// Default: Do ALL if no arguments provided
$doCache = false;
$doRecycle = false;
$doSearchIndex = false;
$doOutbox = true;
$isVerbose = isset($args['verbose']); // Check for flag

if (isset($args['cache-refresh']) || isset($args['delete-recyclers']) || isset($args['search-index']) || isset($args['process-outbox'])) {
    $doCache = isset($args['cache-refresh']);
    $doRecycle = isset($args['delete-recyclers']);
    $doSearchIndex = isset($args['search-index']);
    $doOutbox = isset($args['process-outbox']);
}

$housekeeper = new myCloudHousekeeper($work_dir);
$housekeeper->run($doCache, $doRecycle, $doSearchIndex, $doOutbox, $isVerbose);