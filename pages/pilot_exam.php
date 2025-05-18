<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Récupérer tous les examens disponibles pour le pilote
$query_exams = $conn->prepare("
    SELECT e.id, e.title, e.description, e.duration, e.passing_score, e.date,
           f.title as formation_title, f.id as formation_id,
           (SELECT status FROM pilot_exams WHERE pilot_id = ? AND exam_id = e.id) as status,
           (SELECT COUNT(*) FROM exam_results WHERE pilot_id = ? AND exam_id = e.id) as attempts,
           (SELECT MAX(score) FROM exam_results WHERE pilot_id = ? AND exam_id = e.id) as best_score
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE (f.section = ? OR f.section IS NULL) 
    AND (f.fonction = ? OR f.fonction IS NULL)
    ORDER BY e.date DESC
");
$query_exams->bind_param("iiiss", $pilot_id, $pilot_id, $pilot_id, $pilot_section, $pilot_fonction);
$query_exams->execute();
$result_exams = $query_exams->get_result();

// Récupérer les statistiques des examens
$query_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_exams,
        SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_exams,
        SUM(CASE WHEN er.status = 'failed' THEN 1 ELSE 0 END) as failed_exams,
        ROUND(AVG(er.score), 1) as average_score
    FROM exam_results er
    WHERE er.pilot_id = ?
");
$query_stats->bind_param("i", $pilot_id);
$query_stats->execute();
$stats = $query_stats->get_result()->fetch_assoc();

// Récupérer les examens récemment passés
$query_recent = $conn->prepare("
    SELECT er.id, er.score, er.status, er.date_taken,
           e.title as exam_title, e.id as exam_id,
           f.title as formation_title
    FROM exam_results er
    JOIN exams e ON er.exam_id = e.id
    JOIN formations f ON e.formation_id = f.id
    WHERE er.pilot_id = ?
    ORDER BY er.date_taken DESC
    LIMIT 5
");
$query_recent->bind_param("i", $pilot_id);
$query_recent->execute();
$result_recent = $query_recent->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examens - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
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
        
        .total-exams {
            border-top: 4px solid #0a3d91;
        }
        
        .passed-exams {
            border-top: 4px solid #28a745;
        }
        
        .failed-exams {
            border-top: 4px solid #dc3545;
        }
        
        .average-score {
            border-top: 4px solid #ffc107;
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
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .exam-card {
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .exam-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-title {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .exam-body {
            padding: 20px;
            background-color: #fff;
        }
        
        .exam-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .exam-description {
            margin-bottom: 20px;
            color: #495057;
        }
        
        .exam-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
            <h1 class="page-title">Examens</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Examens</li>
            </ul>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total-exams">
                <div class="stat-label">Total des examens passés</div>
                <div class="stat-value"><?php echo $stats['total_exams'] ?? 0; ?></div>
            </div>
            <div class="stat-card passed-exams">
                <div class="stat-label">Examens réussis</div>
                <div class="stat-value"><?php echo $stats['passed_exams'] ?? 0; ?></div>
            </div>
            <div class="stat-card failed-exams">
                <div class="stat-label">Examens échoués</div>
                <div class="stat-value"><?php echo $stats['failed_exams'] ?? 0; ?></div>
            </div>
            <div class="stat-card average-score">
                <div class="stat-label">Score moyen</div>
                <div class="stat-value"><?php echo $stats['average_score'] ?? 0; ?>%</div>
            </div>
        </div>

        <!-- Recent Exams Results -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Résultats récents</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Examen</th>
                                <th>Formation</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_recent && $result_recent->num_rows > 0): ?>
                                <?php while ($result = $result_recent->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                        <td><?php echo htmlspecialchars($result['formation_title']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($result['date_taken'])); ?></td>
                                        <td><?php echo round($result['score'], 1); ?>%</td>
                                        <td>
                                            <?php if ($result['status'] === 'passed'): ?>
                                                <span class="badge bg-success">Réussi</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Échoué</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="exam_results.php?id=<?php echo $result['exam_id']; ?>" class="btn-action" data-tooltip="Voir les résultats">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Aucun examen passé récemment</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Available Exams -->
        <h2 class="section-title">Examens disponibles</h2>
        
        <div class="row">
            <?php if ($result_exams && $result_exams->num_rows > 0): ?>
                <?php while ($exam = $result_exams->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="exam-card">
                            <div class="exam-header">
                                <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                                <?php if ($exam['attempts'] > 0): ?>
                                    <?php if ($exam['best_score'] >= $exam['passing_score']): ?>
                                        <span class="badge bg-success">Réussi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Échoué</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Non passé</span>
                                <?php endif; ?>
                            </div>
                            <div class="exam-body">
                                <div class="exam-info">
                                    <div class="info-item">
                                        <span class="info-label">Formation</span>
                                        <span class="info-value"><?php echo htmlspecialchars($exam['formation_title']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Durée</span>
                                        <span class="info-value"><?php echo $exam['duration']; ?> minutes</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Score requis</span>
                                        <span class="info-value"><?php echo $exam['passing_score']; ?>%</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Date</span>
                                        <span class="info-value"><?php echo $exam['date'] ? date('d/m/Y', strtotime($exam['date'])) : 'Non définie'; ?></span>
                                    </div>
                                </div>
                                
                                <div class="exam-description">
                                    <?php echo htmlspecialchars($exam['description'] ?? 'Aucune description disponible.'); ?>
                                </div>
                                
                                <div class="exam-actions">
                                    <?php if ($exam['attempts'] > 0): ?>
                                        <a href="exam_results.php?id=<?php echo $exam['id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-chart-bar"></i> Voir les résultats
                                        </a>
                                    <?php else: ?>
                                        <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-pen"></i> Passer l'examen
                                        </a>
                                    <?php endif; ?>
                                    <a href="view_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun examen disponible pour votre section et fonction.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
