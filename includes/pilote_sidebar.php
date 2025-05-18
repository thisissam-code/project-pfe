<?php
// Récupérer le nom de la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-container">
            <img src="../images/Logo-white.png" alt="TTA Logo" class="sidebar-logo">
            <!-- <span class="sidebar-brand">TTA Pilot</span> -->
        </div>
        <button class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="pilote_dashboard.php" class="sidebar-link <?php echo ($current_page == 'pilot_dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt sidebar-icon"></i>
                <span class="sidebar-text">Tableau de bord</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="pilot_formations.php" class="sidebar-link <?php echo ($current_page == 'pilot_formations.php' || $current_page == 'view_formation.php') ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap sidebar-icon"></i>
                <span class="sidebar-text">Mes formations</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="pilot_exam.php" class="sidebar-link <?php echo ($current_page == 'pilot_exam.php' ) ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check sidebar-icon"></i>
                <span class="sidebar-text">Examens</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="pilot_attestations.php" class="sidebar-link <?php echo ($current_page == 'pilot_attestations.php' || $current_page == 'view_attestation.php') ? 'active' : ''; ?>">
                <i class="fas fa-certificate sidebar-icon"></i>
                <span class="sidebar-text">Attestations</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="view_library.php" class="sidebar-link <?php echo ($current_page == 'pilot_library.php') ? 'active' : ''; ?>">
                <i class="fas fa-book-reader sidebar-icon"></i>
                <span class="sidebar-text">Bibliothèque</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="pilot_profile.php" class="sidebar-link <?php echo ($current_page == 'pilot_profile.php') ? 'active' : ''; ?>">
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
                <div class="sidebar-user-role">Pilote</div>
            </div>
        </div>
        <a href="../pages/logout.php" class="sidebar-link mt-2">
            <i class="fas fa-sign-out-alt sidebar-icon"></i>
            <span class="sidebar-text">Déconnexion</span>
        </a>
    </div>
</div>
