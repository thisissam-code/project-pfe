<?php
// Récupérer le nom de la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-container">
            <img src="../images/Logo-white.png" alt="TTA Logo" class="sidebar-logo">
            <!-- <span class="sidebar-brand">TTA Instructor</span> -->
        </div>
        <button class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="instructor_dashboard.php" class="sidebar-link <?php echo ($current_page == 'instructor_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt sidebar-icon"></i>
                <span class="sidebar-text">Tableau de bord</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_formations.php" class="sidebar-link <?php echo ($current_page == 'instructor_formations.php' || $current_page == 'view_formation.php') ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap sidebar-icon"></i>
                <span class="sidebar-text">Mes formations</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_courses.php" class="sidebar-link <?php echo ($current_page == 'instructor_courses.php' || $current_page == 'view_course.php') ? 'active' : ''; ?>">
                <i class="fas fa-book sidebar-icon"></i>
                <span class="sidebar-text">Cours</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_exams.php" class="sidebar-link <?php echo ($current_page == 'instructor_exams.php' ) ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check sidebar-icon"></i>
                <span class="sidebar-text">Examens</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_pilots.php" class="sidebar-link <?php echo ($current_page == 'instructor_pilots.php' || $current_page == 'view_pilots.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate sidebar-icon"></i>
                <span class="sidebar-text">Étudiants</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_attestations.php" class="sidebar-link <?php echo ($current_page == 'instructor_attestations.php' || $current_page == 'view_attestation.php' || $current_page == 'edit_attestation.php') ? 'active' : ''; ?>">
                <i class="fas fa-certificate sidebar-icon"></i>
                <span class="sidebar-text">Attestations</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="instructor_profile.php" class="sidebar-link <?php echo ($current_page == 'instructor_profile.php') ? 'active' : ''; ?>">
                <i class="fas fa-user sidebar-icon"></i>
                <span class="sidebar-text">Profil</span>
            </a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])): ?>
                    <img src="../<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                <div class="sidebar-user-role">Instructeur</div>
            </div>
        </div>
        <a href="../pages/logout.php" class="sidebar-link mt-2">
            <i class="fas fa-sign-out-alt sidebar-icon"></i>
            <span class="sidebar-text">Déconnexion</span>
        </a>
    </div>
</div>
