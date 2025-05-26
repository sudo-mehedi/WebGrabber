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
        <title>Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background-color: #f8f9fa;
                height: 100vh;
            }
        </style>
    </head>
    <body class="d-flex align-items-center justify-content-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <h2 class="card-title text-center mb-4">Login</h2>
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger text-center" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                                </div>
                                <div class="mb-3">
                                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Login</button>
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

// SSE download handler
if (isset($_GET['url'])) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    function sendProgress($percentage, $speed, $filename = '', $filesize = '') {
        echo "data: " . json_encode([
            'percentage' => $percentage, 
            'speed' => $speed,
            'filename' => $filename,
            'filesize' => $filesize
        ]) . "\n\n";
        ob_flush();
        flush();
    }

    function getFilenameFromUrl($url) {
        $parsedUrl = parse_url($url);
        return basename($parsedUrl['path']);
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
        $fp = fopen($path, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    // Global variables to track download progress and speed
    $lastDownloadedSize = 0;
    $lastTime = 0;
    $filename = '';
    $totalSize = 0;

    function progress($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        global $lastDownloadedSize, $lastTime, $filename, $totalSize;
        
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
                sendProgress($percentage, $speed, $filename, formatFileSize($totalSize));
            }
        }
    }

    $url = $_GET['url'];
    $filename = getFilenameFromUrl($url);
    $destination = __DIR__ . '/downloads/' . $filename;

    if (!is_dir(__DIR__ . '/downloads')) {
        mkdir(__DIR__ . '/downloads', 0755, true);
    }

    downloadFile($url, $destination);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloader</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .main-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .download-card {
            max-width: 600px;
            width: 100%;
        }
        .file-info {
            background-color: #e9ecef;
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="card shadow download-card mx-auto">
                        <div class="card-header bg-primary text-white text-center">
                            <h4 class="mb-0"><i class="bi bi-download"></i> File Downloader</h4>
                        </div>
                        <div class="card-body p-4">
                            <form id="downloadForm">
                                <div class="mb-3">
                                    <label for="downloadLink" class="form-label">Download URL</label>
                                    <input type="url" class="form-control" id="downloadLink" placeholder="Insert download link here" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-download"></i> Start Download
                                </button>
                            </form>

                            <!-- File Information Display -->
                            <div id="fileInfo" class="file-info mt-3" style="display:none;">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <strong>File Name:</strong>
                                        <div id="fileName" class="text-break"></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <strong>File Size:</strong>
                                        <div id="fileSize"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div id="progressContainer" class="mt-3" style="display:none;">
                                <div class="progress" style="height: 30px;">
                                    <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                         role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                        0%
                                    </div>
                                </div>
                            </div>

                            <!-- Speed Information -->
                            <div id="speedInfo" class="text-center mt-2" style="display:none;">
                                <small class="text-muted">Speed: Calculating...</small>
                            </div>

                            <!-- Success Message -->
                            <div id="message" class="alert alert-success mt-3" style="display:none;">
                                <i class="bi bi-check-circle"></i> File downloaded successfully!
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <div class="col-6">
                                    <a href="downloads.php" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-folder"></i> View Files
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
        document.getElementById('downloadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const url = document.getElementById('downloadLink').value;
            const progressBar = document.getElementById('progressBar');
            const progressContainer = document.getElementById('progressContainer');
            const speedInfo = document.getElementById('speedInfo');
            const message = document.getElementById('message');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');

            document.title = "Downloading...";
            progressContainer.style.display = 'block';
            speedInfo.style.display = 'block';
            fileInfo.style.display = 'none';
            progressBar.style.width = '0%';
            progressBar.innerHTML = '0%';
            progressBar.setAttribute('aria-valuenow', '0');
            speedInfo.innerHTML = '<small class="text-muted">Speed: Calculating...</small>';
            message.style.display = 'none';

            const source = new EventSource("index.php?url=" + encodeURIComponent(url));

            source.onmessage = function (event) {
                const data = JSON.parse(event.data);
                const percent = data.percentage;
                const speed = data.speed;
                const filename = data.filename;
                const filesize = data.filesize;

                // Update file info if available
                if (filename && filesize && fileInfo.style.display === 'none') {
                    fileName.textContent = filename;
                    fileSize.textContent = filesize;
                    fileInfo.style.display = 'block';
                }

                progressBar.style.width = percent + '%';
                progressBar.innerHTML = percent + '%';
                progressBar.setAttribute('aria-valuenow', percent);
                
                if (speed) {
                    speedInfo.innerHTML = '<small class="text-muted">Speed: ' + speed + '</small>';
                }
                document.title = `Downloading (${percent}%)`;

                if (percent >= 100) {
                    document.title = "Download Complete";
                    progressBar.classList.remove('progress-bar-animated');
                    message.style.display = 'block';
                    setTimeout(() => {
                        message.style.display = 'none';
                        progressContainer.style.display = 'none';
                        speedInfo.style.display = 'none';
                        fileInfo.style.display = 'none';
                        progressBar.innerHTML = '0%';
                        progressBar.style.width = '0%';
                        progressBar.setAttribute('aria-valuenow', '0');
                        progressBar.classList.add('progress-bar-animated');
                        document.title = "Downloader";
                    }, 5000);
                    source.close();
                }
            };

            source.onerror = function () {
                source.close();
                document.title = "Error!";
                message.className = 'alert alert-danger mt-3';
                message.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Download failed!';
                message.style.display = 'block';
            };

            document.getElementById('downloadLink').value = '';
        });
    </script>
</body>
</html>