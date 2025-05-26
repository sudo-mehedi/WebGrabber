<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header("Location: index.php");
    exit;
}

// Handle file delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fileToDelete = basename($_POST['delete_file']);
    $filePath = __DIR__ . '/downloads/' . $fileToDelete;
    if (file_exists($filePath)) {
        unlink($filePath);
        $message = "File '$fileToDelete' deleted successfully.";
        $messageType = "success";
    } else {
        $message = "File not found.";
        $messageType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Downloaded Files</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .file-item {
            transition: all 0.2s ease;
        }
        .file-item:hover {
            background-color: #f8f9fa !important;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><i class="bi bi-folder"></i> Downloaded Files</h4>
                            <div>
                                <a href="index.php" class="btn btn-light btn-sm me-2">
                                    <i class="bi bi-arrow-left"></i> Back to Downloader
                                </a>
                                <a href="logout.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Files List -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <?php
                        $downloadDir = __DIR__ . '/downloads';
                        if (is_dir($downloadDir)) {
                            $files = array_diff(scandir($downloadDir), ['.', '..']);
                            if (count($files) > 0) {
                                echo '<div class="list-group list-group-flush">';
                                foreach ($files as $file) {
                                    $safeFile = htmlspecialchars($file);
                                    $urlFile = urlencode($file);
                                    $filePath = "$downloadDir/$file";
                                    $fileSize = filesize($filePath);
                                    $formattedSize = $fileSize >= 1048576 ? round($fileSize / 1048576, 2) . ' MB' :
                                                    ($fileSize >= 1024 ? round($fileSize / 1024, 2) . ' KB' : $fileSize . ' B');

                                    // Get file extension for icon
                                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                    $iconClass = 'bi-file-earmark';
                                    switch ($extension) {
                                        case 'pdf':
                                            $iconClass = 'bi-file-earmark-pdf';
                                            break;
                                        case 'zip':
                                        case 'rar':
                                        case '7z':
                                            $iconClass = 'bi-file-earmark-zip';
                                            break;
                                        case 'jpg':
                                        case 'jpeg':
                                        case 'png':
                                        case 'gif':
                                        case 'bmp':
                                            $iconClass = 'bi-file-earmark-image';
                                            break;
                                        case 'mp4':
                                        case 'avi':
                                        case 'mkv':
                                        case 'mov':
                                            $iconClass = 'bi-file-earmark-play';
                                            break;
                                        case 'mp3':
                                        case 'wav':
                                        case 'flac':
                                            $iconClass = 'bi-file-earmark-music';
                                            break;
                                        case 'txt':
                                        case 'doc':
                                        case 'docx':
                                            $iconClass = 'bi-file-earmark-text';
                                            break;
                                        case 'exe':
                                        case 'msi':
                                            $iconClass = 'bi-file-earmark-binary';
                                            break;
                                    }

                                    echo '<div class="list-group-item file-item d-flex justify-content-between align-items-center py-3">
                                            <div class="d-flex align-items-center flex-grow-1">
                                                <i class="' . $iconClass . ' text-primary me-3 fs-4"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 text-break">' . $safeFile . '</h6>
                                                    <small class="text-muted">Size: ' . $formattedSize . '</small>
                                                </div>
                                            </div>
                                            <div class="btn-group" role="group">
                                                <a href="downloads/' . $urlFile . '" class="btn btn-outline-primary btn-sm" target="_blank" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmDelete(\'' . addslashes($safeFile) . '\')" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                          </div>';
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="text-center py-5">
                                        <i class="bi bi-folder2-open text-muted" style="font-size: 4rem;"></i>
                                        <h5 class="text-muted mt-3">No files downloaded yet</h5>
                                        <p class="text-muted">Start downloading files to see them here.</p>
                                        <a href="index.php" class="btn btn-primary">
                                            <i class="bi bi-download"></i> Start Downloading
                                        </a>
                                      </div>';
                            }
                        } else {
                            echo '<div class="text-center py-5">
                                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                                    <h5 class="text-warning mt-3">Download directory not found</h5>
                                    <p class="text-muted">The downloads folder has not been created yet.</p>
                                  </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle text-warning"></i> Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this file?</p>
                    <p class="text-muted mb-0"><strong id="fileToDelete"></strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display:inline;" id="deleteForm">
                        <input type="hidden" name="delete_file" id="deleteFileInput">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(filename) {
            document.getElementById('fileToDelete').textContent = filename;
            document.getElementById('deleteFileInput').value = filename;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
