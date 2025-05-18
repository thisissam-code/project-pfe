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
    header("Location: pilot_exams.php");
    exit();
}

$exam_id = intval($_GET['id']);
$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Vérifier si l'examen existe et est disponible pour le pilote
$query_exam = $conn->prepare("
    SELECT e.*, f.title as formation_title, f.section, f.fonction
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE e.id = ?
");
$query_exam->bind_param("i", $exam_id);
$query_exam->execute();
$result_exam = $query_exam->get_result();

if ($result_exam->num_rows === 0) {
    header("Location: pilot_exams.php");
    exit();
}

$exam = $result_exam->fetch_assoc();

// Vérifier uniquement la fonction, pas la section
// Modification: Suppression de la vérification de section pour permettre l'accès aux examens sol et vol
if ($exam['fonction'] !== null && $exam['fonction'] !== $pilot_fonction) {
    header("Location: pilot_exams.php");
    exit();
}

// Vérifier si le pilote a déjà passé cet examen
$query_check_attempt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM exam_results 
    WHERE pilot_id = ? AND exam_id = ?
");
$query_check_attempt->bind_param("ii", $pilot_id, $exam_id);
$query_check_attempt->execute();
$has_attempted = $query_check_attempt->get_result()->fetch_assoc()['count'] > 0;

if ($has_attempted) {
    header("Location: exam_results.php?id=" . $exam_id);
    exit();
}

// Vérifier si une tentative est en cours
$query_check_in_progress = $conn->prepare("
    SELECT id 
    FROM exam_attempts 
    WHERE user_id = ? AND exam_id = ? AND status = 'in_progress'
");
$query_check_in_progress->bind_param("ii", $pilot_id, $exam_id);
$query_check_in_progress->execute();
$result_in_progress = $query_check_in_progress->get_result();

if ($result_in_progress->num_rows > 0) {
    $attempt_id = $result_in_progress->fetch_assoc()['id'];
} else {
    // Créer une nouvelle tentative
    $query_create_attempt = $conn->prepare("
        INSERT INTO exam_attempts (user_id, exam_id, start_time, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ");
    $query_create_attempt->bind_param("ii", $pilot_id, $exam_id);
    $query_create_attempt->execute();
    $attempt_id = $conn->insert_id;
}

// Récupérer les questions liées à l'examen
$query_questions = $conn->prepare("
    SELECT q.id, q.question_text, q.type
    FROM exam_questions eq
    JOIN questions q ON eq.question_id = q.id
    WHERE eq.exam_id = ?
    ORDER BY eq.question_order
");
$query_questions->bind_param("i", $exam_id);
$query_questions->execute();
$result_questions = $query_questions->get_result();
$total_questions = $result_questions->num_rows;

// Vérifier s'il y a suffisamment de questions
if ($total_questions < 10) {
    $error_message = "Cet examen ne contient pas assez de questions. Veuillez contacter l'administrateur.";
}

// Traitement de la soumission de l'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    $answers = $_POST['answers'] ?? [];
    
    // Mettre à jour la tentative
    $query_update_attempt = $conn->prepare("
        UPDATE exam_attempts 
        SET end_time = NOW(), status = 'completed' 
        WHERE id = ?
    ");
    $query_update_attempt->bind_param("i", $attempt_id);
    $query_update_attempt->execute();
    
    // Rediriger vers le script de traitement des résultats
    echo '<form id="resultForm" action="process_exam_result.php" method="POST">';
    echo '<input type="hidden" name="exam_id" value="' . $exam_id . '">';
    echo '<input type="hidden" name="time_taken" value="' . (isset($_POST['time_taken']) ? intval($_POST['time_taken']) : 0) . '">';
    
    foreach ($answers as $question_id => $answer_id) {
        echo '<input type="hidden" name="answers[' . $question_id . ']" value="' . $answer_id . '">';
    }
    
    echo '</form>';
    echo '<script>document.getElementById("resultForm").submit();</script>';
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passer l'examen - <?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .exam-info {
            flex: 1;
        }
        
        .exam-timer {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0a3d91;
            padding: 10px 20px;
            background-color: #e9ecef;
            border-radius: 5px;
        }
        
        .question-container {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .question-text {
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .question-number {
            font-weight: bold;
            color: #0a3d91;
            margin-right: 10px;
        }
        
        .answers-list {
            list-style-type: none;
            padding: 0;
        }
        
        .answer-item {
            margin-bottom: 10px;
        }
        
        .answer-label {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .answer-label:hover {
            background-color: #f8f9fa;
        }
        
        .answer-radio {
            margin-right: 15px;
        }
        
        .exam-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .exam-progress {
            width: 100%;
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #0a3d91;
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .library-section {
            margin-top: 30px;
        }
        
        .library-toggle {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        
        .library-toggle i {
            margin-right: 10px;
        }
        
        .library-content {
            display: none;
            padding: 15px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .document-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .document-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        
        .document-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .document-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .document-type {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .document-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .skip-info {
            margin-top: 10px;
            font-style: italic;
            color: #6c757d;
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
            <h1 class="page-title">Examen: <?php echo htmlspecialchars($exam['title']); ?></h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item"><a href="pilot_exams.php" class="breadcrumb-link">Examens</a></li>
                <li class="breadcrumb-item">Passer l'examen</li>
            </ul>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="exam-form">
                <div class="exam-header">
                    <div class="exam-info">
                        <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <p><strong>Formation:</strong> <?php echo htmlspecialchars($exam['formation_title']); ?></p>
                        <p><strong>Section:</strong> <?php echo $exam['section'] ? ucfirst(htmlspecialchars($exam['section'])) : 'Toutes'; ?></p>
                        <p><strong>Durée:</strong> <?php echo $exam['duration']; ?> minutes</p>
                        <p><strong>Score requis:</strong> <?php echo $exam['passing_score']; ?>%</p>
                        <p><strong>Notation:</strong> Chaque réponse correcte vaut 1%</p>
                        <p class="skip-info">Vous pouvez passer des questions sans y répondre.</p>
                    </div>
                    <div class="exam-timer" id="timer">
                        <i class="fas fa-clock"></i> <span id="minutes"><?php echo $exam['duration']; ?></span>:<span id="seconds">00</span>
                    </div>
                </div>

                <div class="exam-progress">
                    <div class="progress-bar" id="progress-bar"></div>
                </div>

                <div class="library-section">
                    <div class="library-toggle" onclick="toggleLibrary()">
                        <i class="fas fa-book"></i> Bibliothèque de documents
                        <i class="fas fa-chevron-down ml-auto"></i>
                    </div>
                    <div class="library-content" id="library-content">
                        <div class="document-list">
                            <?php
                            // Récupérer les documents de la bibliothèque
                            $query_documents = $conn->prepare("
                                SELECT id, title, description, file_path, file_type
                                FROM documents
                                ORDER BY title
                                LIMIT 10
                            ");
                            $query_documents->execute();
                            $result_documents = $query_documents->get_result();
                            
                            if ($result_documents->num_rows > 0):
                                while ($document = $result_documents->fetch_assoc()):
                            ?>
                                <div class="document-item">
                                    <div class="document-title"><?php echo htmlspecialchars($document['title']); ?></div>
                                    <div class="document-type">
                                        <i class="fas fa-file"></i> <?php echo strtoupper($document['file_type']); ?>
                                    </div>
                                    <div class="document-actions">
                                        <a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </div>
                                </div>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <p class="text-center">Aucun document disponible dans la bibliothèque.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                $question_number = 1;
                if ($result_questions && $result_questions->num_rows > 0):
                    while ($question = $result_questions->fetch_assoc()):
                        // Récupérer les réponses pour cette question
                        $query_answers = $conn->prepare("
                            SELECT id, answer_text
                            FROM answers
                            WHERE question_id = ?
                            ORDER BY RAND()
                        ");
                        $query_answers->bind_param("i", $question['id']);
                        $query_answers->execute();
                        $result_answers = $query_answers->get_result();
                ?>
                    <div class="question-container" id="question-<?php echo $question_number; ?>" <?php echo $question_number > 1 ? 'style="display: none;"' : ''; ?>>
                        <div class="question-text">
                            <span class="question-number"><?php echo $question_number; ?>.</span>
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        <ul class="answers-list">
                            <?php while ($answer = $result_answers->fetch_assoc()): ?>
                                <li class="answer-item">
                                    <label class="answer-label">
                                        <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $answer['id']; ?>" class="answer-radio">
                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                    </label>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php
                        $question_number++;
                    endwhile;
                endif;
                ?>

                <input type="hidden" name="time_taken" id="time_taken" value="0">

                <div class="exam-actions">
                    <button type="button" id="prev-btn" class="btn btn-secondary" disabled>
                        <i class="fas fa-arrow-left"></i> Question précédente
                    </button>
                    <button type="button" id="next-btn" class="btn btn-primary">
                        Question suivante <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submit-btn" name="submit_exam" class="btn btn-success" style="display: none;">
                        <i class="fas fa-check"></i> Terminer l'examen
                    </button>
                </div>
            </form>

            <script>
                // Variables pour la navigation entre les questions
                let currentQuestion = 1;
                const totalQuestions = <?php echo $total_questions; ?>;
                
                // Éléments DOM
                const prevBtn = document.getElementById('prev-btn');
                const nextBtn = document.getElementById('next-btn');
                const submitBtn = document.getElementById('submit-btn');
                const progressBar = document.getElementById('progress-bar');
                const timeTakenInput = document.getElementById('time_taken');
                
                // Fonction pour naviguer entre les questions
                function navigateQuestion(direction) {
                    // Masquer la question actuelle
                    document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                    
                    // Mettre à jour l'index de la question
                    if (direction === 'next') {
                        currentQuestion++;
                    } else {
                        currentQuestion--;
                    }
                    
                    // Afficher la nouvelle question
                    document.getElementById(`question-${currentQuestion}`).style.display = 'block';
                    
                    // Mettre à jour l'état des boutons
                    prevBtn.disabled = currentQuestion === 1;
                    
                    if (currentQuestion === totalQuestions) {
                        nextBtn.style.display = 'none';
                        submitBtn.style.display = 'block';
                    } else {
                        nextBtn.style.display = 'block';
                        submitBtn.style.display = 'none';
                    }
                    
                    // Mettre à jour la barre de progression
                    updateProgressBar();
                }
                
                // Fonction pour mettre à jour la barre de progression
                function updateProgressBar() {
                    const progress = (currentQuestion / totalQuestions) * 100;
                    progressBar.style.width = `${progress}%`;
                }
                
                // Écouteurs d'événements pour les boutons
                prevBtn.addEventListener('click', () => navigateQuestion('prev'));
                nextBtn.addEventListener('click', () => navigateQuestion('next'));
                
                // Initialiser la barre de progression
                updateProgressBar();
                
                // Fonction pour afficher/masquer la bibliothèque
                function toggleLibrary() {
                    const libraryContent = document.getElementById('library-content');
                    if (libraryContent.style.display === 'block') {
                        libraryContent.style.display = 'none';
                    } else {
                        libraryContent.style.display = 'block';
                    }
                }
                
                // Timer pour l'examen
                let duration = <?php echo $exam['duration']; ?> * 60; // Convertir en secondes
                let timeTaken = 0;
                const timerElement = document.getElementById('timer');
                const minutesElement = document.getElementById('minutes');
                const secondsElement = document.getElementById('seconds');
                
                const timer = setInterval(() => {
                    duration--;
                    timeTaken++;
                    timeTakenInput.value = timeTaken;
                    
                    const minutes = Math.floor(duration / 60);
                    const seconds = duration % 60;
                    
                    minutesElement.textContent = minutes.toString().padStart(2, '0');
                    secondsElement.textContent = seconds.toString().padStart(2, '0');
                    
                    if (duration <= 300) { // 5 minutes restantes
                        timerElement.style.color = '#dc3545';
                    }
                    
                    if (duration <= 0) {
                        clearInterval(timer);
                        document.getElementById('exam-form').submit();
                    }
                }, 1000);
                
                // Confirmation avant de quitter la page
                window.addEventListener('beforeunload', (e) => {
                    e.preventDefault();
                    e.returnValue = 'Êtes-vous sûr de vouloir quitter l\'examen ? Votre progression sera perdue.';
                });
                
                // Désactiver la confirmation lors de la soumission du formulaire
                document.getElementById('exam-form').addEventListener('submit', () => {
                    window.removeEventListener('beforeunload', () => {});
                });
            </script>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>