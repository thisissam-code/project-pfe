<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et a les droits appropriés
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

// Fonction pour générer un nouvel examen pour une formation donnée
function generateExam($conn, $formation_id, $pilot_id = null, $previous_exam_id = null) {
    // Récupérer les informations de la formation
    $query_formation = "SELECT title FROM formations WHERE id = ?";
    $stmt = $conn->prepare($query_formation);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Formation non trouvée.'];
    }
    
    $formation = $result->fetch_assoc();
    
    // Créer un nouvel examen
    $exam_title = "Examen - " . $formation['title'];
    if ($previous_exam_id) {
        $exam_title .= " (Reprise)";
    }
    
    $exam_description = "Examen de validation pour la formation " . $formation['title'];
    if ($pilot_id) {
        // Récupérer le nom du pilote
        $query_pilot = "SELECT first_name, last_name FROM users WHERE id = ?";
        $stmt = $conn->prepare($query_pilot);
        $stmt->bind_param("i", $pilot_id);
        $stmt->execute();
        $pilot_result = $stmt->get_result();
        
        if ($pilot_result->num_rows > 0) {
            $pilot = $pilot_result->fetch_assoc();
            $exam_description .= " pour " . $pilot['first_name'] . " " . $pilot['last_name'];
        }
    }
    
    $exam_duration = 60; // Durée par défaut en minutes
    $exam_passing_score = 70; // Score de passage par défaut
    $exam_date = date('Y-m-d'); // Date du jour
    
    $insert_exam_query = "INSERT INTO exams (formation_id, title, description, duration, passing_score, date) 
                         VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_exam_query);
    $stmt->bind_param("ississ", $formation_id, $exam_title, $exam_description, $exam_duration, $exam_passing_score, $exam_date);
    
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Erreur lors de la création de l\'examen: ' . $stmt->error];
    }
    
    $exam_id = $conn->insert_id;
    
    // Récupérer des questions pour cet examen
    // Si c'est une reprise, essayer d'éviter les questions du précédent examen
    $exclude_clause = "";
    if ($previous_exam_id) {
        $exclude_clause = " AND q.id NOT IN (
            SELECT eq.question_id 
            FROM exam_questions eq 
            WHERE eq.exam_id = $previous_exam_id
        )";
    }
    
    $query_questions = "SELECT q.id 
                       FROM questions q 
                       WHERE q.formation_id = ? $exclude_clause
                       ORDER BY RAND() 
                       LIMIT 10";
    $stmt = $conn->prepare($query_questions);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result_questions = $stmt->get_result();
    
    // Si pas assez de questions différentes, prendre des questions aléatoires
    if ($result_questions->num_rows < 5) {
        $query_questions = "SELECT q.id 
                           FROM questions q 
                           WHERE q.formation_id = ?
                           ORDER BY RAND() 
                           LIMIT 10";
        $stmt = $conn->prepare($query_questions);
        $stmt->bind_param("i", $formation_id);
        $stmt->execute();
        $result_questions = $stmt->get_result();
    }
    
    // Associer les questions à l'examen
    if ($result_questions->num_rows > 0) {
        $insert_exam_question = "INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_exam_question);
        
        $order = 1;
        while ($question = $result_questions->fetch_assoc()) {
            $stmt->bind_param("iii", $exam_id, $question['id'], $order);
            $stmt->execute();
            $order++;
        }
        
        // Si c'est une reprise pour un pilote spécifique, l'assigner à ce pilote
        if ($pilot_id) {
            $insert_pilot_exam = "INSERT INTO pilot_exams (pilot_id, exam_id, status, assigned_date) 
                                 VALUES (?, ?, 'assigned', NOW())";
            $stmt = $conn->prepare($insert_pilot_exam);
            $stmt->bind_param("ii", $pilot_id, $exam_id);
            $stmt->execute();
        }
        
        return [
            'success' => true, 
            'exam_id' => $exam_id, 
            'message' => 'Examen créé avec succès avec ' . $result_questions->num_rows . ' questions.'
        ];
    } else {
        // Supprimer l'examen créé car il n'y a pas de questions
        $conn->query("DELETE FROM exams WHERE id = $exam_id");
        return ['success' => false, 'message' => 'Aucune question disponible pour cette formation.'];
    }
}

// Traitement de la demande de génération d'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Paramètres manquants.'];
    
    if (isset($_POST['formation_id'])) {
        $formation_id = intval($_POST['formation_id']);
        $pilot_id = isset($_POST['pilot_id']) ? intval($_POST['pilot_id']) : null;
        $previous_exam_id = isset($_POST['previous_exam_id']) ? intval($_POST['previous_exam_id']) : null;
        
        $response = generateExam($conn, $formation_id, $pilot_id, $previous_exam_id);
    }
    
    // Si c'est une requête AJAX, renvoyer la réponse en JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Sinon, rediriger avec un message
    if ($response['success']) {
        header("Location: manage_exams.php?generated=1&exam_id=" . $response['exam_id']);
    } else {
        header("Location: manage_exams.php?error=" . urlencode($response['message']));
    }
    exit();
}

// Si la page est accédée directement, afficher un formulaire de génération d'examen
$is_admin = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'admin_sol' || $_SESSION['role'] === 'admin_vol');
if (!$is_admin) {
    header("Location: pilot_dashboard.php");
    exit();
}

// Récupérer la liste des formations
$query_formations = "SELECT id, title FROM formations ORDER BY title";
$result_formations = $conn->query($query_formations);

// Récupérer la liste des pilotes
$query_pilots = "SELECT id, first_name, last_name FROM users WHERE role = 'pilot' ORDER BY last_name, first_name";
$result_pilots = $conn->query($query_pilots);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générer un Examen - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../assets/alerts.js"></script>
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
            <h1 class="page-title">Générer un Examen</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="manage_exams.php" class="breadcrumb-link">Examens</a></li>
                <li class="breadcrumb-item">Générer un examen</li>
            </ul>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($_GET['error']); ?>');
                });
            </script>
        <?php endif; ?>

        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Générer un nouvel examen</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="formation_id" class="form-label">Formation</label>
                        <select id="formation_id" name="formation_id" class="form-select" required>
                            <option value="">Sélectionner une formation</option>
                            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                    <option value="<?php echo $formation['id']; ?>">
                                        <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pilot_id" class="form-label">Pilote (optionnel)</label>
                        <select id="pilot_id" name="pilot_id" class="form-select">
                            <option value="">Examen général (non assigné)</option>
                            <?php if ($result_pilots && $result_pilots->num_rows > 0): ?>
                                <?php while ($pilot = $result_pilots->fetch_assoc()): ?>
                                    <option value="<?php echo $pilot['id']; ?>">
                                        <?php echo htmlspecialchars($pilot['first_name'] . ' ' . $pilot['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Si vous sélectionnez un pilote, l'examen lui sera automatiquement assigné.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="previous_exam_id" class="form-label">Examen précédent (optionnel)</label>
                        <input type="number" id="previous_exam_id" name="previous_exam_id" class="form-control" placeholder="ID de l'examen précédent">
                        <small class="text-muted">Si c'est une reprise, indiquez l'ID de l'examen précédent pour éviter les mêmes questions.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Générer l'examen</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
