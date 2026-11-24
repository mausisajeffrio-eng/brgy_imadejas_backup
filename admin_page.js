// ============================================
// QR SCANNER & ATTENDANCE MODULE
// ============================================

let activeScanner = null;
let currentPage = 'daily';
let isSwitching = false;

// Sound effects using Web Audio API
function playSuccessSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Create a pleasant success sound (two-tone beep)
        const oscillator1 = audioContext.createOscillator();
        const oscillator2 = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator1.connect(gainNode);
        oscillator2.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // First tone (higher pitch)
        oscillator1.frequency.value = 800;
        oscillator1.type = 'sine';
        
        // Second tone (even higher pitch)
        oscillator2.frequency.value = 1000;
        oscillator2.type = 'sine';
        
        // Volume envelope
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator1.start(audioContext.currentTime);
        oscillator1.stop(audioContext.currentTime + 0.15);
        
        oscillator2.start(audioContext.currentTime + 0.1);
        oscillator2.stop(audioContext.currentTime + 0.3);
    } catch (error) {
        console.error('Error playing success sound:', error);
    }
}

function playErrorSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // Create an error sound (descending tone)
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // Start at higher frequency and descend
        oscillator.frequency.setValueAtTime(600, audioContext.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(200, audioContext.currentTime + 0.3);
        oscillator.type = 'sawtooth';
        
        // Volume envelope
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (error) {
        console.error('Error playing error sound:', error);
    }
}

// Function to update attendance records
async function updateAttendanceRecords() {
    try {
        console.log('Fetching attendance records...');
        const response = await fetch('admin_page.php?action=get_attendance_records');
        const result = await response.json();
        
        console.log('Attendance records response:', result);
        
        if (result.success && result.data) {
            const tbody = document.querySelector('#attendance-records tbody');
            if (!tbody) {
                console.error('Attendance records tbody not found!');
                return;
            }
            
            console.log('Updating attendance table with', result.data.length, 'records');
            
            // Add fade effect
            tbody.style.opacity = '0.5';
            tbody.style.transition = 'opacity 0.3s ease';
            
            if (result.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">No attendance records found</td></tr>';
                tbody.style.opacity = '1';
                return;
            }
            
            let html = '';
            result.data.forEach((record, index) => {
                html += `
                    <tr style="animation: slideIn 0.3s ease ${index * 0.05}s both;">
                        <td>${record.id}</td>
                        <td>${record.employee_id}</td>
                        <td>${record.first_name}</td>
                        <td>${record.last_name}</td>
                        <td>${record.position}</td>
                        <td>${record.clock_in}</td>
                        <td>${record.clock_out || '-'}</td>
                        <td>${record.type.toLowerCase() === 'daily' ? 'Daily' : record.type}</td>
                        <td>${record.details || ''}</td>
                        <td><span style="color: ${record.status === 'Complete' ? '#10b981' : '#f59e0b'}; font-weight: 500;">${record.status || ''}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            
            // Fade back in
            setTimeout(() => {
                tbody.style.opacity = '1';
            }, 100);
            
            console.log('Attendance table updated successfully!');
        }
    } catch (error) {
        console.error('Error updating attendance records:', error);
    }
}

// Function to update dashboard stats
async function updateDashboardStats() {
    try {
        const response = await fetch('admin_page.php?action=get_dashboard_stats');
        const result = await response.json();
        
        if (result.success) {
            // Update Present Today
            const presentValue = document.querySelector('.stat-card-success .stat-value');
            if (presentValue && result.present !== undefined) {
                presentValue.textContent = result.present;
                presentValue.style.animation = 'pulse 0.5s ease';
            }
            
            // Update Absent Today
            const absentValue = document.querySelector('.stat-card-danger .stat-value');
            if (absentValue && result.absent !== undefined) {
                absentValue.textContent = result.absent;
                absentValue.style.animation = 'pulse 0.5s ease';
            }
            
            // Update Total Employees
            const totalValue = document.querySelector('.stat-card-primary .stat-value');
            if (totalValue && result.total !== undefined) {
                totalValue.textContent = result.total;
                totalValue.style.animation = 'pulse 0.5s ease';
            }
        }
    } catch (error) {
        console.error('Error updating dashboard stats:', error);
    }
}

// Update status message with styling
function updateStatus(element, message, type = 'info') {
    const el = document.getElementById(`status-${element}`);
    if (!el) return;
    
    // Clear any existing timeouts
    if (el.timeoutId) {
        clearTimeout(el.timeoutId);
        el.timeoutId = null;
    }
    
    // Set the message and apply styles
    el.textContent = message;
    el.className = 'camera-status';
    el.classList.add(`camera-${type}`);
    
    // Auto-clear the message after a delay for certain message types
    if (['active', 'success', 'error', 'info', 'loading'].includes(type)) {
        const delay = type === 'loading' ? 10000 : (type === 'active' ? 3000 : 5000);
        
        el.timeoutId = setTimeout(() => {
            if (el.textContent === message) {
                el.textContent = '';
                el.className = 'camera-status';
            }
            el.timeoutId = null;
        }, delay);
    }
}

// Stop the active scanner and clean up
async function stopActiveScanner() {
    if (isSwitching) return;
    isSwitching = true;
    
    try {
        // Stop the Instascan scanner if it exists
        if (activeScanner) {
            await activeScanner.stop();
            activeScanner = null;
        }
        
        // Stop all video tracks
        const stops = [];
        document.querySelectorAll('video').forEach(video => {
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(track => {
                    stops.push(new Promise(resolve => {
                        track.onended = resolve;
                        track.stop();
                    }));
                });
                video.srcObject = null;
            }
        });
        
        await Promise.all(stops);
    } catch (e) {
        console.error('Error stopping scanner:', e);
    } finally {
        isSwitching = false;
    }
}

// Initialize the scanner for a specific tab
async function initScanner(tab) {
    const video = document.getElementById(`preview-${tab}`);
    const input = document.getElementById(`text-${tab}`);
    
    if (!video) return;
    
    try {
        if (!video.hasAttribute('data-initialized')) {
            video.setAttribute('data-initialized', 'true');
            updateStatus(tab, 'Initializing camera...', 'loading');
            
            // Stop any active scanner first
            await stopActiveScanner();
            
            // Initialize new scanner
            const scanner = new Instascan.Scanner({
                video: video,
                mirror: false
            });
            
            // Handle QR code scan
            scanner.addListener('scan', async (content) => {
                try {
                    // Validate QR code format: EMP-XXX|First Name|Last Name|Position
                    const qrData = content.split('|').map(part => part.trim());
                    
                    if (qrData.length !== 4 || !/^EMP-\d+$/.test(qrData[0])) {
                        throw new Error('Invalid QR code format. Expected: EMP-XXX|First Name|Last Name|Position');
                    }
                    
                    // Show scanning status
                    updateStatus(tab, 'Processing QR code...', 'loading');
                    
                    // Submit the form with the QR code data
                    input.value = content;
                    const form = input.closest('form');
                    
                    if (form) {
                        // Submit form via AJAX to handle response
                        const formData = new FormData(form);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        
                        const result = await response.text();
                        
                        if (response.ok) {
                            // Success - show appropriate message
                            if (result.includes('Clock In Successful') || result.includes('Clock Out Successful')) {
                                const action = result.includes('Clock In Successful') ? 'Clock In' : 'Clock Out';
                                updateStatus(tab, `✓ ${action} Successful`, 'success');
                                
                                // Play success sound
                                playSuccessSound();
                                
                                // Clear input and reset status after delay
                                input.value = '';
                                
                                // Update attendance records and dashboard stats
                                await updateAttendanceRecords();
                                await updateDashboardStats();
                                
                                setTimeout(() => {
                                    updateStatus(tab, 'Ready to scan', 'ready');
                                }, 2000);
                                
                                // Don't stop the scanner
                                return;
                            }
                            
                            // If we get here, it's a different success response
                            updateStatus(tab, '✓ Success', 'success');
                            playSuccessSound();
                            input.value = '';
                            
                        } else {
                            // Handle error response
                            const errorMatch = result.match(/<title>Error: (.*?)<\/title>/i);
                            const errorMessage = errorMatch ? errorMatch[1] : 'An error occurred';
                            throw new Error(errorMessage);
                        }
                    }
                } catch (error) {
                    console.error('Error processing QR code:', error);
                    updateStatus(tab, `✗ ${error.message || 'Error processing QR code'}`, 'error');
                    
                    // Play error sound
                    playErrorSound();
                    
                    // Clear input and reset status after delay
                    input.value = '';
                    setTimeout(() => {
                        updateStatus(tab, 'Ready to scan', 'ready');
                    }, 3000);
                }
            });
            
            // Get available cameras and start the scanner
            const cameras = await Instascan.Camera.getCameras();
            if (cameras.length === 0) {
                throw new Error('No cameras found');
            }
            
            // Try to use back camera, fall back to first available
            const backCamera = cameras.find(cam => 
                cam.name.toLowerCase().includes('back')
            ) || cameras[0];
            
            await scanner.start(backCamera);
            activeScanner = scanner;
            
            // Show camera container for all tabs
            const container = document.getElementById(`camera-container-${tab}`);
            if (container) container.style.display = 'flex';
            
            updateStatus(tab, 'Ready to scan', 'active');
        }
    } catch (err) {
        console.error('Scanner error:', err);
        updateStatus(tab, 'Camera error: ' + (err.message || 'Failed'), 'inactive');
        
        // Clean up on error
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(track => track.stop());
            video.srcObject = null;
        }
        video.removeAttribute('data-initialized');
    }
}

// Switch between attendance tabs
window.showPage = async function(tab) {
    if (currentPage === tab || isSwitching) return;
    
    try {
        // Stop active scanner first
        await stopActiveScanner();
        
        // Hide all pages and show the selected one
        document.querySelectorAll('.page').forEach(page => {
            page.classList.remove('active');
        });
        document.getElementById(tab).classList.add('active');
        
        // Update current page
        currentPage = tab;
        
        // Hide camera containers and reset buttons for all tabs
        const container = document.getElementById(`camera-container-${tab}`);
        if (container) container.style.display = 'none';
        
        // Reset and show the Start Camera button
        const startBtn = document.getElementById(`startCamera${tab.charAt(0).toUpperCase() + tab.slice(1)}`);
        if (startBtn) {
            startBtn.style.display = 'block';
            startBtn.disabled = false;
            startBtn.innerHTML = '<span class="las la-camera"></span> Start Camera';
        }
        
        // Set appropriate status message
        if (tab === 'daily') {
            updateStatus(tab, 'Click Start Camera to begin scanning', 'info');
        } else {
            updateStatus(tab, 'Enter details and click Start Camera', 'info');
        }
        
        // Update active tab styling
        updateActiveTab(tab);
    } catch (e) {
        console.error('Error showing page:', e);
        updateStatus(tab, 'Error: ' + (e.message || 'Failed to switch view'), 'inactive');
    }
};

// Update the active tab button styling
function updateActiveTab(tab) {
    ['daily', 'event', 'disaster'].forEach(id => {
        const btn = document.getElementById(`tabBtn-${id}`);
        if (!btn) return;
        
        if (id === tab) {
            btn.classList.add('primary');
        } else {
            btn.classList.remove('primary');
        }
    });
}

// Initialize attendance scanner when DOM is loaded
function initAttendanceScanner() {
    // Initial load of attendance records
    updateAttendanceRecords();
    
    // Set up event listeners for tab buttons
    ['daily', 'event', 'disaster'].forEach(tab => {
        const btn = document.getElementById(`tabBtn-${tab}`);
        if (btn) {
            btn.addEventListener('click', () => showPage(tab));
        }
    });
    
    // Set up Start Camera button for daily
    const startCameraDaily = document.getElementById('startCameraDaily');
    if (startCameraDaily) {
        startCameraDaily.addEventListener('click', async () => {
            startCameraDaily.disabled = true;
            startCameraDaily.textContent = 'Camera Starting...';
            await initScanner('daily');
            startCameraDaily.style.display = 'none';
        });
    }
    
    // Set up Start Camera button for event
    const startCameraEvent = document.getElementById('startCameraEvent');
    if (startCameraEvent) {
        startCameraEvent.addEventListener('click', async () => {
            const detailsInput = document.getElementById('eventDetails');
            if (!detailsInput.value.trim()) {
                updateStatus('event', 'Please enter event details first', 'error');
                detailsInput.focus();
                return;
            }
            startCameraEvent.disabled = true;
            startCameraEvent.textContent = 'Camera Starting...';
            await initScanner('event');
            startCameraEvent.style.display = 'none';
        });
    }
    
    // Set up Start Camera button for disaster
    const startCameraDisaster = document.getElementById('startCameraDisaster');
    if (startCameraDisaster) {
        startCameraDisaster.addEventListener('click', async () => {
            const detailsInput = document.getElementById('disasterDetails');
            if (!detailsInput.value.trim()) {
                updateStatus('disaster', 'Please enter disaster details first', 'error');
                detailsInput.focus();
                return;
            }
            startCameraDisaster.disabled = true;
            startCameraDisaster.textContent = 'Camera Starting...';
            await initScanner('disaster');
            startCameraDisaster.style.display = 'none';
        });
    }
    
    // Initialize the default tab
    showPage('daily');
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        stopActiveScanner();
    });
}

// ============================================
// DATATABLE & MODAL MODULE
// ============================================

let employeeTable;

// Handle QR Code Format modal
function initQRModal() {
    const qrModal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
    
    // Show modal only when the QR Format button is clicked
    document.querySelector('[data-bs-target="#qrCodeModal"]')?.addEventListener('click', function(e) {
        e.preventDefault();
        qrModal.show();
    });
}

// Initialize DataTable for employee management
function initDataTable() {
    $(document).ready(function() {
        // Check if DataTable is already initialized
        if (!$.fn.dataTable.isDataTable('#employeeTable')) {
            employeeTable = $('#employeeTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                order: [[0, 'desc']],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search employees...",
                },
                initComplete: function() {
                    // Add custom class to buttons container for styling
                    $('.dt-buttons').addClass('mb-3');
                }
            });
            
            // Store the DataTable instance for later use
            window.employeeTable = employeeTable;
        }
        
        // Re-draw the table when the employees tab is shown
        document.querySelector('a[data-target="employees"]')?.addEventListener('click', function() {
            if (window.employeeTable) {
                setTimeout(() => {
                    window.employeeTable.columns.adjust().responsive.recalc();
                }, 100);
            }
        });
    });
}

// ============================================
// TAB NAVIGATION MODULE
// ============================================

// Function to initialize tabs
function initTabs() {
    // Hide all sections first
    document.querySelectorAll('.section').forEach(section => {
        section.style.display = 'none';
        section.classList.remove('active');
    });
    
    // Set up click handlers for all tab links
    document.querySelectorAll('.side-menu a[data-target]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            switchToTab(targetId);
        });
    });
    
    // Handle initial page load with hash
    let hash = window.location.hash.substring(1);
    const validTabs = ['dashboard', 'attendance', 'employees', 'reports', 'profile', 'settings'];
    
    // Validate the hash
    if (!hash || !validTabs.includes(hash)) {
        hash = 'dashboard';
        window.history.replaceState(null, null, '#' + hash);
    }
    
    // Show the active tab
    switchToTab(hash);
}

// Function to switch tabs
function switchToTab(tabId) {
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.style.display = 'none';
        section.classList.remove('active');
    });
    
    // Deactivate all tab links
    document.querySelectorAll('.side-menu a').forEach(link => {
        link.classList.remove('active');
    });
    
    // Show the selected section and activate its tab
    const targetSection = document.getElementById(tabId);
    const targetLink = document.querySelector(`.side-menu a[data-target="${tabId}"]`);
    
    if (targetSection && targetLink) {
        targetSection.style.display = 'block';
        targetSection.classList.add('active');
        targetLink.classList.add('active');
        
        // Update URL without page reload
        if (window.location.hash.substring(1) !== tabId) {
            window.history.pushState(null, '', '#' + tabId);
        }
    }
}

// ============================================
// THEME MODULE
// ============================================

// Theme toggle functions
function applyTheme(theme) { 
    if (theme === 'dark') {
        document.body.classList.add('dark');
    } else {
        document.body.classList.remove('dark');
    }
    try {
        localStorage.setItem('theme', theme);
    } catch (e) {
        console.error('Error saving theme preference:', e);
    }
    // Sync Settings toggle
    var switchEl = document.getElementById('themeSwitch');
    if (switchEl) { switchEl.checked = (theme === 'dark'); }
}

// ============================================
// REPORTS MODULE
// ============================================

// Toggle custom date range visibility
function toggleCustomDateRange() {
    const select = document.getElementById('dateRangeSelect');
    const customRange = document.getElementById('customDateRange');
    const customRangeEnd = document.getElementById('customDateRangeEnd');
    
    if (select.value === 'custom') {
        customRange.style.display = 'flex';
        customRangeEnd.style.display = 'flex';
    } else {
        customRange.style.display = 'none';
        customRangeEnd.style.display = 'none';
    }
}

// ============================================
// MAIN INITIALIZATION
// ============================================

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize tabs
    initTabs();
    
    // Initialize QR Modal
    initQRModal();
    
    // Initialize DataTable
    initDataTable();
    
    // Initialize Attendance Scanner
    initAttendanceScanner();
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', () => {
        const hash = window.location.hash.substring(1);
        if (hash) {
            switchToTab(hash);
        } else {
            switchToTab('dashboard');
        }
    });
    
    // Initialize theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);
    
    // Initialize Settings toggle state
    var themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.checked = (savedTheme === 'dark' || document.body.classList.contains('dark'));
        themeSwitch.addEventListener('change', function() {
            applyTheme(this.checked ? 'dark' : 'light');
        });
    }
    
    // Set up theme toggle buttons
    // Header dropdown toggle
    const userMenu = document.getElementById('userMenu');
    const userDropdown = document.getElementById('userDropdown');
    if (userMenu) {
        userMenu.addEventListener('click', function(e) {
            var t = e.target;
            if (t && (t.id === 'headerGoProfile' || t.closest && t.closest('#headerGoProfile'))) return;
            e.stopPropagation();
            userMenu.classList.toggle('open');
        });
    }
    if (userDropdown) {
        userDropdown.addEventListener('click', function(e) {
            // Keep dropdown open while clicking inside
            e.stopPropagation();
        });
    }
    document.addEventListener('click', function() {
        if (userMenu) userMenu.classList.remove('open');
    });

    var headerGoProfile = document.getElementById('headerGoProfile');
    if (headerGoProfile) {
        headerGoProfile.addEventListener('click', function(e) {
            e.preventDefault();
            if (userMenu) userMenu.classList.remove('open');
            switchToTab('profile');
        });
    }
    
    // Theme toggle from header dropdown
    var headerThemeToggle = document.getElementById('headerThemeToggle');
    if (headerThemeToggle) {
        headerThemeToggle.addEventListener('click', function() {
            applyTheme(document.body.classList.contains('dark') ? 'light' : 'dark');
        });
    }
});
