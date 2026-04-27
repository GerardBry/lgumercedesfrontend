<!-- SHARED SIDEBAR COMPONENT - Copy this to every page -->
<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fas fa-building"></i>
        </div>
        <h1>LGU Mercedes</h1>
    </div>

    <nav class="nav-menu">
        <ul>
            <li>
                <a href="index.php" class="nav-item" data-page="dashboard">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="trackdocument.php" class="nav-item" data-page="track">
                    <i class="fas fa-search"></i>
                    <span>Track Documents</span>
                </a>
            </li>
            <li>
                <a href="documententry.php" class="nav-item" data-page="entry">
                    <i class="fas fa-file-upload"></i>
                    <span>Document Entry</span>
                </a>
            </li>
            <li class="divider"></li>
            <li>
                <a href="incoming.php" class="nav-item" data-page="incoming">
                    <i class="fas fa-inbox"></i>
                    <span>Incoming</span>
                </a>
            </li>
            <li>
                <a href="received.php" class="nav-item" data-page="received">
                    <i class="fas fa-envelope-open"></i>
                    <span>Received</span>
                </a>
            </li>
            <li>
                <a href="returned.php" class="nav-item" data-page="returned">
                    <i class="fas fa-undo"></i>
                    <span>Returned</span>
                </a>
            </li>
            <li>
                <a href="finished.php" class="nav-item" data-page="finished">
                    <i class="fas fa-check-circle"></i>
                    <span>Finished</span>
                </a>
            </li>
            <li>
                <a href="archive.php" class="nav-item" data-page="archive">
                    <i class="fas fa-archive"></i>
                    <span>Archive</span>
                </a>
            </li>
            <li class="divider"></li>
            <li>
                <a href="profile.php" class="nav-item" data-page="profile">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="avatar">USER</div>
            <div class="user-info">
                <p class="user-name" id="userNameDisplay">User</p>
                <p class="user-role">Guest</p>
            </div>
        </div>
        <button class="btn btn-secondary" onclick="handleLogout()" style="width: 100%; margin-top: 12px;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </button>
    </div>
</aside>

<!-- Add this SCRIPT at the end before closing </body> to set active nav item -->
<script>
    // Set active navigation item based on current page
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        const navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href === currentPage || (currentPage === '' && href === 'index.php')) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    });
</script>
