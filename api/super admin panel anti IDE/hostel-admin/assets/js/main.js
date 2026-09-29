/**
 * Main JavaScript Controller
 * Handles sidebar toggling, DataTables initialization, SweetAlert2 popups, animations, and form dynamics.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Sidebar Toggle Handler
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth <= 991.98) {
                sidebar.classList.toggle('show-mobile');
                if (sidebar.style.left === '0px') {
                    sidebar.style.left = '-270px';
                } else {
                    sidebar.style.left = '0px';
                }
            } else {
                // Desktop toggle
                if (sidebar.style.width === '80px') {
                    sidebar.style.width = '270px';
                    mainContent.style.marginLeft = '270px';
                    document.querySelectorAll('.nav-link-text, .sidebar-brand-text, .sidebar-category').forEach(el => el.style.display = 'block');
                } else {
                    sidebar.style.width = '80px';
                    mainContent.style.marginLeft = '80px';
                    document.querySelectorAll('.nav-link-text, .sidebar-brand-text, .sidebar-category').forEach(el => el.style.display = 'none');
                }
            }
        });
    }

    // Submenu toggling
    document.querySelectorAll('.has-submenu').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('data-bs-target'));
            if (target) {
                target.classList.toggle('show');
                const arrow = this.querySelector('.submenu-arrow');
                if (arrow) {
                    arrow.style.transform = target.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }
        });
    });

    // 2. Initialize DataTables with sleek responsive config
    if (typeof $ !== 'undefined' && $.fn.DataTable && $('#hostelTable').length > 0) {
        $('#hostelTable').DataTable({
            responsive: true,
            pageLength: 10,
            language: {
                search: "",
                searchPlaceholder: "Search hostels by name, code, city..."
            },
            columnDefs: [
                { orderable: false, targets: -1 } // Disable sorting on action column
            ],
            drawCallback: function() {
                // Re-initialize tooltips if any
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.map(function (tooltipTriggerEl) {
                        return new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }
            }
        });
    }

    // 3. Counter Animations for Dashboard Metric Cards
    const counterElements = document.querySelectorAll('[data-counter]');
    if (counterElements.length > 0) {
        counterElements.forEach(counter => {
            const target = +counter.getAttribute('data-counter');
            const duration = 1200; // ms
            const stepTime = Math.abs(Math.floor(duration / (target || 1)));
            let current = 0;
            
            if (target === 0) {
                counter.innerText = '0';
                return;
            }
            
            const timer = setInterval(() => {
                current += Math.ceil(target / 40);
                if (current >= target) {
                    counter.innerText = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    counter.innerText = current.toLocaleString();
                }
            }, Math.max(stepTime, 25));
        });
    }

    // 4. Image Upload Dropzone Preview
    const imageInput = document.getElementById('hostel_image');
    const imagePreview = document.getElementById('imagePreview');
    const dropzoneText = document.getElementById('dropzoneText');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid File Type',
                        text: 'Please upload only JPG, JPEG, or PNG images.',
                        confirmButtonColor: '#3b82f6'
                    });
                    this.value = '';
                    imagePreview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.style.display = 'block';
                    if (dropzoneText) dropzoneText.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});

/**
 * SweetAlert2 Confirmation for Delete Operations
 */
function confirmDelete(url, hostelName = 'this record') {
    Swal.fire({
        title: 'Are you sure?',
        html: `You are about to delete <strong>${hostelName}</strong>.<br><span class='text-danger'>This action cannot be undone!</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash me-1"></i> Yes, Delete It!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

/**
 * SweetAlert2 Confirmation for Status Toggle Operations
 */
function confirmStatusChange(url, currentStatus, hostelName = 'this hostel') {
    const newStatus = (currentStatus === 'Active') ? 'Inactive' : 'Active';
    const statusColor = (newStatus === 'Active') ? '#10b981' : '#f59e0b';
    
    Swal.fire({
        title: `Change Status to ${newStatus}?`,
        html: `Do you want to mark <strong>${hostelName}</strong> as <b style='color:${statusColor}'>${newStatus}</b>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: statusColor,
        cancelButtonColor: '#64748b',
        confirmButtonText: `Yes, make ${newStatus}`
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
