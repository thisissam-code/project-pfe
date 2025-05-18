<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Ajouter au début du fichier, après la connexion à la base de données
// Vérifier si les colonnes created_at et updated_at existent dans la table user_answers
$check_columns = $conn->query("SHOW COLUMNS FROM user_answers LIKE 'created_at'");
if ($check_columns->num_rows === 0) {
    // Ajouter les colonnes si elles n'existent pas
    $conn->query("ALTER TABLE user_answers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $conn->query("ALTER TABLE user_answers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

// Vérifier si un ID d'examen est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: pilot_formations.php");
    exit();
}

$exam_id = intval($_GET['id']);
$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Récupérer les informations de l'examen
$query_exam = $conn->prepare("
    SELECT e.*, f.title as formation_title, f.section, f.fonction, f.id as formation_id
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    WHERE e.id = ?
");
$query_exam->bind_param("i", $exam_id);
$query_exam->execute();
$result_exam = $query_exam->get_result();

if ($result_exam->num_rows === 0) {
    header("Location: pilot_formations.php");
    exit();
}

$exam = $result_exam->fetch_assoc();

// MODIFICATION: Permettre aux pilotes de prendre les examens des sections "sol" et "vol"
// Vérifier si le pilote a accès à cet examen (selon sa fonction et les sections autorisées)
$allowed_sections = ['sol', 'vol']; // Les sections autorisées pour tous les pilotes
$has_access = false;

// Vérifier si la fonction correspond
if ($exam['fonction'] === $pilot_fonction) {
    // Si l'examen n'a pas de section spécifique (section = NULL), il est accessible à tous
    if ($exam['section'] === null) {
        $has_access = true;
    } 
    // Si l'examen a une section spécifique, vérifier si c'est "sol" ou "vol"
    elseif (in_array(strtolower($exam['section']), $allowed_sections)) {
        $has_access = true;
    }
    // Si l'examen a une autre section, vérifier si elle correspond à celle du pilote
    elseif ($exam['section'] === $pilot_section) {
        $has_access = true;
    }
}

if (!$has_access) {
    header("Location: pilot_formations.php");
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

// Récupérer 100 questions aléatoires liées à la formation de l'examen
$query_questions = $conn->prepare("
    SELECT q.id, q.question_text, q.type
    FROM questions q
    WHERE q.formation_id = ?
    ORDER BY RAND()
    LIMIT 100
");
$query_questions->bind_param("i", $exam['formation_id']);
$query_questions->execute();
$result_questions = $query_questions->get_result();

// Vérifier s'il y a suffisamment de questions
if ($result_questions->num_rows < 10) {
    $error_message = "Cet examen ne contient pas assez de questions. Veuillez contacter l'administrateur.";
}

// Traitement de la soumission de l'examen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    $answers = $_POST['answers'] ?? [];
    $score = 0;
    $total_questions = count($answers);
    
    if ($total_questions === 0) {
        $error_message = "Vous devez répondre à au moins une question.";
    } else {
        // Mettre à jour la tentative
        $query_update_attempt = $conn->prepare("
            UPDATE exam_attempts 
            SET end_time = NOW(), status = 'completed' 
            WHERE id = ?
        ");
        $query_update_attempt->bind_param("i", $attempt_id);
        $query_update_attempt->execute();
        
        // Traiter chaque réponse
        foreach ($answers as $question_id => $answer_id) {
            // Vérifier si la réponse est correcte
            $query_check_answer = $conn->prepare("
                SELECT is_correct 
                FROM answers 
                WHERE id = ? AND question_id = ?
            ");
            $query_check_answer->bind_param("ii", $answer_id, $question_id);
            $query_check_answer->execute();
            $result_check = $query_check_answer->get_result();
            
            if ($result_check->num_rows > 0) {
                $is_correct = $result_check->fetch_assoc()['is_correct'];
                
                // Enregistrer la réponse de l'utilisateur
                $query_save_answer = $conn->prepare("
                    INSERT INTO user_answers (attempt_id, question_id, answer_id, is_correct)
                    VALUES (?, ?, ?, ?)
                ");
                $query_save_answer->bind_param("iiis", $attempt_id, $question_id, $answer_id, $is_correct);
                $query_save_answer->execute();
                
                if ($is_correct) {
                    $score++;
                }
            }
        }
        
        // Calculer le score en pourcentage
        $score_percentage = ($score / $total_questions) * 100;
        $status = ($score_percentage >= $exam['passing_score']) ? 'passed' : 'failed';
        
        // Enregistrer le résultat de l'examen
        $query_save_result = $conn->prepare("
            INSERT INTO exam_results (pilot_id, exam_id, score, status, date_taken, start_time, end_time)
            SELECT user_id, exam_id, ?, ?, NOW(), start_time, end_time
            FROM exam_attempts
            WHERE id = ?
        ");
        $query_save_result->bind_param("dsi", $score_percentage, $status, $attempt_id);
        $query_save_result->execute();
        
        // Mettre à jour le statut dans pilot_exams
        $query_update_status = $conn->prepare("
            INSERT INTO pilot_exams (pilot_id, exam_id, status)
            VALUES (?, ?, 'completed')
            ON DUPLICATE KEY UPDATE status = 'completed'
        ");
        $query_update_status->bind_param("ii", $pilot_id, $exam_id);
        $query_update_status->execute();
        
        // Rediriger vers la page des résultats
        header("Location: exam_results.php?id=" . $exam_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examen: <?php echo htmlspecialchars($exam['title']); ?> - TTA</title>
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
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
            border-radius: 8px;
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
        
        .question-navigation {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .question-nav-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .question-nav-btn:hover {
            background-color: #e9ecef;
        }
        
        .question-nav-btn.active {
            background-color: #0a3d91;
            color: #fff;
            border-color: #0a3d91;
        }
        
        .question-nav-btn.answered {
            background-color: #28a745;
            color: #fff;
            border-color: #28a745;
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
                <li class="breadcrumb-item"><a href="pilot_formations.php" class="breadcrumb-link">Formations</a></li>
                <li class="breadcrumb-item"><a href="formation_details.php?id=<?php echo $exam['formation_id']; ?>" class="breadcrumb-link"><?php echo htmlspecialchars($exam['formation_title']); ?></a></li>
                <li class="breadcrumb-item">Examen</li>
            </ul>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="exam-form">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Attention: Cet examen se fermera automatiquement après 1h30, quelle que soit la durée indiquée.
                </div>
                <div class="exam-header">
                    <div class="exam-info">
                        <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <p><strong>Formation:</strong> <?php echo htmlspecialchars($exam['formation_title']); ?></p>
                        <p><strong>Durée:</strong> <?php echo $exam['duration']; ?> minutes</p>
                        <p><strong>Score requis:</strong> <?php echo $exam['passing_score']; ?>%</p>
                        <?php if ($exam['section']): ?>
                            <p><strong>Section:</strong> <?php echo htmlspecialchars($exam['section']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="exam-timer" id="timer">
                        <i class="fas fa-clock"></i> <span id="minutes">90</span>:<span id="seconds">00</span>
                    </div>
                </div>

                <div class="exam-progress">
                    <div class="progress-bar" id="progress-bar"></div>
                </div>

                <div class="question-navigation" id="question-navigation">
                    <!-- Les boutons de navigation seront générés par JavaScript -->
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
                                        <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $answer['id']; ?>" class="answer-radio" data-question="<?php echo $question_number; ?>">
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
                const totalQuestions = <?php echo $result_questions->num_rows; ?>;
                const answeredQuestions = new Set();
                
                // Éléments DOM
                const prevBtn = document.getElementById('prev-btn');
                const nextBtn = document.getElementById('next-btn');
                const submitBtn = document.getElementById('submit-btn');
                const progressBar = document.getElementById('progress-bar');
                const questionNavigation = document.getElementById('question-navigation');
                
                // Générer les boutons de navigation
                function generateNavigationButtons() {
                    for (let i = 1; i <= totalQuestions; i++) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'question-nav-btn';
                        button.textContent = i;
                        button.dataset.question = i;
                        
                        if (i === currentQuestion) {
                            button.classList.add('active');
                        }
                        
                        button.addEventListener('click', () => {
                            navigateToQuestion(i);
                        });
                        
                        questionNavigation.appendChild(button);
                    }
                }
                
                // Fonction pour naviguer vers une question spécifique
                function navigateToQuestion(questionNumber) {
                    // Masquer la question actuelle
                    document.getElementById(`question-${currentQuestion}`).style.display = 'none';
                    
                    // Mettre à jour l'index de la question
                    currentQuestion = questionNumber;
                    
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
                    
                    // Mettre à jour les boutons de navigation
                    updateNavigationButtons();
                }
                
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
                    
                    // Mettre à jour les boutons de navigation
                    updateNavigationButtons();
                }
                
                // Fonction pour mettre à jour la barre de progression
                function updateProgressBar() {
                    const progress = (currentQuestion / totalQuestions) * 100;
                    progressBar.style.width = `${progress}%`;
                }
                
                // Fonction pour mettre à jour les boutons de navigation
                function updateNavigationButtons() {
                    const buttons = questionNavigation.querySelectorAll('.question-nav-btn');
                    
                    buttons.forEach(button => {
                        const questionNumber = parseInt(button.dataset.question);
                        
                        // Réinitialiser les classes
                        button.classList.remove('active');
                        
                        // Ajouter la classe active à la question courante
                        if (questionNumber === currentQuestion) {
                            button.classList.add('active');
                        }
                        
                        // Ajouter la classe answered aux questions répondues
                        if (answeredQuestions.has(questionNumber)) {
                            button.classList.add('answered');
                        }
                    });
                }
                
                // Écouteurs d'événements pour les boutons
                prevBtn.addEventListener('click', () => navigateQuestion('prev'));
                nextBtn.addEventListener('click', () => navigateQuestion('next'));
                
                // Ajouter après les écouteurs d'événements pour les réponses
                document.querySelectorAll('.answer-radio').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const questionNumber = parseInt(this.dataset.question);
                        answeredQuestions.add(questionNumber);
                        updateNavigationButtons();
                        
                        // Enregistrer la réponse en temps réel
                        saveAnswer(this.name.match(/\d+/)[0], this.value);
                    });
                });

                // Fonction pour enregistrer la réponse
                function saveAnswer(questionId, answerId) {
                    const formData = new FormData();
                    formData.append('attempt_id', <?php echo $attempt_id; ?>);
                    formData.append('question_id', questionId);
                    formData.append('answer_id', answerId);
                    
                    fetch('exam_response_tracker.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Réponse enregistrée:', data);
                    })
                    .catch(error => {
                        console.error('Erreur lors de l\'enregistrement de la réponse:', error);
                    });
                }
                
                // Initialiser la barre de progression
                updateProgressBar();
                
                // Générer les boutons de navigation
                generateNavigationButtons();
                
                // Timer pour l'examen - fixé à 90 minutes (1h30) indépendamment de la durée configurée
                let duration = 90 * 60; // 90 minutes en secondes
                const timerElement = document.getElementById('timer');
                const minutesElement = document.getElementById('minutes');
                const secondsElement = document.getElementById('seconds');

                const timer = setInterval(() => {
                    duration--;
                    
                    const minutes = Math.floor(duration / 60);
                    const seconds = duration % 60;
                    
                    minutesElement.textContent = minutes.toString().padStart(2, '0');
                    secondsElement.textContent = seconds.toString().padStart(2, '0');
                    
                    if (duration <= 300) { // 5 minutes restantes
                        timerElement.style.color = '#dc3545';
                    }
                    
                    if (duration <= 0) {
                        clearInterval(timer);
                        // Soumettre automatiquement l'examen
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
