<?php
session_start();

// Login handling
if (!isset($_SESSION['loggedin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
        if ($_POST['username'] === 'mehedi' && $_POST['password'] === 'password') {
            $_SESSION['loggedin'] = true;
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid credentials!";
        }
    }

    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Advanced Downloader</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
            }
            .login-card {
                backdrop-filter: blur(10px);
                background: rgba(255, 255, 255, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-lg login-card">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <i class="bi bi-download text-primary" style="font-size: 3rem;"></i>
                                <h2 class="card-title mt-2">Advanced Downloader</h2>
                                <p class="text-muted">Please sign in to continue</p>
                            </div>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Get file info endpoint
if (isset($_GET['action']) && $_GET['action'] === 'getFileInfo' && isset($_GET['url'])) {
    header('Content-Type: application/json');
    
    $url = $_GET['url'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    $filename = basename(parse_url($url, PHP_URL_PATH));
    if (empty($filename) || strpos($filename, '.') === false) {
        $filename = 'download_' . time();
    }
    
    // Try to get filename from Content-Disposition header
    if (preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $headers, $matches)) {
        $filename = trim($matches[1], '"\'');
    }
    
    echo json_encode([
        'success' => $httpCode === 200,
        'filename' => $filename,
        'size' => $contentLength > 0 ? $contentLength : null,
        'type' => $contentType,
        'httpCode' => $httpCode
    ]);
    exit;
}

// SSE download handler
if (isset($_GET['url'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    function sendProgress($percentage, $speed, $filename = '', $filesize = '', $downloaded = '', $status = 'downloading') {
        echo "data: " . json_encode([
            'percentage' => $percentage, 
            'speed' => $speed,
            'filename' => $filename,
            'filesize' => $filesize,
            'downloaded' => $downloaded,
            'status' => $status,
            'timestamp' => time()
        ]) . "\n\n";
        ob_flush();
        flush();
    }

    function getFilenameFromUrl($url) {
        $parsedUrl = parse_url($url);
        $filename = basename($parsedUrl['path']);
        if (empty($filename) || strpos($filename, '.') === false) {
            return 'download_' . time();
        }
        return $filename;
    }

    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    function downloadFile($url, $path) {
        global $filename, $totalSize;
        
        $fp = fopen($path, 'w+');
        if (!$fp) {
            sendProgress(0, 'Error', $filename, '', '', 'error');
            return false;
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        
        if ($result === false || $httpCode !== 200) {
            unlink($path);
            sendProgress(0, 'Download failed', $filename, '', '', 'error');
            return false;
        }
        
        return true;
    }

    // Global variables to track download progress and speed
    $lastDownloadedSize = 0;
    $lastTime = 0;
    $filename = '';
    $totalSize = 0;
    $startTime = microtime(true);

    function progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        global $lastDownloadedSize, $lastTime, $filename, $totalSize, $startTime;
        
        if ($download_size > 0) {
            $percentage = round($downloaded / $download_size * 100, 2);
            $totalSize = $download_size;
            
            // Calculate download speed
            $currentTime = microtime(true);
            if ($lastTime === 0) {
                $lastTime = $currentTime;
                $lastDownloadedSize = $downloaded;
                $speed = "Calculating...";
            } else {
                $timeDiff = $currentTime - $lastTime;
                
                // Update speed calculation every 0.5 seconds
                if ($timeDiff >= 0.5) {
                    $bytesDownloaded = $downloaded - $lastDownloadedSize;
                    $speedBps = $bytesDownloaded / $timeDiff;
                    
                    // Convert to appropriate unit (B/s, KB/s, MB/s)
                    if ($speedBps < 1024) {
                        $speed = round($speedBps, 2) . " B/s";
                    } elseif ($speedBps < 1048576) {
                        $speed = round($speedBps / 1024, 2) . " KB/s";
                    } else {
                        $speed = round($speedBps / 1048576, 2) . " MB/s";
                    }
                    
                    // Calculate ETA
                    if ($speedBps > 0 && $percentage > 0) {
                        $remainingBytes = $download_size - $downloaded;
                        $eta = $remainingBytes / $speedBps;
                        if ($eta < 60) {
                            $etaStr = round($eta) . "s";
                        } elseif ($eta < 3600) {
                            $etaStr = round($eta / 60) . "m " . round($eta % 60) . "s";
                        } else {
                            $etaStr = round($eta / 3600) . "h " . round(($eta % 3600) / 60) . "m";
                        }
                        $speed .= " (ETA: $etaStr)";
                    }
                    
                    // Update values for next calculation
                    $lastTime = $currentTime;
                    $lastDownloadedSize = $downloaded;
                } else {
                    // Use previous speed calculation
                    $speed = null; // This will prevent sending redundant updates
                }
            }
            
            // Only send updates when we have a speed to report or for percentage updates
            if ($speed !== null) {
                sendProgress(
                    $percentage, 
                    $speed, 
                    $filename, 
                    formatFileSize($totalSize),
                    formatFileSize($downloaded)
                );
            }
        }
    }

    $url = $_GET['url'];
    $filename = getFilenameFromUrl($url);
    $destination = __DIR__ . '/downloads/' . $filename;

    // Create downloads directory if it doesn't exist
    if (!is_dir(__DIR__ . '/downloads')) {
        mkdir(__DIR__ . '/downloads', 0755, true);
    }

    // Check if file already exists and add number suffix if needed
    $counter = 1;
    $originalFilename = $filename;
    while (file_exists($destination)) {
        $pathInfo = pathinfo($originalFilename);
        $filename = $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
        $destination = __DIR__ . '/downloads/' . $filename;
        $counter++;
    }

    sendProgress(0, 'Starting download...', $filename, '', '', 'starting');
    
    if (downloadFile($url, $destination)) {
        sendProgress(100, 'Complete', $filename, formatFileSize(filesize($destination)), formatFileSize(filesize($destination)), 'completed');
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced File Downloader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .download-card {
            max-width: 700px;
            width: 100%;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .file-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .progress-container {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        .url-input {
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .url-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .download-history {
            max-height: 300px;
            overflow-y: auto;
        }
        .queue-item {
            transition: all 0.2s ease;
        }
        .queue-item:hover {
            background-color: #f8f9fa;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="card shadow-lg download-card mx-auto">
                        <div class="card-header bg-primary text-white text-center py-3">
                            <h3 class="mb-0"><i class="bi bi-cloud-download"></i> Advanced File Downloader</h3>
                            <small>Fast, reliable, and feature-rich downloading</small>
                        </div>
                        <div class="card-body p-4">
                            <!-- URL Input Section -->
                            <form id="downloadForm">
                                <div class="mb-3">
                                    <label for="downloadLink" class="form-label fw-bold">Download URL</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                        <input type="url" class="form-control url-input" id="downloadLink" 
                                               placeholder="Paste your download link here..." required>
                                        <button type="button" class="btn btn-outline-secondary" id="validateBtn" title="Validate URL">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Supports HTTP, HTTPS, and FTP protocols</div>
                                </div>
                                
                                <!-- URL Validation Results -->
                                <div id="urlValidation" class="mb-3" style="display:none;"></div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <button type="submit" class="btn btn-success w-100 py-2" id="downloadBtn">
                                            <i class="bi bi-download"></i> Start Download
                                        </button>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="button" class="btn btn-outline-primary w-100 py-2" id="addToQueueBtn" disabled>
                                            <i class="bi bi-plus-circle"></i> Add to Queue
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Download Queue -->
                            <div id="downloadQueue" class="mt-4" style="display:none;">
                                <h6><i class="bi bi-list-ul"></i> Download Queue</h6>
                                <div id="queueList" class="border rounded p-2 download-history"></div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-success" id="processQueueBtn">
                                        <i class="bi bi-play-circle"></i> Process Queue
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" id="clearQueueBtn">
                                        <i class="bi bi-trash"></i> Clear Queue
                                    </button>
                                </div>
                            </div>

                            <!-- File Information Display -->
                            <div id="fileInfo" class="file-info mt-4" style="display:none;">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1"><i class="bi bi-file-earmark"></i> <span id="fileName"></span></h6>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <small><strong>Size:</strong> <span id="fileSize"></span></small>
                                            </div>
                                            <div class="col-sm-6">
                                                <small><strong>Downloaded:</strong> <span id="downloadedSize">0 B</span></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button class="btn btn-sm btn-outline-light" id="cancelBtn">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div id="progressContainer" class="progress-container mt-3" style="display:none;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Progress</small>
                                    <small class="text-muted" id="progressPercent">0%</small>
                                </div>
                                <div class="progress mb-2" style="height: 25px;">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                         role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        0%
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted" id="speedInfo">Speed: Calculating...</small>
                                    <small class="text-muted" id="statusInfo">Preparing...</small>
                                </div>
                            </div>

                            <!-- Success/Error Messages -->
                            <div id="message" class="alert mt-3" style="display:none;"></div>

                            <!-- Download Statistics -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card stats-card text-center">
                                        <div class="card-body py-3">
                                            <h5 class="mb-1" id="totalDownloads">0</h5>
                                            <small>Total Downloads</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card stats-card text-center">
                                        <div class="card-body py-3">
                                            <h5 class="mb-1" id="totalSize">0 B</h5>
                                            <small>Total Downloaded</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="row">
                                <div class="col-6">
                                    <a href="downloads.php" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-folder"></i> Manage Files
                                    </a>
                                </div>
                                <div class="col-6">
                                    <a href="logout.php" class="btn btn-outline-danger w-100">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let downloadQueue = [];
        let isDownloading = false;
        let currentEventSource = null;
        let downloadStats = JSON.parse(localStorage.getItem('downloadStats')) || { totalDownloads: 0, totalSize: 0 };

        // Update stats display
        function updateStatsDisplay() {
            document.getElementById('totalDownloads').textContent = downloadStats.totalDownloads;
            document.getElementById('totalSize').textContent = formatFileSize(downloadStats.totalSize);
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' B';
            }
        }

        // Validate URL
        async function validateUrl(url) {
            try {
                const response = await fetch(`index.php?action=getFileInfo&url=${encodeURIComponent(url)}`);
                const data = await response.json();
                return data;
            } catch (error) {
                return { success: false, error: error.message };
            }
        }

        // Show validation results
        function showValidationResult(data) {
            const validationDiv = document.getElementById('urlValidation');
            if (data.success) {
                validationDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <strong>Valid URL</strong><br>
                        <small>File: ${data.filename} ${data.size ? `(${formatFileSize(data.size)})` : ''}</small>
                    </div>
                `;
                document.getElementById('addToQueueBtn').disabled = false;
            } else {
                validationDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>URL Validation Failed</strong><br>
                        <small>HTTP Code: ${data.httpCode || 'Unknown'}</small>
                    </div>
                `;
                document.getElementById('addToQueueBtn').disabled = true;
            }
            validationDiv.style.display = 'block';
        }

        // Add to download queue
        function addToQueue(url) {
            if (!downloadQueue.find(item => item.url === url)) {
                downloadQueue.push({ url, id: Date.now(), status: 'pending' });
                updateQueueDisplay();
                showMessage('URL added to download queue', 'success');
            } else {
                showMessage('URL already in queue', 'warning');
            }
        }

        // Update queue display
        function updateQueueDisplay() {
            const queueDiv = document.getElementById('downloadQueue');
            const queueList = document.getElementById('queueList');
            
            if (downloadQueue.length > 0) {
                queueDiv.style.display = 'block';
                queueList.innerHTML = downloadQueue.map(item => `
                    <div class="queue-item d-flex justify-content-between align-items-center p-2 border-bottom">
                        <div class="flex-grow-1">
                            <small class="text-break">${item.url}</small>
                            <br><span class="badge bg-${item.status === 'pending' ? 'secondary' : item.status === 'downloading' ? 'primary' : 'success'}">${item.status}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromQueue(${item.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `).join('');
            } else {
                queueDiv.style.display = 'none';
            }
        }

        // Remove from queue
        function removeFromQueue(id) {
            downloadQueue = downloadQueue.filter(item => item.id !== id);
            updateQueueDisplay();
        }

        // Clear queue
        function clearQueue() {
            downloadQueue = [];
            updateQueueDisplay();
        }

        // Process download queue
        async function processQueue() {
            if (downloadQueue.length === 0 || isDownloading) return;
            
            for (let item of downloadQueue.filter(i => i.status === 'pending')) {
                item.status = 'downloading';
                updateQueueDisplay();
                await startDownload(item.url);
                item.status = 'completed';
                updateQueueDisplay();
            }
        }

        // Show message
        function showMessage(text, type = 'info') {
            const messageDiv = document.getElementById('message');
            messageDiv.className = `alert alert-${type} mt-3`;
            messageDiv.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i> ${text}`;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 5000);
        }

        // Start download
        function startDownload(url) {
            return new Promise((resolve) => {
                if (isDownloading) {
                    showMessage('Another download is in progress', 'warning');
                    return resolve();
                }

                isDownloading = true;
                const progressBar = document.getElementById('progressBar');
                const progressContainer = document.getElementById('progressContainer');
                const speedInfo = document.getElementById('speedInfo');
                const statusInfo = document.getElementById('statusInfo');
                const message = document.getElementById('message');
                const fileInfo = document.getElementById('fileInfo');
                const fileName = document.getElementById('fileName');
                const fileSize = document.getElementById('fileSize');
                const downloadedSize = document.getElementById('downloadedSize');
                const progressPercent = document.getElementById('progressPercent');

                document.title = "Downloading...";
                progressContainer.style.display = 'block';
                fileInfo.style.display = 'none';
                progressBar.style.width = '0%';
                progressBar.innerHTML = '0%';
                progressBar.setAttribute('aria-valuenow', '0');
                speedInfo.textContent = 'Speed: Calculating...';
                statusInfo.textContent = 'Preparing...';
                message.style.display = 'none';

                currentEventSource = new EventSource("index.php?url=" + encodeURIComponent(url));

                currentEventSource.onmessage = function (event) {
                    const data = JSON.parse(event.data);
                    const percent = data.percentage;
                    const speed = data.speed;
                    const filename = data.filename;
                    const filesize = data.filesize;
                    const downloaded = data.downloaded;
                    const status = data.status;

                    // Update file info if available
                    if (filename && filesize && fileInfo.style.display === 'none') {
                        fileName.textContent = filename;
                        fileSize.textContent = filesize;
                        fileInfo.style.display = 'block';
                    }

                    if (downloaded) {
                        downloadedSize.textContent = downloaded;
                    }

                    progressBar.style.width = percent + '%';
                    progressBar.innerHTML = percent + '%';
                    progressBar.setAttribute('aria-valuenow', percent);
                    progressPercent.textContent = percent + '%';
                    
                    if (speed) {
                        speedInfo.textContent = 'Speed: ' + speed;
                    }
                    
                    statusInfo.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    document.title = `Downloading (${percent}%)`;

                    if (percent >= 100 || status === 'completed') {
                        document.title = "Download Complete";
                        progressBar.classList.remove('progress-bar-animated');
                        showMessage('File downloaded successfully!', 'success');
                        
                        // Update stats
                        downloadStats.totalDownloads++;
                        if (filesize) {
                            const sizeInBytes = parseFloat(filesize.replace(/[^\d.]/g, '')) * 
                                (filesize.includes('GB') ? 1073741824 : 
                                 filesize.includes('MB') ? 1048576 : 
                                 filesize.includes('KB') ? 1024 : 1);
                            downloadStats.totalSize += sizeInBytes;
                        }
                        localStorage.setItem('downloadStats', JSON.stringify(downloadStats));
                        updateStatsDisplay();
                        
                        setTimeout(() => {
                            resetDownloadUI();
                        }, 3000);
                        
                        currentEventSource.close();
                        isDownloading = false;
                        resolve();
                    } else if (status === 'error') {
                        showMessage('Download failed!', 'danger');
                        currentEventSource.close();
                        isDownloading = false;
                        resolve();
                    }
                };

                currentEventSource.onerror = function () {
                    currentEventSource.close();
                    document.title = "Error!";
                    showMessage('Download failed due to connection error!', 'danger');
                    isDownloading = false;
                    resolve();
                };
            });
        }

        // Reset download UI
        function resetDownloadUI() {
            document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('progressBar').innerHTML = '0%';
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('progressBar').setAttribute('aria-valuenow', '0');
            document.getElementById('progressBar').classList.add('progress-bar-animated');
            document.getElementById('message').style.display = 'none';
            document.title = "Advanced File Downloader";
        }

        // Cancel download
        function cancelDownload() {
            if (currentEventSource) {
                currentEventSource.close();
                isDownloading = false;
                showMessage('Download cancelled', 'warning');
                resetDownloadUI();
            }
        }

        // Event listeners
        document.getElementById('downloadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const url = document.getElementById('downloadLink').value;
            startDownload(url);
            document.getElementById('downloadLink').value = '';
            document.getElementById('urlValidation').style.display = 'none';
            document.getElementById('addToQueueBtn').disabled = true;
        });

        document.getElementById('validateBtn').addEventListener('click', async function() {
            const url = document.getElementById('downloadLink').value;
            if (!url) return;
            
            this.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            this.disabled = true;
            
            const result = await validateUrl(url);
            showValidationResult(result);
            
            this.innerHTML = '<i class="bi bi-check-circle"></i>';
            this.disabled = false;
        });

        document.getElementById('addToQueueBtn').addEventListener('click', function() {
            const url = document.getElementById('downloadLink').value;
            if (url) {
                addToQueue(url);
                document.getElementById('downloadLink').value = '';
                document.getElementById('urlValidation').style.display = 'none';
                this.disabled = true;
            }
        });

        document.getElementById('processQueueBtn').addEventListener('click', processQueue);
        document.getElementById('clearQueueBtn').addEventListener('click', clearQueue);
        document.getElementById('cancelBtn').addEventListener('click', cancelDownload);

        // Initialize
        updateStatsDisplay();
    </script>
</body>
</html>