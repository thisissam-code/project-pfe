<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'admin_sol', 'admin_vol', 'instructor'])) {
    header("Location: ../login.php");
    exit();
}

// Déterminer la section en fonction du rôle de l'utilisateur
$user_role = $_SESSION['role'];
$fixed_section = '';
$can_change_section = true;

if ($user_role === 'admin_sol') {
    $fixed_section = 'sol';
    $can_change_section = false;
} elseif ($user_role === 'admin_vol') {
    $fixed_section = 'vol';
    $can_change_section = false;
}

// Initialiser les variables
$selected_formation = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
$selected_pilot = isset($_GET['pilot_id']) ? intval($_GET['pilot_id']) : 0;
$selected_result = isset($_GET['result_id']) ? intval($_GET['result_id']) : 0;

// Si l'utilisateur a une section fixe, utiliser cette section, sinon utiliser celle de l'URL
$selected_section = $fixed_section ? $fixed_section : (isset($_GET['section']) ? $_GET['section'] : '');

// Récupérer toutes les formations pour le filtre
$query_formations = "SELECT id, title FROM formations ORDER BY title";
$result_formations = $conn->query($query_formations);

// Construire la requête pour récupérer les pilotes
$query_pilots = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.section, u.fonction
                FROM users u
                JOIN exam_results er ON u.id = er.pilot_id
                JOIN exams e ON er.exam_id = e.id
                JOIN formations f ON e.formation_id = f.id
                WHERE u.role = 'pilot'";

// Ajouter le filtre par formation si sélectionné
if ($selected_formation > 0) {
    $query_pilots .= " AND e.formation_id = $selected_formation";
}

// Ajouter le filtre par section si sélectionné ou fixé
if ($selected_section === 'sol' || $selected_section === 'vol') {
    $query_pilots .= " AND (u.section = '" . $conn->real_escape_string($selected_section) . "' OR f.section = '" . $conn->real_escape_string($selected_section) . "')";
}

$query_pilots .= " ORDER BY u.last_name, u.first_name";
$result_pilots = $conn->query($query_pilots);

// Si un pilote est sélectionné, récupérer ses résultats d'examens
$pilot_results = [];
if ($selected_pilot > 0) {
    $query_results = "SELECT er.id, er.exam_id, er.score, er.status, er.date_taken, 
                      e.title as exam_title, f.id as formation_id, f.title as formation_title, f.section
                      FROM exam_results er
                      JOIN exams e ON er.exam_id = e.id
                      JOIN formations f ON e.formation_id = f.id
                      WHERE er.pilot_id = $selected_pilot";
    
    // Ajouter le filtre par formation si sélectionné
    if ($selected_formation > 0) {
        $query_results .= " AND f.id = $selected_formation";
    }
    
    // Ajouter le filtre par section si sélectionné ou fixé
    if ($selected_section === 'sol' || $selected_section === 'vol') {
        $query_results .= " AND (f.section = '" . $conn->real_escape_string($selected_section) . "')";
    }
    
    $query_results .= " ORDER BY er.date_taken DESC";
    $result_results = $conn->query($query_results);
    
    if ($result_results) {
        while ($row = $result_results->fetch_assoc()) {
            $pilot_results[] = $row;
        }
    }
    
    // Récupérer les informations du pilote sélectionné
    $query_pilot_info = "SELECT first_name, last_name, email, section, fonction FROM users WHERE id = $selected_pilot";
    $result_pilot_info = $conn->query($query_pilot_info);
    $pilot_info = $result_pilot_info->fetch_assoc();
}

// Si un résultat d'examen est sélectionné, récupérer les détails des réponses
$exam_details = [];
$user_answers = [];
if ($selected_result > 0) {
    // Récupérer les détails de l'examen
    $query_exam_details = "SELECT er.id, er.exam_id, er.pilot_id, er.score, er.status, er.date_taken, 
                          e.title as exam_title, e.passing_score, f.title as formation_title, f.section,
                          u.first_name, u.last_name
                          FROM exam_results er
                          JOIN exams e ON er.exam_id = e.id
                          JOIN formations f ON e.formation_id = f.id
                          JOIN users u ON er.pilot_id = u.id
                          WHERE er.id = $selected_result";
    
    // Ajouter le filtre par section si fixé
    if ($fixed_section) {
        $query_exam_details .= " AND f.section = '" . $conn->real_escape_string($fixed_section) . "'";
    }
    
    $result_exam_details = $conn->query($query_exam_details);
    
    if ($result_exam_details && $result_exam_details->num_rows > 0) {
        $exam_details = $result_exam_details->fetch_assoc();
        
        // Récupérer les réponses de l'utilisateur
        $query_user_answers = "SELECT ua.id, ua.question_id, ua.answer_id, ua.is_correct, 
                              q.question_text, a.answer_text,
                              (SELECT answer_text FROM answers WHERE question_id = q.id AND is_correct = 1 LIMIT 1) as correct_answer_text
                              FROM user_answers ua
                              JOIN questions q ON ua.question_id = q.id
                              JOIN answers a ON ua.answer_id = a.id
                              JOIN exam_attempts ea ON ua.attempt_id = ea.id
                              WHERE ea.user_id = " . $exam_details['pilot_id'] . " 
                              AND ea.exam_id = " . $exam_details['exam_id'] . "
                              ORDER BY ua.id";
        $result_user_answers = $conn->query($query_user_answers);
        
        if ($result_user_answers) {
            while ($row = $result_user_answers->fetch_assoc()) {
                $user_answers[] = $row;
            }
        }
    } else {
        // Rediriger si le résultat n'existe pas ou n'est pas dans la section autorisée
        header("Location: manage_exam_results.php" . ($selected_pilot > 0 ? "?pilot_id=" . $selected_pilot : ""));
        exit();
    }
}

// Fonction pour construire les URL avec les paramètres actuels
function buildUrl($params = []) {
    global $selected_formation, $selected_pilot, $selected_result, $selected_section, $fixed_section;
    
    $url_params = [];
    
    if (isset($params['formation_id'])) {
        $url_params['formation_id'] = $params['formation_id'];
    } elseif ($selected_formation > 0) {
        $url_params['formation_id'] = $selected_formation;
    }
    
    // N'ajouter le paramètre section que s'il n'est pas fixé
    if (!$fixed_section) {
        if (isset($params['section'])) {
            $url_params['section'] = $params['section'];
        } elseif ($selected_section !== '') {
            $url_params['section'] = $selected_section;
        }
    }
    
    if (isset($params['pilot_id'])) {
        $url_params['pilot_id'] = $params['pilot_id'];
    } elseif ($selected_pilot > 0 && !isset($params['reset_pilot'])) {
        $url_params['pilot_id'] = $selected_pilot;
    }
    
    if (isset($params['result_id'])) {
        $url_params['result_id'] = $params['result_id'];
    } elseif ($selected_result > 0 && !isset($params['reset_result'])) {
        $url_params['result_id'] = $selected_result;
    }
    
    $url = 'manage_exam_results.php';
    if (!empty($url_params)) {
        $url .= '?' . http_build_query($url_params);
    }
    
    return $url;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Résultats d'Examens - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-label {
            font-weight: 500;
            white-space: nowrap;
        }
        
        .filter-select {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            min-width: 200px;
        }
        
        .section-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .section-sol {
            background-color: #e6f7ff;
            color: #0066cc;
        }
        
        .section-vol {
            background-color: #fff0e6;
            color: #ff6600;
        }
        
        .fixed-section-notice {
            background-color: #f8f9fa;
            border-left: 4px solid #0a3d91;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #495057;
        }
        
        .pilot-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        
        .pilot-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .pilot-card.active {
            border-left-color: #0a3d91;
            background-color: #f8f9fa;
        }
        
        .pilot-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #0a3d91;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .pilot-info {
            flex: 1;
        }
        
        .pilot-name {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .pilot-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .result-card {
            padding: 15px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
            border-left: 4px solid transparent;
        }
        
        .result-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .result-card.active {
            border-left-color: #0a3d91;
            background-color: #f8f9fa;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .result-title {
            font-weight: 500;
        }
        
        .result-score {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .score-passed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .score-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .result-meta {
            display: flex;
            gap: 15px;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .result-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .pilot-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .pilot-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .pilot-details-title {
            font-size: 1.2rem;
            font-weight: 500;
            color: #0a3d91;
        }
        
        .pilot-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .pilot-details-item {
            display: flex;
            flex-direction: column;
        }
        
        .pilot-details-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .pilot-details-value {
            font-weight: 500;
        }
        
        .exam-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .exam-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .exam-details-title {
            font-size: 1.2rem;
            font-weight: 500;
            color: #0a3d91;
        }
        
        .exam-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .exam-details-item {
            display: flex;
            flex-direction: column;
        }
        
        .exam-details-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .exam-details-value {
            font-weight: 500;
        }
        
        .answer-item {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .answer-question {
            font-weight: 500;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .answer-response {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .answer-text {
            flex: 1;
        }
        
        .answer-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .answer-correct {
            background-color: #d4edda;
            color: #155724;
        }
        
        .answer-incorrect {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .correct-answer {
            font-size: 0.85rem;
            color: #155724;
            background-color: #d4edda;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #0a3d91;
            text-decoration: none;
            margin-bottom: 15px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #0a3d91;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
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
        
        .total-exams {
            border-top: 4px solid #0a3d91;
        }
        
        @media (max-width: 768px) {
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-item {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
        }
    </style>
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
            <h1 class="page-title">Gestion des Résultats d'Examens</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Résultats d'examens</li>
                <?php if ($fixed_section): ?>
                    <li class="breadcrumb-item">Section <?php echo ucfirst($fixed_section); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($fixed_section): ?>
            <div class="fixed-section-notice">
                <i class="fas fa-info-circle"></i> 
                Vous consultez uniquement les résultats d'examens de la section <strong><?php echo ucfirst($fixed_section); ?></strong> conformément à votre rôle.
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filter-section">
            <div class="filter-item">
                <span class="filter-label">Formation:</span>
                <select class="filter-select" id="formation-filter" onchange="applyFilters()">
                    <option value="0">Toutes les formations</option>
                    <?php while ($formation = $result_formations->fetch_assoc()): ?>
                        <option value="<?php echo $formation['id']; ?>" <?php echo $selected_formation == $formation['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($formation['title']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <?php if ($can_change_section): ?>
            <div class="filter-item">
                <span class="filter-label">Section:</span>
                <select class="filter-select" id="section-filter" onchange="applyFilters()">
                    <option value="">Toutes les sections</option>
                    <option value="sol" <?php echo $selected_section === 'sol' ? 'selected' : ''; ?>>Sol</option>
                    <option value="vol" <?php echo $selected_section === 'vol' ? 'selected' : ''; ?>>Vol</option>
                </select>
            </div>
            <?php endif; ?>
            
            <?php if ($selected_pilot > 0 || (!$fixed_section && $selected_section !== '') || $selected_formation > 0): ?>
                <div class="filter-item">
                    <a href="<?php echo $fixed_section ? 'manage_exam_results.php' : 'manage_exam_results.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Réinitialiser les filtres
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($selected_result > 0 && !empty($exam_details)): ?>
            <!-- Détails d'un résultat d'examen spécifique -->
            <a href="<?php echo buildUrl(['reset_result' => true]); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour aux résultats
            </a>
            
            <div class="exam-details">
                <div class="exam-details-header">
                    <h2 class="exam-details-title">Détails de l'examen</h2>
                </div>
                
                <div class="exam-details-grid">
                    <div class="exam-details-item">
                        <span class="exam-details-label">Pilote</span>
                        <span class="exam-details-value"><?php echo htmlspecialchars($exam_details['first_name'] . ' ' . $exam_details['last_name']); ?></span>
                    </div>
                    <div class="exam-details-item">
                        <span class="exam-details-label">Examen</span>
                        <span class="exam-details-value"><?php echo htmlspecialchars($exam_details['exam_title']); ?></span>
                    </div>
                    <div class="exam-details-item">
                        <span class="exam-details-label">Formation</span>
                        <span class="exam-details-value">
                            <?php echo htmlspecialchars($exam_details['formation_title']); ?>
                            <?php if ($exam_details['section']): ?>
                                <span class="section-badge section-<?php echo strtolower($exam_details['section']); ?>">
                                    <?php echo ucfirst($exam_details['section']); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="exam-details-item">
                        <span class="exam-details-label">Date</span>
                        <span class="exam-details-value"><?php echo date('d/m/Y H:i', strtotime($exam_details['date_taken'])); ?></span>
                    </div>
                    <div class="exam-details-item">
                        <span class="exam-details-label">Score</span>
                        <span class="exam-details-value"><?php echo $exam_details['score']; ?>% (Minimum requis: <?php echo $exam_details['passing_score']; ?>%)</span>
                    </div>
                    <div class="exam-details-item">
                        <span class="exam-details-label">Statut</span>
                        <span class="exam-details-value">
                            <?php if ($exam_details['status'] === 'passed'): ?>
                                <span class="badge bg-success">Réussi</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Échoué</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <h3>Réponses du pilote</h3>
            
            <?php if (count($user_answers) > 0): ?>
                <?php foreach ($user_answers as $index => $answer): ?>
                    <div class="answer-item">
                        <div class="answer-question">
                            <span class="question-number"><?php echo $index + 1; ?>.</span>
                            <?php echo htmlspecialchars($answer['question_text']); ?>
                        </div>
                        <div class="answer-response">
                            <div class="answer-text">
                                <strong>Réponse du pilote:</strong> <?php echo htmlspecialchars($answer['answer_text']); ?>
                            </div>
                            <span class="answer-status <?php echo $answer['is_correct'] ? 'answer-correct' : 'answer-incorrect'; ?>">
                                <?php echo $answer['is_correct'] ? 'Correcte' : 'Incorrecte'; ?>
                            </span>
                        </div>
                        <?php if (!$answer['is_correct']): ?>
                            <div class="correct-answer">
                                <strong>Réponse correcte:</strong> <?php echo htmlspecialchars($answer['correct_answer_text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i> Aucune réponse disponible pour cet examen.
                </div>
            <?php endif; ?>
            
        <?php elseif ($selected_pilot > 0): ?>
            <!-- Résultats d'examens pour un pilote spécifique -->
            <a href="<?php echo buildUrl(['reset_pilot' => true]); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour à la liste des pilotes
            </a>
            
            <div class="pilot-details">
                <div class="pilot-details-header">
                    <h2 class="pilot-details-title">Détails du pilote</h2>
                </div>
                
                <div class="pilot-details-grid">
                    <div class="pilot-details-item">
                        <span class="pilot-details-label">Nom</span>
                        <span class="pilot-details-value"><?php echo htmlspecialchars($pilot_info['first_name'] . ' ' . $pilot_info['last_name']); ?></span>
                    </div>
                    <div class="pilot-details-item">
                        <span class="pilot-details-label">Email</span>
                        <span class="pilot-details-value"><?php echo htmlspecialchars($pilot_info['email']); ?></span>
                    </div>
                    <div class="pilot-details-item">
                        <span class="pilot-details-label">Section</span>
                        <span class="pilot-details-value">
                            <?php if ($pilot_info['section']): ?>
                                <?php echo htmlspecialchars(ucfirst($pilot_info['section'])); ?>
                                <span class="section-badge section-<?php echo strtolower($pilot_info['section']); ?>">
                                    <?php echo ucfirst($pilot_info['section']); ?>
                                </span>
                            <?php else: ?>
                                Non spécifiée
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="pilot-details-item">
                        <span class="pilot-details-label">Fonction</span>
                        <span class="pilot-details-value"><?php echo htmlspecialchars($pilot_info['fonction'] ?? 'Non spécifiée'); ?></span>
                    </div>
                </div>
            </div>
            
            <?php
            // Calculer les statistiques
            $total_exams = count($pilot_results);
            $passed_exams = 0;
            $failed_exams = 0;
            $total_score = 0;
            
            foreach ($pilot_results as $result) {
                if ($result['status'] === 'passed') {
                    $passed_exams++;
                } else {
                    $failed_exams++;
                }
                $total_score += $result['score'];
            }
            
            $average_score = $total_exams > 0 ? round($total_score / $total_exams, 1) : 0;
            ?>
            
            <div class="stats-grid">
                <div class="stat-card total-exams">
                    <div class="stat-value"><?php echo $total_exams; ?></div>
                    <div class="stat-label">Examens passés</div>
                </div>
                <div class="stat-card passed-exams">
                    <div class="stat-value"><?php echo $passed_exams; ?></div>
                    <div class="stat-label">Examens réussis</div>
                </div>
                <div class="stat-card failed-exams">
                    <div class="stat-value"><?php echo $failed_exams; ?></div>
                    <div class="stat-label">Examens échoués</div>
                </div>
                <div class="stat-card average-score">
                    <div class="stat-value"><?php echo $average_score; ?>%</div>
                    <div class="stat-label">Score moyen</div>
                </div>
            </div>
            
            <h3>Résultats d'examens<?php echo $fixed_section ? ' - Section ' . ucfirst($fixed_section) : ''; ?></h3>
            
            <?php if (count($pilot_results) > 0): ?>
                <?php foreach ($pilot_results as $result): ?>
                    <div class="result-card <?php echo $selected_result == $result['id'] ? 'active' : ''; ?>" onclick="window.location.href='<?php echo buildUrl(['result_id' => $result['id']]); ?>'">
                        <div class="result-header">
                            <div class="result-title">
                                <?php echo htmlspecialchars($result['exam_title']); ?>
                                <?php if ($result['section']): ?>
                                    <span class="section-badge section-<?php echo strtolower($result['section']); ?>">
                                        <?php echo ucfirst($result['section']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="result-score <?php echo $result['status'] === 'passed' ? 'score-passed' : 'score-failed'; ?>">
                                <?php echo $result['score']; ?>%
                            </div>
                        </div>
                        <div class="result-meta">
                            <div class="result-meta-item">
                                <i class="fas fa-graduation-cap"></i>
                                <?php echo htmlspecialchars($result['formation_title']); ?>
                            </div>
                            <div class="result-meta-item">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y H:i', strtotime($result['date_taken'])); ?>
                            </div>
                            <div class="result-meta-item">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $result['status'] === 'passed' ? 'Réussi' : 'Échoué'; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i> Aucun résultat d'examen trouvé pour ce pilote<?php echo $fixed_section ? ' dans la section ' . ucfirst($fixed_section) : ''; ?>.
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Liste des pilotes -->
            <h3>Pilotes ayant passé des examens<?php echo $fixed_section ? ' - Section ' . ucfirst($fixed_section) : ''; ?></h3>
            
            <?php if ($result_pilots && $result_pilots->num_rows > 0): ?>
                <?php while ($pilot = $result_pilots->fetch_assoc()): ?>
                    <div class="pilot-card <?php echo $selected_pilot == $pilot['id'] ? 'active' : ''; ?>" onclick="window.location.href='<?php echo buildUrl(['pilot_id' => $pilot['id']]); ?>'">
                        <div class="pilot-avatar">
                            <?php echo strtoupper(substr($pilot['first_name'], 0, 1)); ?>
                        </div>
                        <div class="pilot-info">
                            <div class="pilot-name">
                                <?php echo htmlspecialchars($pilot['first_name'] . ' ' . $pilot['last_name']); ?>
                                <?php if ($pilot['section']): ?>
                                    <span class="section-badge section-<?php echo strtolower($pilot['section']); ?>">
                                        <?php echo ucfirst($pilot['section']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="pilot-meta">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($pilot['email']); ?></span>
                                <?php if ($pilot['fonction']): ?>
                                    <span><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($pilot['fonction']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i> Aucun pilote n'a passé d'examen<?php echo $fixed_section ? ' dans la section ' . ucfirst($fixed_section) : ($selected_section ? ' dans la section ' . ucfirst($selected_section) : ''); ?>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        function applyFilters() {
            const formationId = document.getElementById('formation-filter').value;
            <?php if ($can_change_section): ?>
            const section = document.getElementById('section-filter').value;
            <?php else: ?>
            const section = '<?php echo $fixed_section; ?>';
            <?php endif; ?>
        
            let url = 'manage_exam_results.php';
            let params = [];
        
            if (formationId > 0) {
                params.push('formation_id=' + formationId);
            }
        
            <?php if ($can_change_section): ?>
            if (section) {
                params.push('section=' + section);
            }
            <?php endif; ?>
        
            <?php if ($selected_pilot > 0): ?>
                params.push('pilot_id=<?php echo $selected_pilot; ?>');
            
                <?php if ($selected_result > 0): ?>
                    params.push('result_id=<?php echo $selected_result; ?>');
                <?php endif; ?>
            <?php endif; ?>
        
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
        
            window.location.href = url;
        }
    </script>
</body>
</html>
