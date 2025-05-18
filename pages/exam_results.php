<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID d'examen est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pilot_exam.php");
    exit();
}

$exam_id = intval($_GET['id']);
$pilot_id = $_SESSION['user_id'];

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
    header("Location: pilot_exam.php");
    exit();
}

$exam = $result_exam->fetch_assoc();

// Récupérer le dernier résultat de cet examen pour ce pilote
$query_last = $conn->prepare("
    SELECT *
    FROM exam_results
    WHERE pilot_id = ? AND exam_id = ?
    ORDER BY date_taken DESC
    LIMIT 1
");
$query_last->bind_param("ii", $pilot_id, $exam_id);
$query_last->execute();
$result = $query_last->get_result();

if ($result->num_rows === 0) {
    header("Location: pilot_exam.php");
    exit();
}

$last_result = $result->fetch_assoc();

// Remplacer la requête qui utilise la table inexistante "exam_result_answers" par celle-ci:

// Récupérer le nombre de réponses correctes et incorrectes
$query_answers = $conn->prepare("
    SELECT 
        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
        SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as incorrect_answers
    FROM user_answers
    WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE user_id = ? AND exam_id = ?)
");
$query_answers->bind_param("ii", $pilot_id, $exam_id);
$query_answers->execute();
$answers_stats = $query_answers->get_result()->fetch_assoc();

$correct_answers = $answers_stats['correct_answers'] ?? 0;
$incorrect_answers = $answers_stats['incorrect_answers'] ?? 0;
$total_questions = $correct_answers + $incorrect_answers;

// Enregistrer la consultation dans les logs
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_exam_results', CONCAT('Consultation des résultats de l\'examen #', ?), NOW())
");
$log_query->bind_param("ii", $pilot_id, $exam_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats d'Examen - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .results-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .results-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .results-title {
            font-size: 1.5rem;
            color: #0a3d91;
            margin-bottom: 5px;
        }
        
        .results-subtitle {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .results-body {
            padding: 30px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .stat-correct {
            border-top: 4px solid #28a745;
        }
        
        .stat-incorrect {
            border-top: 4px solid #dc3545;
        }
        
        .stat-score {
            border-top: 4px solid #0a3d91;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-back {
            padding: 10px 20px;
            font-size: 1rem;
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
            <h1 class="page-title">Résultats d'Examen</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="pilot_exam.php" class="breadcrumb-link">Examens</a></li>
                <li class="breadcrumb-item">Résultats</li>
            </ul>
        </div>

        <div class="results-card">
            <div class="results-header">
                <h2 class="results-title"><?php echo htmlspecialchars($exam['title']); ?></h2>
                <p class="results-subtitle">Formation: <?php echo htmlspecialchars($exam['formation_title']); ?></p>
            </div>
            
            <div class="results-body">
                <div class="stats-container">
                    <div class="stat-card stat-correct">
                        <div class="stat-value"><?php echo $correct_answers; ?></div>
                        <div class="stat-label">Réponses correctes</div>
                    </div>
                    <div class="stat-card stat-incorrect">
                        <div class="stat-value"><?php echo $incorrect_answers; ?></div>
                        <div class="stat-label">Réponses incorrectes</div>
                    </div>
                    <div class="stat-card stat-score">
                        <div class="stat-value"><?php echo round($last_result['score'], 1); ?>%</div>
                        <div class="stat-label">Score total</div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="pilot_exam.php" class="btn btn-primary btn-back">
                        <i class="fas fa-arrow-left"></i> Retour aux examens
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
