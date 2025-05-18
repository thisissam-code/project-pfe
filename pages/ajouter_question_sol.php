<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_sol') {
    header("Location: ../index.php"); // Redirige vers la page d'accueil si l'utilisateur n'est pas admin
    exit();
}

// Récupération des formations de la section SOL uniquement
$sql_formations = "SELECT id, title FROM formations WHERE section = 'sol' ORDER BY title";
$result_formations = $conn->query($sql_formations);

// Récupération des questions existantes avec pagination
$questions_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $questions_per_page;

// Compter le nombre total de questions
$count_query = "SELECT COUNT(*) as total FROM questions q JOIN formations f ON q.formation_id = f.id WHERE f.section = 'sol'";
$count_result = $conn->query($count_query);
$total_questions = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_questions / $questions_per_page);

// Récupérer les questions pour la page actuelle (uniquement pour les formations SOL)
$sql_questions = "SELECT q.id, q.question_text, f.title, f.id as formation_id 
                 FROM questions q 
                 JOIN formations f ON q.formation_id = f.id 
                 WHERE f.section = 'sol'
                 ORDER BY f.title, q.id DESC 
                 LIMIT $offset, $questions_per_page";
$result_questions = $conn->query($sql_questions);

// Filtrer par formation si demandé
$filter_formation = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
if ($filter_formation > 0) {
    $sql_questions = "SELECT q.id, q.question_text, f.title, f.id as formation_id 
                     FROM questions q 
                     JOIN formations f ON q.formation_id = f.id 
                     WHERE f.id = $filter_formation AND f.section = 'sol'
                     ORDER BY q.id DESC 
                     LIMIT $offset, $questions_per_page";
    $result_questions = $conn->query($sql_questions);
    
    // Recalculer la pagination
    $count_query = "SELECT COUNT(*) as total FROM questions q JOIN formations f ON q.formation_id = f.id WHERE q.formation_id = $filter_formation AND f.section = 'sol'";
    $count_result = $conn->query($count_query);
    $total_questions = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_questions / $questions_per_page);
}

// Traitement du formulaire d'ajout de question
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_question'])) {
    $formation_id = $_POST['formation_id'];
    
    // Vérifier que la formation appartient bien à la section SOL
    $check_formation = "SELECT id FROM formations WHERE id = ? AND section = 'sol'";
    $stmt = $conn->prepare($check_formation);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Formation non autorisée.";
    } else {
        $question_text = trim($_POST['question_text']);
        $answers = $_POST['answers'];
        $correct_answer = isset($_POST['correct_answer']) ? intval($_POST['correct_answer']) : -1;

        // Validation des données
        if (empty($formation_id) || empty($question_text) || count($answers) != 4 || $correct_answer < 0 || $correct_answer > 3) {
            $error_message = "Veuillez remplir tous les champs et sélectionner une réponse correcte.";
        } else {
            // Vérifier que toutes les réponses sont remplies
            $all_answers_filled = true;
            foreach ($answers as $answer) {
                if (trim($answer) === '') {
                    $all_answers_filled = false;
                    break;
                }
            }

            if (!$all_answers_filled) {
                $error_message = "Veuillez remplir toutes les réponses.";
            } else {
                // Insérer la question
                $stmt = $conn->prepare("INSERT INTO questions (formation_id, question_text) VALUES (?, ?)");
                $stmt->bind_param("is", $formation_id, $question_text);
                $stmt->execute();
                $question_id = $stmt->insert_id;
                $stmt->close();

                // Insérer les réponses
                $stmt = $conn->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                foreach ($answers as $index => $answer_text) {
                    $is_correct = ($index == $correct_answer) ? 1 : 0;
                    $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                    $stmt->execute();
                }
                $stmt->close();
                
                $success_message = "Question et réponses ajoutées avec succès.";
                
                // Rediriger pour éviter la soumission multiple du formulaire
                header("Location: ajouter_question_sol.php?success=1");
                exit();
            }
        }
    }
}

// Traitement de la suppression d'une question
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $question_id = intval($_GET['delete']);
    
    // Vérifier que la question appartient à une formation SOL
    $check_question = "SELECT q.id FROM questions q JOIN formations f ON q.formation_id = f.id WHERE q.id = ? AND f.section = 'sol'";
    $stmt = $conn->prepare($check_question);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_message = "Question non autorisée.";
        header("Location: ajouter_question_sol.php?error=" . urlencode($error_message) . "&nocache=" . time());
        exit();
    }
    
    // Désactiver temporairement les contraintes de clé étrangère
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Utiliser des requêtes directes comme pour la suppression de toutes les questions
    $delete_answers_query = "DELETE FROM answers WHERE question_id = $question_id";
    $delete_answers_result = $conn->query($delete_answers_query);
    
    $delete_question_query = "DELETE FROM questions WHERE id = $question_id";
    $delete_question_result = $conn->query($delete_question_query);
    
    // Réactiver les contraintes de clé étrangère
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    if ($delete_answers_result && $delete_question_result) {
        // Rediriger vers la même page avec un message de succès
        header("Location: ajouter_question_sol.php?deleted=1&nocache=" . time());
        exit();
    } else {
        // Stocker le message d'erreur
        $error_message = "Erreur lors de la suppression de la question: " . $conn->error;
        
        // Rediriger avec un message d'erreur
        header("Location: ajouter_question_sol.php?error=" . urlencode($error_message) . "&nocache=" . time());
        exit();
    }
}

// Traitement de la suppression de toutes les questions
if (isset($_POST['delete_all']) && $_POST['delete_all'] == 'confirm') {
    // Désactiver temporairement les contraintes de clé étrangère
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Supprimer uniquement les réponses des questions liées aux formations SOL
    $conn->query("DELETE a FROM answers a 
                 JOIN questions q ON a.question_id = q.id 
                 JOIN formations f ON q.formation_id = f.id 
                 WHERE f.section = 'sol'");
    
    // Puis supprimer uniquement les questions liées aux formations SOL
    $conn->query("DELETE q FROM questions q 
                 JOIN formations f ON q.formation_id = f.id 
                 WHERE f.section = 'sol'");
    
    // Réactiver les contraintes de clé étrangère
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    // Rediriger vers la même page avec un message de succès
    header("Location: ajouter_question_sol.php?deleted_all=1");
    exit();
}

// Ajouter cette section après les autres conditions de vérification des messages
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Questions SOL - TTA</title>
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
        
        .answer-true {
            color: #155724;
            background-color: #d4edda;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .answer-false {
            color: #383d41;
            background-color: #e2e3e5;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        
        .filter-form {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-form select {
            min-width: 200px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            color: #0a3d91;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination .active {
            background-color: #0a3d91;
            color: white;
            border-color: #0a3d91;
        }
        
        .pagination .disabled {
            color: #6c757d;
            pointer-events: none;
            cursor: default;
        }
        
        .question-text {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .question-text:hover {
            white-space: normal;
            overflow: visible;
        }
        
        .delete-all-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 20px;
        }
        
        .delete-all-btn:hover {
            background-color: #c82333;
        }
        
        .table-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            transition: background-color 0.2s;
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
        
        .section-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: #0a3d91;
            color: white;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Mobile Navigation Toggle -->
    <button class="mobile-nav-toggle">
        <i class="fas fa-bars"></i>
    </button>

    <?php include '../includes/admin_sol_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Gestion des Questions SOL <span class="section-badge">SOL</span></h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Gestion des questions SOL</li>
            </ul>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Question et réponses ajoutées avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Question supprimée avec succès.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($_GET['deleted_all'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', 'Toutes les questions SOL ont été supprimées.');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('Erreur', '<?php echo addslashes($error_message); ?>');
                });
            </script>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('Succès', '<?php echo addslashes($success_message); ?>');
                });
            </script>
        <?php endif; ?>

        <!-- Add Question Form -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Ajouter une question SOL</h2>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="formation_id" class="form-label">Formation SOL</label>
                        <select id="formation_id" name="formation_id" class="form-select" required>
                            <option value="">Sélectionner une formation</option>
                            <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                    <option value="<?php echo $formation['id']; ?>">
                                        <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <?php $result_formations->data_seek(0); // Reset pointer for reuse ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="question_text" class="form-label">Question</label>
                        <textarea id="question_text" name="question_text" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Réponses (sélectionnez une seule réponse correcte)</label>
                        <div class="answer-container">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <div class="answer-row">
                                    <input type="text" name="answers[]" class="form-control answer-input" placeholder="Réponse <?php echo $i + 1; ?>" required>
                                    <label class="answer-label">
                                        <input type="radio" name="correct_answer" value="<?php echo $i; ?>" class="answer-radio" required>
                                        Correcte
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_question" class="btn btn-primary">Ajouter la question</button>
                </form>
            </div>
        </div>

        <!-- Questions List -->
        <div class="card animate-on-scroll">
            <div class="card-header">
                <h2 class="card-title">Liste des questions SOL</h2>
                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteAll()">
                    <i class="fas fa-trash"></i> Supprimer toutes les questions SOL
                </button>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="get" class="filter-form">
                    <select name="formation" class="form-select">
                        <option value="0">Toutes les formations SOL</option>
                        <?php if ($result_formations && $result_formations->num_rows > 0): ?>
                            <?php while ($formation = $result_formations->fetch_assoc()): ?>
                                <option value="<?php echo $formation['id']; ?>" <?php echo ($filter_formation == $formation['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($formation['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <?php if ($filter_formation > 0): ?>
                        <a href="ajouter_question_sol.php" class="btn btn-outline-secondary">Réinitialiser</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Formation</th>
                                <th>Question</th>
                                <th>Réponses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_questions && $result_questions->num_rows > 0): ?>
                                <?php while ($question = $result_questions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $question['id']; ?></td>
                                        <td><?php echo htmlspecialchars($question['title']); ?></td>
                                        <td class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></td>
                                        <td>
                                            <?php
                                            // Récupération des réponses
                                            $question_id = $question['id'];
                                            $sql_answers = "SELECT answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY id";
                                            $stmt = $conn->prepare($sql_answers);
                                            $stmt->bind_param("i", $question_id);
                                            $stmt->execute();
                                            $result_answers = $stmt->get_result();

                                            while ($answer = $result_answers->fetch_assoc()) {
                                                $class = $answer['is_correct'] ? 'answer-true' : 'answer-false';
                                                echo "<div class='$class'>" . htmlspecialchars($answer['answer_text']) . "</div>";
                                            }
                                            $stmt->close();
                                            ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="edit_question_sol.php?id=<?php echo $question['id']; ?>" class="btn-action btn-edit" data-tooltip="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <!-- Remplacer le bouton de suppression par un lien direct -->
                                            <a href="ajouter_question_sol.php?delete=<?php echo $question['id']; ?>" class="btn-action btn-delete" data-tooltip="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette question?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Aucune question SOL trouvée</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo $filter_formation > 0 ? '&formation=' . $filter_formation : ''; ?>">«</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $filter_formation > 0 ? '&formation=' . $filter_formation : ''; ?>">‹</a>
                        <?php else: ?>
                            <span class="disabled">«</span>
                            <span class="disabled">‹</span>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filter_formation > 0 ? '&formation=' . $filter_formation : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $filter_formation > 0 ? '&formation=' . $filter_formation : ''; ?>">›</a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo $filter_formation > 0 ? '&formation=' . $filter_formation : ''; ?>">»</a>
                        <?php else: ?>
                            <span class="disabled">›</span>
                            <span class="disabled">»</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Form (hidden) -->
    <form id="delete-form" method="post" style="display: none;">
        <input type="hidden" name="delete_all" value="confirm">
    </form>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        function confirmDeleteAll() {
            Swal.fire({
                title: 'Confirmer la suppression',
                text: 'Êtes-vous sûr de vouloir supprimer TOUTES les questions SOL ? Cette action est irréversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, tout supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-form').submit();
                }
            });
        }
    </script>
</body>
</html>
