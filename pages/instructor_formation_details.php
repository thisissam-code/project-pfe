<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Vérifier si un ID de formation est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: instructor_formations.php");
    exit();
}

$formation_id = intval($_GET['id']);

// Vérifier si l'instructeur est assigné à cette formation
$query_check = $conn->prepare("
    SELECT COUNT(*) as count
    FROM formation_instructors
    WHERE formation_id = ? AND instructor_id = ?
");
$query_check->bind_param("ii", $formation_id, $instructor_id);
$query_check->execute();
$is_assigned = $query_check->get_result()->fetch_assoc()['count'] > 0;

if (!$is_assigned) {
    header("Location: instructor_formations.php");
    exit();
}

// Récupérer les détails de la formation
$query_formation = $conn->prepare("
    SELECT f.*
    FROM formations f
    WHERE f.id = ?
");
$query_formation->bind_param("i", $formation_id);
$query_formation->execute();
$result_formation = $query_formation->get_result();

if ($result_formation->num_rows === 0) {
    header("Location: instructor_formations.php");
    exit();
}

$formation = $result_formation->fetch_assoc();

// Récupérer les cours associés à la formation
$query_courses = $conn->prepare("
    SELECT c.*
    FROM courses c
    JOIN formation_courses fc ON c.id = fc.course_id
    WHERE fc.formation_id = ?
    ORDER BY c.title
");
$query_courses->bind_param("i", $formation_id);
$query_courses->execute();
$result_courses = $query_courses->get_result();

// Récupérer les examens associés à la formation
$query_exams = $conn->prepare("
    SELECT e.*, 
           COUNT(DISTINCT er.id) as attempt_count,
           SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_count
    FROM exams e
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE e.formation_id = ?
    GROUP BY e.id
    ORDER BY e.date DESC
");
$query_exams->bind_param("i", $formation_id);
$query_exams->execute();
$result_exams = $query_exams->get_result();

// Récupérer les pilotes assignés à cette formation
$query_pilots = $conn->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.section, u.fonction,
           (SELECT COUNT(*) FROM exam_results er JOIN exams e ON er.exam_id = e.id 
            WHERE e.formation_id = ? AND er.pilot_id = u.id AND er.status = 'passed') as passed_exams,
           (SELECT COUNT(*) FROM attestations a WHERE a.formation_id = ? AND a.user_id = u.id) as has_attestation
    FROM users u
    JOIN pilot_exams pe ON u.id = pe.pilot_id
    JOIN exams e ON pe.exam_id = e.id
    WHERE e.formation_id = ? AND u.role = 'pilot'
    ORDER BY u.last_name, u.first_name
");
$query_pilots->bind_param("iii", $formation_id, $formation_id, $formation_id);
$query_pilots->execute();
$result_pilots = $query_pilots->get_result();

// Récupérer les statistiques de la formation
$query_stats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as course_count,
        COUNT(DISTINCT e.id) as exam_count,
        COUNT(DISTINCT pe.pilot_id) as pilot_count,
        COUNT(DISTINCT a.id) as attestation_count,
        SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_exams,
        SUM(CASE WHEN er.status = 'failed' THEN 1 ELSE 0 END) as failed_exams
    FROM formations f
    LEFT JOIN formation_courses fc ON f.id = fc.formation_id
    LEFT JOIN courses c ON fc.course_id = c.id
    LEFT JOIN exams e ON f.id = e.formation_id
    LEFT JOIN pilot_exams pe ON e.id = pe.exam_id
    LEFT JOIN exam_results er ON e.id = er.exam_id
    LEFT JOIN attestations a ON f.id = a.formation_id
    WHERE f.id = ?
");
$query_stats->bind_param("i", $formation_id);
$query_stats->execute();
$stats = $query_stats->get_result()->fetch_assoc();

// Vérifier si un ID de pilote est fourni pour afficher ses détails
$pilot_details = null;
$pilot_exams = null;

if (isset($_GET['pilot_id']) && is_numeric($_GET['pilot_id'])) {
    $pilot_id = intval($_GET['pilot_id']);
    
    // Récupérer les détails du pilote
    $query_pilot_details = $conn->prepare("
        SELECT u.*
        FROM users u
        WHERE u.id = ? AND u.role = 'pilot'
    ");
    $query_pilot_details->bind_param("i", $pilot_id);
    $query_pilot_details->execute();
    $pilot_details = $query_pilot_details->get_result()->fetch_assoc();
    
    if ($pilot_details) {
        // Récupérer les résultats d'examens du pilote pour cette formation
        $query_pilot_exams = $conn->prepare("
            SELECT e.id, e.title, e.date, e.passing_score, 
                   er.score, er.status, er.completion_time, er.attempt_date
            FROM exams e
            LEFT JOIN exam_results er ON e.id = er.exam_id AND er.pilot_id = ?
            WHERE e.formation_id = ?
            ORDER BY e.date DESC, er.attempt_date DESC
        ");
        $query_pilot_exams->bind_param("ii", $pilot_id, $formation_id);
        $query_pilot_exams->execute();
        $pilot_exams = $query_pilot_exams->get_result();
    }
}

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_formation_details', CONCAT('Consultation des détails de la formation #', ?), NOW())
");
$log_query->bind_param("ii", $instructor_id, $formation_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la Formation - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .formation-header {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0a3d91;
        }
        
        .formation-title {
            font-size: 1.8rem;
            color: #0a3d91;
            margin-bottom: 10px;
        }
        
        .formation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #6c757d;
        }
        
        .meta-item i {
            margin-right: 8px;
            color: #0a3d91;
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
        
        .courses-card {
            border-top: 4px solid #28a745;
        }
        
        .exams-card {
            border-top: 4px solid #dc3545;
        }
        
        .pilots-card {
            border-top: 4px solid #ffc107;
        }
        
        .attestations-card {
            border-top: 4px solid #0a3d91;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: #343a40;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .course-card {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .course-header {
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .course-title {
            margin: 0;
            font-size: 1.1rem;
            color: #343a40;
        }
        
        .course-duration {
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .course-body {
            padding: 15px;
        }
        
        .course-description {
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .course-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .exam-card {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .exam-header {
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-title {
            margin: 0;
            font-size: 1.1rem;
            color: #343a40;
        }
        
        .exam-stats {
            display: flex;
            gap: 10px;
        }
        
        .exam-stat {
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .exam-stat i {
            margin-right: 5px;
        }
        
        .exam-body {
            padding: 15px;
        }
        
        .exam-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-group {
            flex: 1;
            min-width: 150px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .exam-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .pilot-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .pilot-table th, .pilot-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .pilot-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
        }
        
        .pilot-table tr:hover {
            background-color: #f8f9fa;
            cursor: pointer;
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
        
        .instructor-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .instructor-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 250px;
        }
        
        .instructor-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .instructor-email {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .empty-state i {
            font-size: 2rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6c757d;
            margin: 0;
        }
        
        /* Styles pour la section de détails du pilote */
        .pilot-details {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .pilot-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .pilot-name {
            font-size: 1.5rem;
            color: #0a3d91;
            margin: 0;
        }
        
        .pilot-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .pilot-info-item {
            display: flex;
            align-items: center;
            color: #6c757d;
        }
        
        .pilot-info-item i {
            margin-right: 8px;
            color: #0a3d91;
        }
        
        .pilot-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .pilot-stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .pilot-stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .pilot-stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .pilot-exam-results {
            margin-top: 20px;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .result-table th, .result-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .result-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
        }
        
        .result-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .close-details {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 8px 15px;
            color: #6c757d;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .close-details:hover {
            background-color: #e9ecef;
        }
        
        .score-cell {
            font-weight: 600;
        }
        
        .passed {
            color: #28a745;
        }
        
        .failed {
            color: #dc3545;
        }
        
        .not-attempted {
            color: #6c757d;
            font-style: italic;
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
            <h1 class="page-title">Détails de la Formation</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="instructor_formations.php" class="breadcrumb-link">Formations</a></li>
                <li class="breadcrumb-item">Détails</li>
            </ul>
        </div>

        <!-- Formation Header -->
        <div class="formation-header">
            <h2 class="formation-title"><?php echo htmlspecialchars($formation['title']); ?></h2>
            <div class="formation-meta">
                <div class="meta-item">
                    <i class="fas fa-layer-group"></i>
                    <span>Section: <?php echo $formation['section'] ? ucfirst(htmlspecialchars($formation['section'])) : 'Toutes'; ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user-tag"></i>
                    <span>Fonction: <?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : 'Toutes'; ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Durée: <?php echo $formation['duration']; ?> heures</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Créée le: <?php echo date('d/m/Y', strtotime($formation['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card courses-card">
                <i class="fas fa-book-open fa-2x"></i>
                <div class="stat-value"><?php echo $stats['course_count']; ?></div>
                <div class="stat-label">Cours</div>
            </div>
            <div class="stat-card exams-card">
                <i class="fas fa-clipboard-check fa-2x"></i>
                <div class="stat-value"><?php echo $stats['exam_count']; ?></div>
                <div class="stat-label">Examens</div>
            </div>
            <div class="stat-card pilots-card">
                <i class="fas fa-users fa-2x"></i>
                <div class="stat-value"><?php echo $stats['pilot_count']; ?></div>
                <div class="stat-label">Pilotes</div>
            </div>
            <div class="stat-card attestations-card">
                <i class="fas fa-certificate fa-2x"></i>
                <div class="stat-value"><?php echo $stats['attestation_count']; ?></div>
                <div class="stat-label">Attestations</div>
            </div>
        </div>

        <?php if ($pilot_details): ?>
        <!-- Détails du pilote sélectionné -->
        <div class="pilot-details">
            <div class="pilot-header">
                <h3 class="pilot-name"><?php echo htmlspecialchars($pilot_details['first_name'] . ' ' . $pilot_details['last_name']); ?></h3>
                <a href="instructor_formation_details.php?id=<?php echo $formation_id; ?>" class="close-details">
                    <i class="fas fa-times"></i> Fermer les détails
                </a>
            </div>
            
            <div class="pilot-info">
                <div class="pilot-info-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($pilot_details['email']); ?></span>
                </div>
                <?php if ($pilot_details['section']): ?>
                <div class="pilot-info-item">
                    <i class="fas fa-layer-group"></i>
                    <span>Section: <?php echo ucfirst(htmlspecialchars($pilot_details['section'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($pilot_details['fonction']): ?>
                <div class="pilot-info-item">
                    <i class="fas fa-user-tag"></i>
                    <span>Fonction: <?php echo htmlspecialchars($pilot_details['fonction']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php
            // Calculer les statistiques du pilote
            $total_exams = 0;
            $passed_exams = 0;
            $failed_exams = 0;
            $not_attempted = 0;
            $avg_score = 0;
            $scores_sum = 0;
            $scores_count = 0;
            
            if ($pilot_exams && $pilot_exams->num_rows > 0) {
                $total_exams = $pilot_exams->num_rows;
                $pilot_exams_data = [];
                
                while ($exam = $pilot_exams->fetch_assoc()) {
                    $pilot_exams_data[] = $exam;
                    
                    if ($exam['score'] !== null) {
                        if ($exam['status'] === 'passed') {
                            $passed_exams++;
                        } else {
                            $failed_exams++;
                        }
                        
                        $scores_sum += $exam['score'];
                        $scores_count++;
                    } else {
                        $not_attempted++;
                    }
                }
                
                $avg_score = $scores_count > 0 ? round($scores_sum / $scores_count, 1) : 0;
                
                // Reset le pointeur du résultat
                $pilot_exams = $pilot_exams_data;
            }
            ?>
            
            <div class="pilot-stats">
                <div class="pilot-stat-card">
                    <div class="pilot-stat-value"><?php echo $total_exams; ?></div>
                    <div class="pilot-stat-label">Examens totaux</div>
                </div>
                <div class="pilot-stat-card">
                    <div class="pilot-stat-value passed"><?php echo $passed_exams; ?></div>
                    <div class="pilot-stat-label">Examens réussis</div>
                </div>
                <div class="pilot-stat-card">
                    <div class="pilot-stat-value failed"><?php echo $failed_exams; ?></div>
                    <div class="pilot-stat-label">Examens échoués</div>
                </div>
                <div class="pilot-stat-card">
                    <div class="pilot-stat-value"><?php echo $not_attempted; ?></div>
                    <div class="pilot-stat-label">Non tentés</div>
                </div>
                <div class="pilot-stat-card">
                    <div class="pilot-stat-value"><?php echo $avg_score; ?>%</div>
                    <div class="pilot-stat-label">Score moyen</div>
                </div>
            </div>
            
            <div class="pilot-exam-results">
                <h4 class="section-title"><i class="fas fa-clipboard-list"></i> Résultats des examens</h4>
                
                <?php if (!empty($pilot_exams)): ?>
                <div class="table-container">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>Examen</th>
                                <th>Date</th>
                                <th>Score minimum</th>
                                <th>Score obtenu</th>
                                <th>Statut</th>
                                <th>Temps</th>
                                <th>Date de passage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pilot_exams as $exam): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                <td><?php echo $exam['date'] ? date('d/m/Y', strtotime($exam['date'])) : '-'; ?></td>
                                <td><?php echo $exam['passing_score']; ?>%</td>
                                <td class="score-cell <?php echo $exam['score'] !== null ? ($exam['status'] === 'passed' ? 'passed' : 'failed') : 'not-attempted'; ?>">
                                    <?php echo $exam['score'] !== null ? $exam['score'] . '%' : 'Non tenté'; ?>
                                </td>
                                <td>
                                    <?php if ($exam['score'] !== null): ?>
                                        <?php if ($exam['status'] === 'passed'): ?>
                                            <span class="badge bg-success">Réussi</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Échoué</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non tenté</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $exam['completion_time'] ? $exam['completion_time'] . ' min' : '-'; ?></td>
                                <td><?php echo $exam['attempt_date'] ? date('d/m/Y H:i', strtotime($exam['attempt_date'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard"></i>
                    <p>Aucun résultat d'examen disponible pour ce pilote.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Courses Section -->
        <h3 class="section-title"><i class="fas fa-book-open"></i> Cours associés</h3>
        <?php if ($result_courses && $result_courses->num_rows > 0): ?>
            <?php while ($course = $result_courses->fetch_assoc()): ?>
                <div class="course-card">
                    <div class="course-header">
                        <h4 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h4>
                        <span class="course-duration"><?php echo $course['duration']; ?> heures</span>
                    </div>
                    <div class="course-body">
                        <div class="course-description">
                            <?php echo htmlspecialchars($course['description'] ?? 'Aucune description disponible.'); ?>
                        </div>
                        <div class="course-actions">
                            <?php if (!empty($course['file_path'])): ?>
                                <a href="<?php echo htmlspecialchars($course['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-file-alt"></i> Voir le support de cours
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book"></i>
                <p>Aucun cours n'est associé à cette formation.</p>
            </div>
        <?php endif; ?>

        <!-- Exams Section -->
        <h3 class="section-title"><i class="fas fa-clipboard-check"></i> Examens associés</h3>
        <?php if ($result_exams && $result_exams->num_rows > 0): ?>
            <?php while ($exam = $result_exams->fetch_assoc()): ?>
                <div class="exam-card">
                    <div class="exam-header">
                        <h4 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h4>
                        <div class="exam-stats">
                            <span class="exam-stat">
                                <i class="fas fa-users"></i> <?php echo $exam['attempt_count']; ?> tentatives
                            </span>
                            <span class="exam-stat">
                                <i class="fas fa-check-circle"></i> <?php echo $exam['passed_count']; ?> réussites
                            </span>
                        </div>
                    </div>
                    <div class="exam-body">
                        <div class="exam-info">
                            <div class="info-group">
                                <div class="info-label">Durée</div>
                                <div class="info-value"><?php echo $exam['duration']; ?> minutes</div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Score minimum</div>
                                <div class="info-value"><?php echo $exam['passing_score']; ?>%</div>
                            </div>
                            <div class="info-group">
                                <div class="info-label">Date</div>
                                <div class="info-value">
                                    <?php echo $exam['date'] ? date('d/m/Y', strtotime($exam['date'])) : 'Non définie'; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($exam['description'])): ?>
                            <div class="exam-description">
                                <?php echo htmlspecialchars($exam['description']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="exam-actions">
                            <a href="instructor_exam_details.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard"></i>
                <p>Aucun examen n'est associé à cette formation.</p>
            </div>
        <?php endif; ?>

        <!-- Pilots Section -->
        <h3 class="section-title"><i class="fas fa-users"></i> Pilotes assignés</h3>
        <?php if ($result_pilots && $result_pilots->num_rows > 0): ?>
            <div class="table-container">
                <table class="pilot-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Section</th>
                            <th>Fonction</th>
                            <th>Examens réussis</th>
                            <th>Attestation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pilot = $result_pilots->fetch_assoc()): ?>
                            <tr onclick="window.location='instructor_formation_details.php?id=<?php echo $formation_id; ?>&pilot_id=<?php echo $pilot['id']; ?>'">
                                <td><?php echo htmlspecialchars($pilot['first_name'] . ' ' . $pilot['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($pilot['email']); ?></td>
                                <td><?php echo $pilot['section'] ? ucfirst(htmlspecialchars($pilot['section'])) : '-'; ?></td>
                                <td><?php echo $pilot['fonction'] ? htmlspecialchars($pilot['fonction']) : '-'; ?></td>
                                <td><?php echo $pilot['passed_exams']; ?></td>
                                <td>
                                    <?php if ($pilot['has_attestation'] > 0): ?>
                                        <span class="badge bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Non</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-user-slash"></i>
                <p>Aucun pilote n'est assigné à cette formation.</p>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="instructor_formations.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour aux formations
            </a>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>