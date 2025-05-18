<?php
// Récupérer le nom de la page actuelle
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-container">
            <img src="../images/Logo-white.png" alt="TTA Logo" class="sidebar-logo">
            <!-- <span class="sidebar-brand">TTA Admin</span> -->
        </div>
        <button class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a href="admin_dashboard_sol.php" class="sidebar-link <?php echo ($current_page == 'admin_dashboard_sol.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt sidebar-icon"></i>
                <span class="sidebar-text">Tableau de bord</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a href="manage_courses_sol.php" class="sidebar-link <?php echo ($current_page == 'manage_courses_sol.php' || $current_page == 'edit_course_sol.php') ? 'active' : ''; ?>">
                <i class="fas fa-book sidebar-icon"></i>
                <span class="sidebar-text">Gestion des cours</span>
            </a>
        </li>
        
        <li class="sidebar-item">
            <a href="manage_formations_sol.php" class="sidebar-link <?php echo ($current_page == 'manage_formations_sol.php') ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap sidebar-icon"></i>
                <span class="sidebar-text">Gestion des formations</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="manage_exam_results_sol.php" class="sidebar-link <?php echo ($current_page == 'manage_exam_results.php') ? 'active' : ''; ?>">
                <i class="fas fa-cog sidebar-icon"></i>
                <span class="sidebar-text">Manage examens</span>
            </a>    
        </li>
            
        <li class="sidebar-item">
            <a href="ajouter_question_sol.php" class="sidebar-link <?php echo ($current_page == 'ajouter_question_sol.php' ) ? 'active' : ''; ?>">
                <i class="fas fa-users sidebar-icon"></i>
                <span class="sidebar-text">Gestion des question</span>
            </a>
        </li>    
        
        
        <li class="sidebar-item">
            <a href="manage_attestations_sol.php" class="sidebar-link <?php echo ($current_page == 'manage_attestations_sol.php' ) ? 'active' : ''; ?>">
                <i class="fas fa-users sidebar-icon"></i>
                <span class="sidebar-text">Gestion des attestation</span>
            </a>
        </li>
        <li class="sidebar-item">
            <a href="view_library_sol.php" class="sidebar-link <?php echo ($current_page == 'pilot_library.php') ? 'active' : ''; ?>">
                <i class="fas fa-book-reader sidebar-icon"></i>
                <span class="sidebar-text">Bibliothèque</span>
            </a>
        </li>
                
        <li class="sidebar-item">
            <a href="profile_sol.php" class="sidebar-link <?php echo ($current_page == 'profile_sol.php') ? 'active' : ''; ?>">
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
                <div class="sidebar-user-role">
                    <?php 
                    switch($_SESSION['role']) {
                        case 'admin':
                            echo 'Administrateur';
                            break;
                        case 'pilot':
                            echo 'Pilote';
                            break;
                        case 'instructor':
                            echo 'Instructeur';
                            break;
                        default:
                            echo 'Utilisateur';
                    }
                    ?>
                </div>
            </div>
        </div>
        <a href="../pages/logout.php" class="sidebar-link mt-2">
            <i class="fas fa-sign-out-alt sidebar-icon"></i>
            <span class="sidebar-text">Déconnexion</span>
        </a>
    </div>
</div>

