<?php
session_start();
require '../includes/bd.php'; // Inclusion du fichier de connexion à la base de données

// Vérifier si l'utilisateur est un admin avant d'accéder au tableau de bord
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

// Récupération des statistiques pour le tableau de bord
$query_pilotes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'pilot'")->fetch_assoc();
$query_instructeurs = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'instructor'")->fetch_assoc();
$query_formations = $conn->query("SELECT COUNT(*) AS total FROM courses")->fetch_assoc();
$query_examens = $conn->query("SELECT COUNT(*) AS total FROM exams")->fetch_assoc();

// Récupérer les derniers utilisateurs ajoutés
$recent_users = $conn->query("SELECT id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Récupérer les derniers examens
$recent_exams = $conn->query("
    SELECT e.id, e.title, e.date, f.title as formation_title 
    FROM exams e 
    LEFT JOIN formations f ON e.formation_id = f.id 
    ORDER BY e.date DESC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Admin - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>


</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Tableau de bord</li>
            </ul>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card pilots">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $query_pilotes['total']; ?></div>
                    <div class="stat-label">Pilotes</div>
                </div>
            </div>
            <div class="stat-card instructors">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $query_instructeurs['total']; ?></div>
                    <div class="stat-label">Instructeurs</div>
                </div>
            </div>
            <div class="stat-card courses">
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $query_formations['total']; ?></div>
                    <div class="stat-label">Cours</div>
                </div>
            </div>
            <div class="stat-card exams">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $query_examens['total']; ?></div>
                    <div class="stat-label">Examens</div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Utilisateurs récents</h2>
                <a href="manage_users.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                                <?php while ($user = $recent_users->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-primary">Administrateur</span>
                                            <?php elseif ($user['role'] === 'pilot'): ?>
                                                <span class="badge bg-secondary">Pilote</span>
                                            <?php elseif ($user['role'] === 'instructor'): ?>
                                                <span class="badge bg-info">Instructeur</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td class="table-actions">
                                            <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="confirmDelete('Confirmer la suppression', 'Êtes-vous sûr de vouloir supprimer cet utilisateur ?', 'manage_users.php?delete=<?php echo $user['id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucun utilisateur trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Exams -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Examens récents</h2>
                <a href="manage_exams.php" class="btn btn-sm btn-outline-primary">Voir tous</a>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Cours</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_exams && $recent_exams->num_rows > 0): ?>
                                <?php while ($exam = $recent_exams->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['course_title'] ?? 'Non assigné'); ?></td>
                                        <td><?php echo $exam['date'] ? date('d/m/Y', strtotime($exam['date'])) : 'Non définie'; ?></td>
                                        <td class="table-actions">
                                            <a href="view_exam.php?id=<?php echo $exam['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="confirmDelete('Confirmer la suppression', 'Êtes-vous sûr de vouloir supprimer cet examen ?', 'manage_exams.php?delete=<?php echo $exam['id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Aucun examen trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="stats-grid">
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h2 class="card-title">Utilisateurs par mois</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="card animate-on-scroll">
                <div class="card-header">
                    <h2 class="card-title">Progression des cours</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="coursesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Profile Section -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Profil Utilisateur</h2>
                <a href="profile.php" class="btn btn-sm btn-outline-primary">Modifier le profil</a>
            </div>
            <div class="card-body">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (isset($_SESSION['avatar']) && !empty($_SESSION['avatar'])): ?>
                            <img src="../<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></h2>
                        <p><?php echo $_SESSION['email']; ?></p>
                        <p><strong>Rôle:</strong> Administrateur</p>
                        <p><strong>Dernière connexion:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        // Initialize charts when the DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Users Chart
            const usersCtx = document.getElementById('usersChart').getContext('2d');
            new Chart(usersCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                    datasets: [{
                        label: 'Nouveaux utilisateurs',
                        data: [5, 8, 12, 15, 10, 7, 9, 11, 13, 7, 6, 8],
                        borderColor: '#0a3d91',
                        backgroundColor: 'rgba(10, 61, 145, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Courses Chart
            const coursesCtx = document.getElementById('coursesChart').getContext('2d');
            new Chart(coursesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Terminés', 'En cours', 'Non commencés'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#28a745', '#f39200', '#0a3d91']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

