<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Vérifier si un ID d'examen est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: instructor_formations.php");
    exit();
}

$exam_id = intval($_GET['id']);

// Récupérer les détails de l'examen
$query_exam = $conn->prepare("
    SELECT e.*, f.title as formation_title, f.id as formation_id
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE e.id = ?
");
$query_exam->bind_param("i", $exam_id);
$query_exam->execute();
$result_exam = $query_exam->get_result();

if ($result_exam->num_rows === 0) {
    header("Location: instructor_formations.php");
    exit();
}

$exam = $result_exam->fetch_assoc();

// Vérifier si l'instructeur est assigné à la formation de cet examen
$query_check = $conn->prepare("
    SELECT COUNT(*) as count
    FROM formation_instructors
    WHERE formation_id = ? AND instructor_id = ?
");
$query_check->bind_param("ii", $exam['formation_id'], $instructor_id);
$query_check->execute();
$is_assigned = $query_check->get_result()->fetch_assoc()['count'] > 0;

if (!$is_assigned) {
    header("Location: instructor_formations.php");
    exit();
}

// Récupérer les questions liées à la formation de cet examen
$query_questions = $conn->prepare("
    SELECT q.id, q.question_text, q.type
    FROM questions q
    WHERE q.formation_id = ?
    ORDER BY q.id
");
$query_questions->bind_param("i", $exam['formation_id']);
$query_questions->execute();
$result_questions = $query_questions->get_result();

// Récupérer les résultats des pilotes pour cet examen
$query_results = $conn->prepare("
    SELECT er.id, er.pilot_id, er.score, er.status, er.date_taken,
           u.first_name, u.last_name, u.email
    FROM exam_results er
    JOIN users u ON er.pilot_id = u.id
    WHERE er.exam_id = ?
    ORDER BY er.date_taken DESC
");
$query_results->bind_param("i", $exam_id);
$query_results->execute();
$result_results = $query_results->get_result();

// Calculer les statistiques de l'examen
$query_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_attempts,
        SUM(CASE WHEN status = 'passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        AVG(score) as average_score
    FROM exam_results
    WHERE exam_id = ?
");
$query_stats->bind_param("i", $exam_id);
$query_stats->execute();
$stats = $query_stats->get_result()->fetch_assoc();

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_exam_details', CONCAT('Consultation des détails de l\'examen #', ?), NOW())
");
$log_query->bind_param("ii", $instructor_id, $exam_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de l'Examen - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .exam-header {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0a3d91;
        }
        
        .exam-title {
            font-size: 1.8rem;
            color: #0a3d91;
            margin-bottom: 10px;
        }
        
        .exam-subtitle {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        .exam-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
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
        
        .attempts-card {
            border-top: 4px solid #0a3d91;
        }
        
        .passed-card {
            border-top: 4px solid #28a745;
        }
        
        .failed-card {
            border-top: 4px solid #dc3545;
        }
        
        .average-card {
            border-top: 4px solid #ffc107;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: #343a40;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .question-card {
            background-color: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .question-header {
            padding: 15px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .question-number {
            font-weight: bold;
            color: #0a3d91;
        }
        
        .question-body {
            padding: 15px;
        }
        
        .question-text {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .answers-list {
            list-style-type: none;
            padding: 0;
        }
        
        .answer-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
        }
        
        .answer-item.correct {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .answer-marker {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: 500;
        }
        
        .answer-item.correct .answer-marker {
            background-color: #28a745;
            color: white;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .results-table th, .results-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .results-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #343a40;
        }
        
        .results-table tr:hover {
            background-color: #f8f9fa;
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
            <h1 class="page-title">Détails de l'Examen</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="instructor_formations.php" class="breadcrumb-link">Formations</a></li>
                <li class="breadcrumb-item"><a href="instructor_formation_details.php?id=<?php echo $exam['formation_id']; ?>" class="breadcrumb-link">Détails de la formation</a></li>
                <li class="breadcrumb-item">Détails de l'examen</li>
            </ul>
        </div>

        <!-- Exam Header -->
        <div class="exam-header">
            <h2 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h2>
            <p class="exam-subtitle">Formation: <?php echo htmlspecialchars($exam['formation_title']); ?></p>
            <div class="exam-meta">
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span>Durée: <?php echo $exam['duration']; ?> minutes</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-percentage"></i>
                    <span>Score minimum: <?php echo $exam['passing_score']; ?>%</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Date: <?php echo $exam['date'] ? date('d/m/Y', strtotime($exam['date'])) : 'Non définie'; ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Créé le: <?php echo date('d/m/Y', strtotime($exam['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card attempts-card">
                <i class="fas fa-users fa-2x"></i>
                <div class="stat-value"><?php echo $stats['total_attempts'] ?? 0; ?></div>
                <div class="stat-label">Tentatives totales</div>
            </div>
            <div class="stat-card passed-card">
                <i class="fas fa-check-circle fa-2x"></i>
                <div class="stat-value"><?php echo $stats['passed_count'] ?? 0; ?></div>
                <div class="stat-label">Réussites</div>
            </div>
            <div class="stat-card failed-card">
                <i class="fas fa-times-circle fa-2x"></i>
                <div class="stat-value"><?php echo $stats['failed_count'] ?? 0; ?></div>
                <div class="stat-label">Échecs</div>
            </div>
            <div class="stat-card average-card">
                <i class="fas fa-chart-line fa-2x"></i>
                <div class="stat-value"><?php echo round($stats['average_score'] ?? 0, 1); ?>%</div>
                <div class="stat-label">Score moyen</div>
            </div>
        </div>

        <!-- Questions Section -->
        <h3 class="section-title"><i class="fas fa-question-circle"></i> Questions de l'examen</h3>
        <?php if ($result_questions && $result_questions->num_rows > 0): ?>
            <?php $question_number = 1; ?>
            <?php while ($question = $result_questions->fetch_assoc()): ?>
                <div class="question-card">
                    <div class="question-header">
                        <span class="question-number">Question <?php echo $question_number; ?></span>
                    </div>
                    <div class="question-body">
                        <div class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <?php
                        // Récupérer les réponses pour cette question
                        $query_answers = $conn->prepare("
                            SELECT id, answer_text, is_correct
                            FROM answers
                            WHERE question_id = ?
                            ORDER BY id
                        ");
                        $query_answers->bind_param("i", $question['id']);
                        $query_answers->execute();
                        $result_answers = $query_answers->get_result();
                        ?>
                        
                        <ul class="answers-list">
                            <?php $answer_letter = 'A'; ?>
                            <?php while ($answer = $result_answers->fetch_assoc()): ?>
                                <li class="answer-item <?php echo $answer['is_correct'] ? 'correct' : ''; ?>">
                                    <span class="answer-marker"><?php echo $answer_letter++; ?></span>
                                    <span class="answer-text"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                    <?php if ($answer['is_correct']): ?>
                                        <span class="answer-correct-marker ml-auto">
                                            <i class="fas fa-check-circle text-success"></i>
                                        </span>
                                    <?php endif; ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                </div>
                <?php $question_number++; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-question-circle"></i>
                <p>Aucune question n'est associée à cette formation.</p>
            </div>
        <?php endif; ?>

        <!-- Results Section -->
        <h3 class="section-title"><i class="fas fa-chart-bar"></i> Résultats des pilotes</h3>
        <?php if ($result_results && $result_results->num_rows > 0): ?>
            <div class="table-container">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Pilote</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($result = $result_results->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($result['email']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($result['date_taken'])); ?></td>
                                <td><?php echo round($result['score'], 1); ?>%</td>
                                <td>
                                    <?php if ($result['status'] === 'passed'): ?>
                                        <span class="badge bg-success">Réussi</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Échoué</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="instructor_view_result.php?id=<?php echo $result['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>Aucun pilote n'a encore passé cet examen.</p>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="instructor_formation_details.php?id=<?php echo $exam['formation_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à la formation
            </a>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
