<?php
session_start();
require '../includes/bd.php'; // Inclusion du fichier de connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder au tableau de bord
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas pilote
    exit();
}

$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Récupération des statistiques pour le tableau de bord
// Nombre de formations disponibles pour le pilote (selon sa section et fonction)
$query_formations = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM formations 
    WHERE (section = ? OR section IS NULL) 
    AND (fonction = ? OR fonction IS NULL)
");
$query_formations->bind_param("ss", $pilot_section, $pilot_fonction);
$query_formations->execute();
$formations_count = $query_formations->get_result()->fetch_assoc()['total'];

// Nombre de cours associés aux formations du pilote
$query_courses = $conn->prepare("
    SELECT COUNT(DISTINCT fc.course_id) AS total 
    FROM formation_courses fc
    JOIN formations f ON fc.formation_id = f.id
    WHERE (f.section = ? OR f.section IS NULL) 
    AND (f.fonction = ? OR f.fonction IS NULL)
");
$query_courses->bind_param("ss", $pilot_section, $pilot_fonction);
$query_courses->execute();
$courses_count = $query_courses->get_result()->fetch_assoc()['total'];

// Nombre d'examens disponibles pour le pilote
$query_exams = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE (f.section = ? OR f.section IS NULL) 
    AND (f.fonction = ? OR f.fonction IS NULL)
");
$query_exams->bind_param("ss", $pilot_section, $pilot_fonction);
$query_exams->execute();
$exams_count = $query_exams->get_result()->fetch_assoc()['total'];

// Nombre d'attestations du pilote
$query_attestations = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM attestations 
    WHERE user_id = ?
");
$query_attestations->bind_param("i", $pilot_id);
$query_attestations->execute();
$attestations_count = $query_attestations->get_result()->fetch_assoc()['total'];

// Récupérer les attestations qui expirent bientôt (dans moins d'un mois)
$query_expiring_attestations = $conn->prepare("
    SELECT a.id, a.title, a.date_emission, a.date_expiration, a.statut,
           f.title as formation_title,
           DATEDIFF(a.date_expiration, CURDATE()) as days_remaining
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.user_id = ? 
    AND a.statut = 'bientot_expire'
    AND a.date_expiration IS NOT NULL
    AND DATEDIFF(a.date_expiration, CURDATE()) <= 30
    ORDER BY a.date_expiration ASC
");
$query_expiring_attestations->bind_param("i", $pilot_id);
$query_expiring_attestations->execute();
$result_expiring_attestations = $query_expiring_attestations->get_result();

// Récupérer les attestations expirées
$query_expired_attestations = $conn->prepare("
    SELECT a.id, a.title, a.date_emission, a.date_expiration, a.statut,
           f.title as formation_title,
           DATEDIFF(CURDATE(), a.date_expiration) as days_expired
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.user_id = ? 
    AND a.statut = 'expire'
    ORDER BY a.date_expiration DESC
");
$query_expired_attestations->bind_param("i", $pilot_id);
$query_expired_attestations->execute();
$result_expired_attestations = $query_expired_attestations->get_result();

// Récupérer les formations disponibles pour le pilote
$query_pilot_formations = $conn->prepare("
    SELECT f.id, f.title, f.duration, f.section, f.fonction, f.created_at,
           COUNT(DISTINCT fc.course_id) AS course_count,
           (SELECT COUNT(*) FROM exam_results er JOIN exams e ON er.exam_id = e.id WHERE e.formation_id = f.id AND er.pilot_id = ?) AS completed_exams
    FROM formations f
    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
    WHERE (f.section = ? OR f.section IS NULL) 
    AND f.fonction = ?
    AND f.id NOT IN (
        SELECT formation_id FROM attestations 
        WHERE user_id = ? AND (statut = 'valide' OR statut = 'bientot_expire')
    )
    GROUP BY f.id
    ORDER BY f.created_at DESC
    LIMIT 5
");
$query_pilot_formations->bind_param("issi", $pilot_id, $pilot_section, $pilot_fonction, $pilot_id);
$query_pilot_formations->execute();
$result_formations = $query_pilot_formations->get_result();

// Récupérer les examens disponibles pour le pilote
$query_pilot_exams = $conn->prepare("
    SELECT e.id, e.title, e.description, e.duration, e.passing_score, e.date,
           f.title as formation_title, f.id as formation_id,
           (SELECT status FROM pilot_exams WHERE pilot_id = ? AND exam_id = e.id) as status,
           (SELECT COUNT(*) FROM exam_results WHERE pilot_id = ? AND exam_id = e.id) as attempts
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE (f.section = ? OR f.section IS NULL) 
    AND f.fonction = ?
    AND f.id NOT IN (
        SELECT formation_id FROM attestations 
        WHERE user_id = ? AND (statut = 'valide' OR statut = 'bientot_expire')
    )
    ORDER BY e.date DESC
    LIMIT 5
");
$query_pilot_exams->bind_param("iissi", $pilot_id, $pilot_id, $pilot_section, $pilot_fonction, $pilot_id);
$query_pilot_exams->execute();
$result_exams = $query_pilot_exams->get_result();

// Récupérer les attestations valides du pilote
$query_pilot_attestations = $conn->prepare("
    SELECT a.id, a.title, a.date_emission, a.date_expiration, a.statut,
           f.title as formation_title
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.user_id = ? AND a.statut = 'valide'
    ORDER BY a.date_emission DESC
    LIMIT 5
");
$query_pilot_attestations->bind_param("i", $pilot_id);
$query_pilot_attestations->execute();
$result_attestations = $query_pilot_attestations->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Pilote - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .alert-section {
            margin-bottom: 30px;
        }
        
        .alert-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            overflow: hidden;
            border-left: 4px solid #dc3545;
        }
        
        .alert-card.warning {
            border-left-color: #ffc107;
        }
        
        .alert-card.danger {
            border-left-color: #dc3545;
        }
        
        .alert-header {
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-title {
            font-weight: 500;
            color: #343a40;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .alert-title i {
            margin-right: 8px;
        }
        
        .alert-body {
            padding: 15px;
        }
        
        .alert-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .alert-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .alert-value {
            font-weight: 500;
        }
        
        .alert-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .alert-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-section-title {
            margin-bottom: 15px;
            font-size: 1.25rem;
            color: #0a3d91;
            display: flex;
            align-items: center;
        }
        
        .alert-section-title i {
            margin-right: 10px;
        }
        
        .empty-alert {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
        
        .dashboard-welcome {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #0a3d91;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            color: #0a3d91;
            margin-bottom: 10px;
        }
        
        .welcome-text {
            color: #6c757d;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/pilote_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de bord Pilote</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Tableau de bord</li>
            </ul>
        </div>

        <!-- Welcome Message -->
        <div class="dashboard-welcome">
            <h2 class="welcome-title">Bienvenue, <?php echo $_SESSION['first_name']; ?> !</h2>
            <p class="welcome-text">Voici votre tableau de bord personnel. Consultez vos alertes, formations et attestations.</p>
        </div>

        <!-- Alerts Section -->
        <div class="alert-section">
            <h2 class="alert-section-title"><i class="fas fa-exclamation-triangle"></i> Alertes</h2>
            
            <?php if ($result_expiring_attestations && $result_expiring_attestations->num_rows > 0): ?>
                <h3 class="section-title">Attestations expirant bientôt</h3>
                <?php while ($attestation = $result_expiring_attestations->fetch_assoc()): ?>
                    <div class="alert-card warning">
                        <div class="alert-header">
                            <h4 class="alert-title">
                                <i class="fas fa-clock"></i> 
                                <?php echo htmlspecialchars($attestation['title'] ?: $attestation['formation_title']); ?>
                            </h4>
                            <span class="alert-badge badge-warning">
                                Expire dans <?php echo $attestation['days_remaining']; ?> jour(s)
                            </span>
                        </div>
                        <div class="alert-body">
                            <div class="alert-info">
                                <span class="alert-label">Formation:</span>
                                <span class="alert-value"><?php echo htmlspecialchars($attestation['formation_title']); ?></span>
                            </div>
                            <div class="alert-info">
                                <span class="alert-label">Date d'émission:</span>
                                <span class="alert-value"><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></span>
                            </div>
                            <div class="alert-info">
                                <span class="alert-label">Date d'expiration:</span>
                                <span class="alert-value"><?php echo date('d/m/Y', strtotime($attestation['date_expiration'])); ?></span>
                            </div>
                            <div class="alert-actions">
                                <a href="attestation_details.php?id=<?php echo $attestation['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Voir les détails
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
            
            <?php if ($result_expired_attestations && $result_expired_attestations->num_rows > 0): ?>
                <h3 class="section-title">Attestations expirées</h3>
                <?php while ($attestation = $result_expired_attestations->fetch_assoc()): ?>
                    <div class="alert-card danger">
                        <div class="alert-header">
                            <h4 class="alert-title">
                                <i class="fas fa-exclamation-circle"></i> 
                                <?php echo htmlspecialchars($attestation['title'] ?: $attestation['formation_title']); ?>
                            </h4>
                            <span class="alert-badge badge-danger">
                                Expirée depuis <?php echo $attestation['days_expired']; ?> jour(s)
                            </span>
                        </div>
                        <div class="alert-body">
                            <div class="alert-info">
                                <span class="alert-label">Formation:</span>
                                <span class="alert-value"><?php echo htmlspecialchars($attestation['formation_title']); ?></span>
                            </div>
                            <div class="alert-info">
                                <span class="alert-label">Date d'émission:</span>
                                <span class="alert-value"><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></span>
                            </div>
                            <div class="alert-info">
                                <span class="alert-label">Date d'expiration:</span>
                                <span class="alert-value"><?php echo date('d/m/Y', strtotime($attestation['date_expiration'])); ?></span>
                            </div>
                            <div class="alert-actions">
                                <a href="attestation_details.php?id=<?php echo $attestation['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Voir les détails
                                </a>
                                <a href="formation_details.php?id=<?php echo $attestation['formation_id']; ?>" class="btn btn-sm btn-success">
                                    <i class="fas fa-sync-alt"></i> Renouveler
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
            
            <?php if ((!$result_expiring_attestations || $result_expiring_attestations->num_rows == 0) && 
                      (!$result_expired_attestations || $result_expired_attestations->num_rows == 0)): ?>
                <div class="empty-alert">
                    <i class="fas fa-check-circle fa-3x mb-3" style="color: #28a745;"></i>
                    <p>Aucune alerte à afficher. Toutes vos attestations sont à jour.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card formations">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $formations_count; ?></div>
                    <div class="stat-label">Formations</div>
                </div>
            </div>
            <div class="stat-card courses">
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $courses_count; ?></div>
                    <div class="stat-label">Cours</div>
                </div>
            </div>
            <div class="stat-card exams">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $exams_count; ?></div>
                    <div class="stat-label">Examens</div>
                </div>
            </div>
            <div class="stat-card attestations">
                <div class="stat-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $attestations_count; ?></div>
                    <div class="stat-label">Attestations</div>
                </div>
            </div>
        </div>

        <!-- Formations List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Mes Formations</h2>
                <a href="pilot_formations.php" class="btn btn-sm btn-outline-primary">Voir toutes</a>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Section</th>
                                <th>Fonction</th>
                                <th>Durée</th>
                                <th>Cours</th>
                                <th>Examens complétés</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($formation['title']); ?></td>
                                        <td><?php echo $formation['section'] ? htmlspecialchars(ucfirst($formation['section'])) : '-'; ?></td>
                                        <td><?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : '-'; ?></td>
                                        <td><?php echo $formation['duration']; ?> heures</td>
                                        <td><?php echo $formation['course_count']; ?></td>
                                        <td><?php echo $formation['completed_exams']; ?></td>
                                        <td class="table-actions">
                                            <a href="formation_details.php?id=<?php echo $formation['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="formation_courses.php?id=<?php echo $formation['id']; ?>" class="btn-action" data-tooltip="Cours">
                                                <i class="fas fa-book"></i>
                                            </a>
                                            <a href="formation_exams.php?id=<?php echo $formation['id']; ?>" class="btn-action" data-tooltip="Examens">
                                                <i class="fas fa-clipboard-list"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucune formation trouvée pour votre section et fonction</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Attestations List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Mes Attestations</h2>
                <a href="pilot_attestations.php" class="btn btn-sm btn-outline-primary">Voir toutes</a>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Formation</th>
                                <th>Date d'émission</th>
                                <th>Date d'expiration</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_attestations && $result_attestations->num_rows > 0): ?>
                                <?php while ($attestation = $result_attestations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attestation['title'] ?: $attestation['formation_title']); ?></td>
                                        <td><?php echo htmlspecialchars($attestation['formation_title']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></td>
                                        <td><?php echo $attestation['date_expiration'] ? date('d/m/Y', strtotime($attestation['date_expiration'])) : '-'; ?></td>
                                        <td>
                                            <?php if ($attestation['statut'] === 'valide'): ?>
                                                <span class="badge bg-success">Valide</span>
                                            <?php elseif ($attestation['statut'] === 'bientot_expire'): ?>
                                                <span class="badge bg-warning">Bientôt expiré</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Expiré</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="attestation_details.php?id=<?php echo $attestation['id']; ?>" class="btn-action" data-tooltip="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" class="btn-action" data-tooltip="Télécharger" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucune attestation trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
