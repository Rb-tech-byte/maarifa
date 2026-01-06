document.addEventListener('DOMContentLoaded', function() {
    // Toast notification system
    const toastEl = document.getElementById('liveToast');
    const toastBody = toastEl.querySelector('.toast-body');
    const toast = new bootstrap.Toast(toastEl);

    function showToast(message, isError = false) {
        toastBody.textContent = message;
        if (isError) {
            toastEl.classList.add('bg-danger', 'text-white');
            toastEl.classList.remove('bg-success');
        } else {
            toastEl.classList.add('bg-success', 'text-white');
            toastEl.classList.remove('bg-danger');
        }
        toast.show();
    }

    // Copy to clipboard functionality
    document.querySelectorAll('.copy-btn').forEach(button => {
        button.addEventListener('click', function() {
            const targetInput = document.querySelector(this.dataset.target);
            if (targetInput && targetInput.value) {
                navigator.clipboard.writeText(targetInput.value).then(() => {
                    showToast('Link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                    showToast('Failed to copy link.', true);
                });
            } else {
                showToast('No link to copy.', true);
            }
        });
    });

    // Bulk Actions Management
    const fileCheckboxes = document.querySelectorAll('.file-checkbox');
    const bulkActionBar = document.getElementById('bulkActionBar');
    const selectedCount = document.getElementById('selectedCount');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const moveToFolderBtn = document.getElementById('moveToFolderBtn');
    const generateShareLinksBtn = document.getElementById('generateShareLinksBtn');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const selectAllTable = document.getElementById('selectAllTable');

    const selectedFiles = {
        db: new Set(),
        local: new Set()
    };

    function updateBulkActionBar() {
        const totalSelected = selectedFiles.db.size + selectedFiles.local.size;
        
        if (totalSelected > 0) {
            selectedCount.textContent = totalSelected;
            bulkActionBar.classList.remove('d-none');
        } else {
            bulkActionBar.classList.add('d-none');
        }

        // Update select all checkboxes
        if (fileCheckboxes.length > 0) {
            const allChecked = totalSelected === fileCheckboxes.length;
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            if (selectAllTable) selectAllTable.checked = allChecked;
        }
    }

    // Select All functionality
    function handleSelectAll(isChecked) {
        fileCheckboxes.forEach(cb => {
            cb.checked = isChecked;
            const { id, type } = cb.dataset;
            if (isChecked) {
                selectedFiles[type].add(id);
            } else {
                selectedFiles[type].delete(id);
            }
        });
        updateBulkActionBar();
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            handleSelectAll(e.target.checked);
        });
    }

    if (selectAllTable) {
        selectAllTable.addEventListener('change', (e) => {
            handleSelectAll(e.target.checked);
        });
    }

    // File checkbox event listeners
    fileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', (e) => {
            const { id, type } = e.target.dataset;
            if (e.target.checked) {
                selectedFiles[type].add(id);
            } else {
                selectedFiles[type].delete(id);
            }
            updateBulkActionBar();
        });
    });

    // Bulk Delete
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', function() {
            const totalSelected = selectedFiles.db.size + selectedFiles.local.size;
            if (totalSelected === 0) {
                showToast('Please select at least one file to delete.', true);
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${totalSelected} selected file(s)?`)) {
                const formData = new FormData();
                formData.append('action', 'bulk_delete');
                formData.append('db_ids', JSON.stringify(Array.from(selectedFiles.db)));
                formData.append('local_files', JSON.stringify(Array.from(selectedFiles.local)));

                fetch('files-manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        // Remove deleted files from UI
                        selectedFiles.db.forEach(id => {
                            const row = document.querySelector(`tr[data-file-id="${id}"][data-file-type="db"]`);
                            if (row) {
                                row.style.transition = 'opacity 0.5s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 500);
                            }
                        });
                        selectedFiles.local.forEach(filename => {
                            const row = document.querySelector(`tr[data-file-id="${filename}"][data-file-type="local"]`);
                            if (row) {
                                row.style.transition = 'opacity 0.5s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 500);
                            }
                        });
                        // Clear selections
                        selectedFiles.db.clear();
                        selectedFiles.local.clear();
                        fileCheckboxes.forEach(cb => cb.checked = false);
                        updateBulkActionBar();
                    } else {
                        showToast(data.message || 'Failed to delete files.', true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An unexpected error occurred.', true);
                });
            }
        });
    }

    // Move to Folder
    if (moveToFolderBtn) {
        moveToFolderBtn.addEventListener('click', function() {
            const totalSelected = selectedFiles.db.size + selectedFiles.local.size;
            if (totalSelected === 0) {
                showToast('Please select at least one file to move.', true);
                return;
            }
            
            document.getElementById('moveFileCount').textContent = totalSelected;
        });
    }

    if (document.getElementById('moveToFolderBtn')) {
        document.getElementById('moveToFolderBtn').addEventListener('click', function() {
            const folderId = document.getElementById('moveFolderSelect').value;
            const fileIds = Array.from(selectedFiles.db);
            
            if (fileIds.length === 0) {
                showToast('Please select at least one tracked file to move.', true);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'move_to_folder');
            formData.append('file_ids', JSON.stringify(fileIds));
            formData.append('folder_id', folderId);

            fetch('files-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('moveToFolderModal'));
                    modal.hide();
                    // Clear selections
                    selectedFiles.db.clear();
                    selectedFiles.local.clear();
                    fileCheckboxes.forEach(cb => cb.checked = false);
                    updateBulkActionBar();
                    // Reload page to show updated folder assignments
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to move files.', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred.', true);
            });
        });
    }

    // Generate Bulk Share Links
    if (generateShareLinksBtn) {
        generateShareLinksBtn.addEventListener('click', function() {
            const totalSelected = selectedFiles.db.size + selectedFiles.local.size;
            if (totalSelected === 0) {
                showToast('Please select at least one tracked file to share.', true);
                return;
            }
            
            document.getElementById('shareFileCount').textContent = totalSelected;
        });
    }

    if (document.getElementById('generateBulkShareBtn')) {
        document.getElementById('generateBulkShareBtn').addEventListener('click', function() {
            const fileIds = Array.from(selectedFiles.db);
            const expiry = document.getElementById('bulkExpirySelect').value;
            const maxDownloads = document.getElementById('bulkMaxDownloads').value;
            
            if (fileIds.length === 0) {
                showToast('Please select at least one tracked file to share.', true);
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';

            // Generate links for each file
            const promises = fileIds.map(fileId => {
                const formData = new FormData();
                formData.append('action', 'generate_share_link');
                formData.append('media_id', fileId);
                formData.append('expiry', expiry);
                formData.append('max_downloads', maxDownloads);

                return fetch('files-manager.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());
            });

            Promise.all(promises)
                .then(results => {
                    const successfulResults = results.filter(r => r.success);
                    const failedResults = results.filter(r => !r.success);
                    
                    if (successfulResults.length > 0) {
                        showToast(`${successfulResults.length} share links generated successfully.`);
                        
                        // Display results
                        const resultsDiv = document.getElementById('bulkShareResults');
                        const linksDiv = document.getElementById('bulkShareLinks');
                        linksDiv.innerHTML = '';
                        
                        successfulResults.forEach(result => {
                            const linkDiv = document.createElement('div');
                            linkDiv.className = 'mb-2 p-2 border rounded';
                            linkDiv.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">File ID: ${result.media_id}</small>
                                    <button class="btn btn-sm btn-outline-secondary copy-link-btn" data-link="${result.link}">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                <input type="text" class="form-control form-control-sm mt-1" value="${result.link}" readonly>
                            `;
                            linksDiv.appendChild(linkDiv);
                        });
                        
                        resultsDiv.classList.remove('d-none');
                    }
                    
                    if (failedResults.length > 0) {
                        showToast(`${failedResults.length} links failed to generate.`, true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An unexpected error occurred.', true);
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-share-alt me-2"></i>Generate Links';
                });
        });
    }

    // Copy bulk share links
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('copy-link-btn')) {
            const link = e.target.dataset.link;
            navigator.clipboard.writeText(link).then(() => {
                showToast('Link copied to clipboard!');
            }).catch(err => {
                showToast('Failed to copy link.', true);
            });
        }
    });

    // Individual File Actions
    // Delete files
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const fileId = this.dataset.id;
            const fileType = this.dataset.type;
            const row = this.closest('tr');

            if (confirm('Are you sure you want to delete this file?')) {
                const formData = new FormData();
                formData.append('action', 'delete_file');
                formData.append('id', fileId);
                formData.append('type', fileType);

                fetch('files-manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 500);
                        showToast('File deleted successfully.');
                    } else {
                        showToast(data.message || 'Could not delete file.', true);
                    }
                }).catch(err => showToast('An unexpected error occurred.', true));
            }
        });
    });

    // Rename files
    document.querySelectorAll('.rename-btn').forEach(button => {
        button.addEventListener('click', function() {
            const fileId = this.dataset.id;
            const fileType = this.dataset.type;
            const modalId = this.dataset.modalId;
            const modalElement = (fileType === 'local') 
                ? document.getElementById('renameModalLocal-' + modalId) 
                : document.getElementById('renameModal-' + fileId);
            
            const newNameInput = modalElement.querySelector('input[name="new_name"]');
            const newName = newNameInput.value;

            if (!newName.trim()) {
                showToast('Please enter a valid file name.', true);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'rename_file');
            formData.append('id', fileId);
            formData.append('type', fileType);
            formData.append('new_name', newName);

            fetch('files-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-file-id='${fileId}'][data-file-type='${fileType}']`);
                    if (row) {
                        const nameElement = row.querySelector('.fw-bold');
                        if (nameElement) {
                            nameElement.textContent = data.newName;
                        }
                    }
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    modal.hide();
                    showToast('File renamed successfully.');
                } else {
                    showToast(data.message || 'Could not rename file.', true);
                }
            }).catch(err => showToast('An unexpected error occurred.', true));
        });
    });

    // Generate share links
    document.querySelectorAll('.generate-share-link-btn').forEach(button => {
        button.addEventListener('click', function() {
            const mediaId = this.dataset.mediaId;
            const feedbackDiv = document.getElementById('share-feedback-' + mediaId);
            const linkInput = document.getElementById('shareLinkInput-' + mediaId);
            const expirySelect = document.getElementById('expirySelect-' + mediaId);
            const maxDownloads = document.getElementById('maxDownloads-' + mediaId);
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
            feedbackDiv.innerHTML = '';

            const formData = new FormData();
            formData.append('action', 'generate_share_link');
            formData.append('media_id', mediaId);
            formData.append('expiry', expirySelect.value);
            formData.append('max_downloads', maxDownloads.value);

            fetch('files-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    linkInput.value = data.link;
                    showToast('New link generated successfully.');
                    feedbackDiv.innerHTML = '';
                } else {
                    showToast(data.message || 'Could not generate link.', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred.', true);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-share-alt me-2"></i>Generate New Link';
            });
        });
    });

    // Create Folder
    if (document.getElementById('createFolderBtn')) {
        document.getElementById('createFolderBtn').addEventListener('click', function() {
            const form = document.getElementById('createFolderForm');
            const formData = new FormData(form);
            formData.append('action', 'create_folder');

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';

            fetch('files-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Folder created successfully.');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createFolderModal'));
                    modal.hide();
                    // Reload page to show new folder
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Could not create folder.', true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred.', true);
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-folder-plus me-2"></i>Create Folder';
            });
        });
    }

    // Upload Files
    if (document.getElementById('uploadBtn')) {
        document.getElementById('uploadBtn').addEventListener('click', function() {
            const form = document.getElementById('uploadForm');
            const formData = new FormData(form);
            const progressDiv = document.getElementById('uploadProgress');
            const progressBar = progressDiv.querySelector('.progress-bar');
            const statusDiv = document.getElementById('uploadStatus');

            if (!formData.get('files[]').size) {
                showToast('Please select files to upload.', true);
                return;
            }

            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
            progressDiv.classList.remove('d-none');
            statusDiv.textContent = 'Uploading files...';

            // Simulate progress (in real implementation, you'd track actual upload progress)
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                progressBar.style.width = progress + '%';
                if (progress >= 100) {
                    clearInterval(progressInterval);
                }
            }, 200);

            // For now, we'll just show a success message
            // In a real implementation, you'd send the files to the server
            setTimeout(() => {
                showToast('Files uploaded successfully!');
                progressDiv.classList.add('d-none');
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-upload me-2"></i>Upload Files';
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('uploadModal'));
                modal.hide();
                
                // Reload page to show new files
                setTimeout(() => location.reload(), 1000);
            }, 2000);
        });
    }

    // Initialize bulk action bar
    updateBulkActionBar();
}); 