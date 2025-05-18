<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_vol') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

// Vérifier si l'ID de la question est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ajouter_question_vol.php");
    exit();
}

$question_id = intval($_GET['id']);

// Récupération des formations
$sql_formations = "SELECT id, title FROM formations ORDER BY title";
$result_formations = $conn->query($sql_formations);

// Récupération des détails de la question
$sql_question = "SELECT q.*, f.title as formation_title 
                FROM questions q 
                JOIN formations f ON q.formation_id = f.id 
                WHERE q.id = ?";
$stmt = $conn->prepare($sql_question);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result_question = $stmt->get_result();

if ($result_question->num_rows === 0) {
    header("Location: ajouter_question_vol.php");
    exit();
}

$question = $result_question->fetch_assoc();
$stmt->close();

// Récupération des réponses
$sql_answers = "SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY id";
$stmt = $conn->prepare($sql_answers);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result_answers = $stmt->get_result();
$answers = [];
$correct_answer_index = -1;

$i = 0;
while ($answer = $result_answers->fetch_assoc()) {
    $answers[] = $answer;
    if ($answer['is_correct'] == 1) {
        $correct_answer_index = $i;
    }
    $i++;
}
$stmt->close();

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_question'])) {
    $formation_id = $_POST['formation_id'];
    $question_text = trim($_POST['question_text']);
    $answer_texts = $_POST['answers'];
    $answer_ids = $_POST['answer_ids'];
    $correct_answer = isset($_POST['correct_answer']) ? intval($_POST['correct_answer']) : -1;

    // Validation des données
    if (empty($formation_id) || empty($question_text) || count($answer_texts) != 4 || $correct_answer < 0 || $correct_answer > 3) {
        $error_message = "Veuillez remplir tous les champs et sélectionner une réponse correcte.";
    } else {
        // Vérifier que toutes les réponses sont remplies
        $all_answers_filled = true;
        foreach ($answer_texts as $answer) {
            if (trim($answer) === '') {
                $all_answers_filled = false;
                break;
            }
        }

        if (!$all_answers_filled) {
            $error_message = "Veuillez remplir toutes les réponses.";
        } else {
            // Mettre à jour la question
            $stmt = $conn->prepare("UPDATE questions SET formation_id = ?, question_text = ? WHERE id = ?");
            $stmt->bind_param("isi", $formation_id, $question_text, $question_id);
            $stmt->execute();
            $stmt->close();

            // Mettre à jour les réponses
            $stmt = $conn->prepare("UPDATE answers SET answer_text = ?, is_correct = ? WHERE id = ?");
            foreach ($answer_texts as $index => $answer_text) {
                $is_correct = ($index == $correct_answer) ? 1 : 0;
                $answer_id = $answer_ids[$index];
                $stmt->bind_param("sii", $answer_text, $is_correct, $answer_id);
                $stmt->execute();
            }
            $stmt->close();
            
            // Rediriger avec un message de succès
            header("Location: ajouter_question_vol.php?updated=1");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier une Question - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .answer-container {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
        
        .answer-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .answer-row:last-child {
            margin-bottom: 0;
        }
        
        .answer-input {
            flex: 1;
            margin-right: 10px;
        }
        
        .answer-radio {
            margin-right: 5px;
        }
        
        .answer-label {
            display: flex;
            align-items: center;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin_vol_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Modifier une Question</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard_vol.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="ajouter_question_vol.php" class="breadcrumb-link">Gestion des questions</a></li>
                <li class="breadcrumb-item">Modifier une question</li>
            </ul>
        </div>

        <?php if (isset($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($error_message); ?>');
                });
            </script>
        <?php endif; ?>

        <!-- Edit Question Form -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Modifier la question #<?php echo $question_id; ?></h2>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="formation_id" class="form-label">Formation</label>
                        <select id="formation_id" name="formation_id" class="form-select" required>
                            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo ($formation['id'] == $question['formation_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="question_text" class="form-label">Question</label>
                        <textarea id="question_text" name="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Réponses (sélectionnez une seule réponse correcte)</label>
                        <div class="answer-container">
                            <?php for ($i = 0; $i < count($answers); $i++): ?>
                                <div class="answer-row">
                                    <input type="hidden" name="answer_ids[]" value="<?php echo $answers[$i]['id']; ?>">
                                    <input type="text" name="answers[]" class="form-control answer-input" value="<?php echo htmlspecialchars($answers[$i]['answer_text']); ?>" required>
                                    <label class="answer-label">
                                        <input type="radio" name="correct_answer" value="<?php echo $i; ?>" class="answer-radio" <?php echo ($i == $correct_answer_index) ? 'checked' : ''; ?> required>
                                        Correcte
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="submit" name="update_question" class="btn btn-primary">Mettre à jour</button>
                        <a href="ajouter_question_vol.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
