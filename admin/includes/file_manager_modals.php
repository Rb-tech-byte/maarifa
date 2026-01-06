<?php
// Add the missing function
function getFilePreviewModalHtml($file_data, $is_local = false) {
    $file_path = $is_local ? ($file_data['full'] ?? '') : ($file_data['value'] ?? '');
    $file_name = $is_local ? ($file_data['basename'] ?? '') : basename($file_data['value'] ?? '');
    $file_size = $file_data['size'] ?? 0;
    $file_created = $file_data['created_at'] ?? date('Y-m-d H:i:s');
    $file_type = $is_local ? null : ($file_data['file_type'] ?? null);

    if ($file_path && !$file_type && file_exists($file_path)) {
        $mime = mime_content_type($file_path);
        if (str_starts_with($mime, 'image/')) $file_type = 'image';
        elseif (str_starts_with($mime, 'video/')) $file_type = 'video';
        elseif (str_starts_with($mime, 'audio/')) $file_type = 'audio';
        elseif (str_contains($mime, 'pdf')) $file_type = 'pdf';
        else $file_type = 'other';
    }

    $escaped_path = htmlspecialchars($file_path);
    $preview_html = '';

    if ($file_type === 'image') {
        $preview_html = '<img src="' . $escaped_path . '" alt="File Preview" class="img-fluid rounded" style="max-height: 70vh;">';
    } elseif ($file_type === 'video') {
        $preview_html = '<video src="' . $escaped_path . '" class="img-fluid rounded" style="max-height: 70vh;" controls></video>';
    } elseif ($file_type === 'audio' || $file_type === 'wav') {
        $preview_html = '<audio src="' . $escaped_path . '" controls class="w-100"></audio>';
    } elseif ($file_type === 'pdf') {
        $preview_html = '<div class="text-center"><i class="fas fa-file-pdf text-danger" style="font-size: 8rem;"></i><p class="mt-3">PDF Document</p><a href="' . $escaped_path . '" class="btn btn-primary" target="_blank">Open PDF</a></div>';
    } else {
        $preview_html = '<div class="text-center"><i class="fas fa-file text-secondary" style="font-size: 8rem;"></i><p class="mt-3">No preview available</p></div>';
    }

    return '
    <div class="row">
        <div class="col-md-8 d-flex justify-content-center align-items-center">' . $preview_html . '</div>
        <div class="col-md-4">
            <h5>File Details</h5>
            <p class="mb-2"><strong>Name:</strong><br><span class="text-break">' . htmlspecialchars($file_name) . '</span></p>
            <p class="mb-2"><strong>Size:</strong><br>' . formatFileSize($file_size) . '</p>
            <p class="mb-3"><strong>Uploaded:</strong><br>' . date('d M Y, H:i', strtotime($file_created)) . '</p>
            <a href="' . $escaped_path . '" class="btn btn-success w-100" download>Download File</a>
        </div>
    </div>';
}
?>

<!-- Preview Modals for DB Files -->
<?php foreach ($files as $file): ?>
    <div class="modal fade" id="previewModal-<?= $file['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars(basename($file['value'])) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= getFilePreviewModalHtml($file, false) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal fade" id="shareModal-<?= $file['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Generate a shareable link for this file with customizable settings.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Expiry Time</label>
                        <select class="form-select" id="expirySelect-<?= $file['id'] ?>">
                            <option value="+1 hour">1 Hour</option>
                            <option value="+1 day">1 Day</option>
                            <option value="+7 days" selected>7 Days</option>
                            <option value="+30 days">30 Days</option>
                            <option value="+90 days">90 Days</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Max Downloads</label>
                        <input type="number" class="form-control" id="maxDownloads-<?= $file['id'] ?>" value="10" min="1" max="100">
                    </div>
                    
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLinkInput-<?= $file['id'] ?>" 
                               value="<?= htmlspecialchars(getLatestShareLink($file['id'])['link'] ?? '') ?>" 
                               placeholder="Generate a link..." readonly>
                        <button class="btn btn-outline-secondary copy-btn" type="button" data-target="#shareLinkInput-<?= $file['id'] ?>">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    
                    <div id="share-feedback-<?= $file['id'] ?>"></div>
                    
                    <?php 
                    $shareInfo = getLatestShareLink($file['id']);
                    if ($shareInfo): ?>
                        <div class="alert alert-info">
                            <small>
                                <strong>Current Link Status:</strong><br>
                                Expires: <?= date('M j, Y g:i A', strtotime($shareInfo['expires_at'])) ?><br>
                                Downloads: <?= $shareInfo['download_count'] ?>/<?= $shareInfo['max_downloads'] ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary generate-share-link-btn" data-media-id="<?= $file['id'] ?>">
                        <i class="fas fa-share-alt me-2"></i>Generate New Link
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal fade" id="renameModal-<?= $file['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">New File Name</label>
                        <input type="text" name="new_name" class="form-control" 
                               value="<?= htmlspecialchars(pathinfo($file['value'], PATHINFO_FILENAME)) ?>" required>
                        <div class="form-text">Enter the new name without the file extension</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rename-btn" data-id="<?= $file['id'] ?>" data-type="db">
                        <i class="fas fa-save me-2"></i>Rename
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Preview Modals for Local Files -->
<?php foreach ($local_files as $lf): ?>
    <div class="modal fade" id="previewModalLocal-<?= md5($lf['basename']) ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= htmlspecialchars($lf['basename']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= getFilePreviewModalHtml($lf, true) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rename Modal for Local Files -->
    <div class="modal fade" id="renameModalLocal-<?= md5($lf['basename']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rename Untracked File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">New File Name</label>
                        <input type="text" name="new_name" class="form-control" 
                               value="<?= htmlspecialchars(pathinfo($lf['basename'], PATHINFO_FILENAME)) ?>" required>
                        <div class="form-text">Enter the new name without the file extension</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rename-btn" data-id="<?= htmlspecialchars($lf['basename']) ?>" data-type="local" data-modal-id="<?= md5($lf['basename']) ?>">
                        <i class="fas fa-save me-2"></i>Rename
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createFolderForm">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Folder Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Description (Optional)</label>
                        <textarea name="description" class="form-control rte" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Parent Folder (Optional)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">No Parent</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createFolderBtn">
                    <i class="fas fa-folder-plus me-2"></i>Create Folder
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload Files Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Files</label>
                        <input type="file" name="files[]" class="form-control" multiple required>
                        <div class="form-text">You can select multiple files at once</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Folder (Optional)</label>
                        <select name="folder_id" class="form-select">
                            <option value="">No Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="track_in_db" class="form-check-input" id="trackInDb" checked>
                            <label class="form-check-label" for="trackInDb">
                                Track files in database
                            </label>
                        </div>
                    </div>
                </form>
                <div id="uploadProgress" class="d-none">
                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="uploadStatus" class="text-muted"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="uploadBtn">
                    <i class="fas fa-upload me-2"></i>Upload Files
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Move to Folder Modal -->
<div class="modal fade" id="moveToFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Move Files to Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Move <span id="moveFileCount">0</span> selected files to:</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Folder</label>
                    <select name="folder_id" class="form-select" id="moveFolderSelect">
                        <option value="">No Folder (Root)</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= $folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="moveToFolderBtn">
                    <i class="fas fa-folder-open me-2"></i>Move Files
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Share Links Modal -->
<div class="modal fade" id="bulkShareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Share Links</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Generate share links for <span id="shareFileCount">0</span> selected files.</p>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Expiry Time</label>
                    <select class="form-select" id="bulkExpirySelect">
                        <option value="+1 hour">1 Hour</option>
                        <option value="+1 day">1 Day</option>
                        <option value="+7 days" selected>7 Days</option>
                        <option value="+30 days">30 Days</option>
                        <option value="+90 days">90 Days</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Max Downloads per File</label>
                    <input type="number" class="form-control" id="bulkMaxDownloads" value="10" min="1" max="100">
                </div>
                
                <div id="bulkShareResults" class="d-none">
                    <h6>Generated Links:</h6>
                    <div id="bulkShareLinks" class="border rounded p-3 bg-light"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="generateBulkShareBtn">
                    <i class="fas fa-share-alt me-2"></i>Generate Links
                </button>
            </div>
        </div>
    </div>
</div> 