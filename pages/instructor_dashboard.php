<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Récupérer les informations de l'instructeur
$query_instructor = $conn->prepare("
    SELECT first_name, last_name, email, avatar 
    FROM users 
    WHERE id = ? AND role = 'instructor'
");
$query_instructor->bind_param("i", $instructor_id);
$query_instructor->execute();
$result_instructor = $query_instructor->get_result();

if ($result_instructor->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$instructor = $result_instructor->fetch_assoc();

// Récupérer le nombre de formations associées à l'instructeur
$query_formations = $conn->prepare("
    SELECT COUNT(DISTINCT f.id) as formation_count
    FROM formations f
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
");
$query_formations->bind_param("i", $instructor_id);
$query_formations->execute();
$formations_count = $query_formations->get_result()->fetch_assoc()['formation_count'];

// Récupérer le nombre de pilotes dans les formations de l'instructeur
$query_pilots = $conn->prepare("
    SELECT COUNT(DISTINCT pe.pilot_id) as pilot_count
    FROM pilot_exams pe
    JOIN exams e ON pe.exam_id = e.id
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
");
$query_pilots->bind_param("i", $instructor_id);
$query_pilots->execute();
$pilots_count = $query_pilots->get_result()->fetch_assoc()['pilot_count'];

// Récupérer le nombre d'examens dans les formations de l'instructeur
$query_exams = $conn->prepare("
    SELECT COUNT(DISTINCT e.id) as exam_count
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
");
$query_exams->bind_param("i", $instructor_id);
$query_exams->execute();
$exams_count = $query_exams->get_result()->fetch_assoc()['exam_count'];

// Récupérer le nombre d'attestations dans les formations de l'instructeur
$query_attestations = $conn->prepare("
    SELECT COUNT(DISTINCT a.id) as attestation_count
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
");
$query_attestations->bind_param("i", $instructor_id);
$query_attestations->execute();
$attestations_count = $query_attestations->get_result()->fetch_assoc()['attestation_count'];

// Récupérer les formations récentes de l'instructeur
$query_recent_formations = $conn->prepare("
    SELECT f.id, f.title, f.section, f.fonction, f.duration, f.created_at,
           COUNT(DISTINCT e.id) as exam_count,
           COUNT(DISTINCT pe.pilot_id) as pilot_count
    FROM formations f
    JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN exams e ON f.id = e.formation_id
    LEFT JOIN pilot_exams pe ON e.id = pe.exam_id
    WHERE fi.instructor_id = ?
    GROUP BY f.id
    ORDER BY f.created_at DESC
    LIMIT 5
");
$query_recent_formations->bind_param("i", $instructor_id);
$query_recent_formations->execute();
$result_recent_formations = $query_recent_formations->get_result();

// Récupérer les examens récents dans les formations de l'instructeur
$query_recent_exams = $conn->prepare("
    SELECT e.id, e.title, e.date, f.title as formation_title,
           COUNT(DISTINCT er.id) as result_count,
           SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_count
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE fi.instructor_id = ?
    GROUP BY e.id
    ORDER BY e.date DESC
    LIMIT 5
");
$query_recent_exams->bind_param("i", $instructor_id);
$query_recent_exams->execute();
$result_recent_exams = $query_recent_exams->get_result();

// Récupérer les pilotes récemment évalués
$query_recent_pilots = $conn->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, u.section, u.fonction,
           MAX(er.date_taken) as last_exam_date,
           COUNT(DISTINCT er.id) as exam_count,
           SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_count
    FROM users u
    JOIN exam_results er ON u.id = er.pilot_id
    JOIN exams e ON er.exam_id = e.id
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ? AND u.role = 'pilot'
    GROUP BY u.id
    ORDER BY last_exam_date DESC
    LIMIT 5
");
$query_recent_pilots->bind_param("i", $instructor_id);
$query_recent_pilots->execute();
$result_recent_pilots = $query_recent_pilots->get_result();

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'dashboard_access', 'Accès au tableau de bord instructeur', NOW())
");
$log_query->bind_param("i", $instructor_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Instructeur - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .welcome-banner {
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .formations {
            border-top: 4px solid #0a3d91;
        }
        
        .pilots {
            border-top: 4px solid #28a745;
        }
        
        .exams {
            border-top: 4px solid #dc3545;
        }
        
        .attestations {
            border-top: 4px solid #ffc107;
        }
        
        .section-title {
            margin-bottom: 15px;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .formation-card {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .formation-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .formation-title {
            margin: 0;
            font-size: 1.1rem;
            color: #0a3d91;
        }
        
        .formation-body {
            padding: 15px 20px;
        }
        
        .formation-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .info-item {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .formation-footer {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            text-align: right;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            color: #fff;
        }
        
        .bg-success {
            background-color: #28a745;
        }
        
        .bg-danger {
            background-color: #dc3545;
        }
        
        .bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .bg-info {
            background-color: #17a2b8;
        }
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view {
            background-color: #0a3d91;
        }
        
        .btn-edit {
            background-color: #ffc107;
        }
        
        .btn-delete {
            background-color: #dc3545;
        }
        
        .btn-action:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/instructor_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Tableau de Bord Instructeur</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Tableau de bord</li>
            </ul>
        </div>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2 class="welcome-title">Bienvenue, <?php echo htmlspecialchars($instructor['first_name']); ?> !</h2>
            <p class="welcome-text">Voici votre tableau de bord personnel. Consultez vos formations, pilotes, examens et attestations.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card formations">
                <i class="fas fa-graduation-cap fa-2x"></i>
                <div class="stat-value"><?php echo $formations_count; ?></div>
                <div class="stat-label">Formations</div>
            </div>
            <div class="stat-card pilots">
                <i class="fas fa-users fa-2x"></i>
                <div class="stat-value"><?php echo $pilots_count; ?></div>
                <div class="stat-label">Pilotes</div>
            </div>
            <div class="stat-card exams">
                <i class="fas fa-clipboard-check fa-2x"></i>
                <div class="stat-value"><?php echo $exams_count; ?></div>
                <div class="stat-label">Examens</div>
            </div>
            <div class="stat-card attestations">
                <i class="fas fa-certificate fa-2x"></i>
                <div class="stat-value"><?php echo $attestations_count; ?></div>
                <div class="stat-label">Attestations</div>
            </div>
        </div>

        <!-- Recent Formations -->
        <h3 class="section-title">Formations récentes</h3>
        <div class="card-grid">
            <?php if ($result_recent_formations && $result_recent_formations->num_rows > 0): ?>
                <?php while ($formation = $result_recent_formations->fetch_assoc()): ?>
                    <div class="formation-card">
                        <div class="formation-header">
                            <h4 class="formation-title"><?php echo htmlspecialchars($formation['title']); ?></h4>
                        </div>
                        <div class="formation-body">
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-layer-group"></i> Section: <?php echo $formation['section'] ? ucfirst(htmlspecialchars($formation['section'])) : 'Toutes'; ?></span>
                                <span class="info-item"><i class="fas fa-user-tag"></i> Fonction: <?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : 'Toutes'; ?></span>
                            </div>
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-clock"></i> Durée: <?php echo $formation['duration']; ?> heures</span>
                                <span class="info-item"><i class="fas fa-calendar-alt"></i> Créée le: <?php echo date('d/m/Y', strtotime($formation['created_at'])); ?></span>
                            </div>
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-clipboard-check"></i> Examens: <?php echo $formation['exam_count']; ?></span>
                                <span class="info-item"><i class="fas fa-users"></i> Pilotes: <?php echo $formation['pilot_count']; ?></span>
                            </div>
                        </div>
                        <div class="formation-footer">
                            <a href="instructor_formation_details.php?id=<?php echo $formation['id']; ?>" class="btn btn-sm btn-primary">Voir les détails</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aucune formation trouvée. Contactez l'administrateur pour être assigné à des formations.
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Exams -->
        <h3 class="section-title">Examens récents</h3>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Formation</th>
                                <th>Date</th>
                                <th>Résultats</th>
                                <th>Taux de réussite</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_recent_exams && $result_recent_exams->num_rows > 0): ?>
                                <?php while ($exam = $result_recent_exams->fetch_assoc()): ?>
                                    <?php 
                                        $success_rate = $exam['result_count'] > 0 ? round(($exam['passed_count'] / $exam['result_count']) * 100) : 0;
                                        $badge_class = '';
                                        if ($success_rate >= 80) {
                                            $badge_class = 'bg-success';
                                        } elseif ($success_rate >= 50) {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['formation_title']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($exam['date'])); ?></td>
                                        <td><?php echo $exam['result_count']; ?> (<?php echo $exam['passed_count']; ?> réussis)</td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo $success_rate; ?>%</span></td>
                                        <td class="table-actions">
                                            <a href="instructor_exam_results.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucun examen récent trouvé</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Pilots -->
        <h3 class="section-title">Pilotes récemment évalués</h3>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Section</th>
                                <th>Fonction</th>
                                <th>Dernier examen</th>
                                <th>Examens passés</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_recent_pilots && $result_recent_pilots->num_rows > 0): ?>
                                <?php while ($pilot = $result_recent_pilots->fetch_assoc()): ?>
                                    <?php 
                                        $success_rate = $pilot['exam_count'] > 0 ? round(($pilot['passed_count'] / $pilot['exam_count']) * 100) : 0;
                                        $badge_class = '';
                                        if ($success_rate >= 80) {
                                            $badge_class = 'bg-success';
                                        } elseif ($success_rate >= 50) {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pilot['first_name'] . ' ' . $pilot['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($pilot['email']); ?></td>
                                        <td><?php echo $pilot['section'] ? ucfirst(htmlspecialchars($pilot['section'])) : '-'; ?></td>
                                        <td><?php echo $pilot['fonction'] ? htmlspecialchars($pilot['fonction']) : '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($pilot['last_exam_date'])); ?></td>
                                        <td>
                                            <?php echo $pilot['passed_count']; ?>/<?php echo $pilot['exam_count']; ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $success_rate; ?>%</span>
                                        </td>
                                        <td class="table-actions">
                                            <a href="instructor_pilot_details.php?id=<?php echo $pilot['id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> Voir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Aucun pilote récemment évalué</td>
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
