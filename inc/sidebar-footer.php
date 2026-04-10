    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMenu(element) {
    const menuItems = element.nextElementSibling;
    const isOpen = menuItems.classList.contains('show');
    
    // Close all other menus
    document.querySelectorAll('.menu-items.show').forEach(item => {
        if (item !== menuItems) {
            item.classList.remove('show');
            item.previousElementSibling.classList.add('collapsed');
        }
    });
    
    // Toggle current menu
    if (isOpen) {
        menuItems.classList.remove('show');
        element.classList.add('collapsed');
    } else {
        menuItems.classList.add('show');
        element.classList.remove('collapsed');
    }
}

// Mobile sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('active');
    });
}

// Close sidebar on menu item click (mobile)
document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('active');
        }
    });
});

// Close sidebar when clicking outside (mobile)
document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768) {
        if (!e.target.closest('.sidebar') && !e.target.closest('.mobile-toggle')) {
            sidebar.classList.remove('active');
        }
    }
});

// Set active menu item based on current page
document.addEventListener('DOMContentLoaded', () => {
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop() || 'dashboard.php';
    let hasActiveItem = false;

    // List of pages and their corresponding sidebar items
    const pageMapping = {
        'dashboard.php': ['dashboard.php'],
        'students.php': ['students.php'],
        'teachers.php': ['teachers.php'],
        'classes.php': ['classes.php'],
        'attendance.php': ['attendance_daily.php', 'attendance_monthly.php', 'attendance_summary.php', 'attendance_semesters.php', 'attendance_sheet.php'],
        'attendance_daily.php': ['attendance_daily.php'],
        'attendance_monthly.php': ['attendance_monthly.php'],
        'attendance_summary.php': ['attendance_summary.php'],
        'attendance_semesters.php': ['attendance_semesters.php'],
        'attendance_sheet.php': ['attendance_sheet.php'],
        'grades.php': ['grades.php'],
        'grades_report.php': ['grades_report.php'],
        'report_card.php': ['report_card.php'],
        'fees.php': ['fees.php'],
        'schedule.php': ['schedule.php'],
        'financial.php': ['financial.php'],
        'enrollment.php': ['enrollment.php']
    };

    // Find which menu item should be active
    const pageToMatch = currentPage === '' ? 'dashboard.php' : currentPage;
    
    // Mark active menu item
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href').split('/').pop();
        const shouldBeActive = href === pageToMatch || 
                              (currentPage === '' && href === 'dashboard.php') ||
                              (currentPage.includes('attendance') && href.includes('attendance'));
        
        if (shouldBeActive) {
            item.classList.add('active');
            hasActiveItem = true;
            
            // Expand parent menu if item is inside
            const parentMenuItems = item.closest('.menu-items');
            if (parentMenuItems) {
                parentMenuItems.classList.add('show');
                const parentTitle = parentMenuItems.previousElementSibling;
                if (parentTitle) {
                    parentTitle.classList.remove('collapsed');
                    // Highlight the parent menu title too
                    parentTitle.style.color = '#e74c3c';
                }
            }
        } else {
            item.classList.remove('active');
        }
    });

    // Dashboard always links to itself
    if (currentPage === 'dashboard.php' || currentPath.endsWith('/school/') || currentPath.endsWith('/school')) {
        document.querySelectorAll('a[href="dashboard.php"]').forEach(item => {
            item.classList.add('active');
        });
    }
});
</script>
</body>
</html>
