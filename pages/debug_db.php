<?php
session_start();
require '../includes/bd.php';

// Vérifier si l'utilisateur est un admin avant d'accéder à la page
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'admin_sol' && $_SESSION['role'] !== 'admin_vol')) {
    header("Location: ../login.php");
    exit();
}

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Style CSS pour la page
echo '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Débogage de la Base de Données - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            padding: 0;
            color: #333;
        }
        h1, h2, h3 {
            color: #0a3d91;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #0a3d91;
        }
        .success {
            color: #155724;
            background-color: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .warning {
            color: #856404;
            background-color: #fff3cd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .info {
            color: #0c5460;
            background-color: #d1ecf1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #0a3d91;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            border: none;
            cursor: pointer;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-success {
            background-color: #28a745;
        }
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-database"></i> Débogage de la Base de Données</h1>
';

// Fonction pour afficher un message
function showMessage($type, $message) {
    echo "<div class='$type'>$message</div>";
}

// Section 1: Informations sur la base de données
echo '<div class="section">';
echo '<h2><i class="fas fa-info-circle"></i> Informations sur la Base de Données</h2>';

// Vérifier la connexion à la base de données
if ($conn->connect_error) {
    showMessage('error', 'Erreur de connexion à la base de données: ' . $conn->connect_error);
} else {
    showMessage('success', 'Connexion à la base de données établie avec succès.');
    
    // Afficher les informations sur le serveur
    echo '<p><strong>Serveur MySQL:</strong> ' . $conn->server_info . '</p>';
    echo '<p><strong>Version du protocole:</strong> ' . $conn->protocol_version . '</p>';
    echo '<p><strong>Jeu de caractères:</strong> ' . $conn->character_set_name() . '</p>';
    
    // Afficher les tables de la base de données
    $tables_result = $conn->query("SHOW TABLES");
    if ($tables_result) {
        echo '<h3>Tables dans la base de données:</h3>';
        echo '<ul>';
        while ($table = $tables_result->fetch_array()) {
            echo '<li>' . $table[0] . '</li>';
        }
        echo '</ul>';
    }
}
echo '</div>';

// Section 2: Vérification des tables questions et answers
echo '<div class="section">';
echo '<h2><i class="fas fa-check-circle"></i> Vérification des Tables Questions et Réponses</h2>';

// Vérifier si la table questions existe
$check_questions = $conn->query("SHOW TABLES LIKE 'questions'");
if ($check_questions->num_rows > 0) {
    showMessage('success', 'La table "questions" existe.');
    
    // Afficher la structure de la table questions
    $structure_questions = $conn->query("DESCRIBE questions");
    if ($structure_questions) {
        echo '<h3>Structure de la table "questions":</h3>';
        echo '<table>';
        echo '<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>';
        while ($field = $structure_questions->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $field['Field'] . '</td>';
            echo '<td>' . $field['Type'] . '</td>';
            echo '<td>' . $field['Null'] . '</td>';
            echo '<td>' . $field['Key'] . '</td>';
            echo '<td>' . ($field['Default'] === NULL ? 'NULL' : $field['Default']) . '</td>';
            echo '<td>' . $field['Extra'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // Compter le nombre de questions
    $count_questions = $conn->query("SELECT COUNT(*) as count FROM questions");
    $questions_count = $count_questions->fetch_assoc()['count'];
    echo '<p><strong>Nombre de questions:</strong> ' . $questions_count . '</p>';
    
    // Afficher quelques questions
    if ($questions_count > 0) {
        $sample_questions = $conn->query("SELECT id, formation_id, question_text FROM questions LIMIT 5");
        echo '<h3>Échantillon de questions:</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Formation ID</th><th>Question</th></tr>';
        while ($question = $sample_questions->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $question['id'] . '</td>';
            echo '<td>' . $question['formation_id'] . '</td>';
            echo '<td>' . htmlspecialchars($question['question_text']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} else {
    showMessage('error', 'La table "questions" n\'existe pas!');
}

// Vérifier si la table answers existe
$check_answers = $conn->query("SHOW TABLES LIKE 'answers'");
if ($check_answers->num_rows > 0) {
    showMessage('success', 'La table "answers" existe.');
    
    // Afficher la structure de la table answers
    $structure_answers = $conn->query("DESCRIBE answers");
    if ($structure_answers) {
        echo '<h3>Structure de la table "answers":</h3>';
        echo '<table>';
        echo '<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>';
        while ($field = $structure_answers->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $field['Field'] . '</td>';
            echo '<td>' . $field['Type'] . '</td>';
            echo '<td>' . $field['Null'] . '</td>';
            echo '<td>' . $field['Key'] . '</td>';
            echo '<td>' . ($field['Default'] === NULL ? 'NULL' : $field['Default']) . '</td>';
            echo '<td>' . $field['Extra'] . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    // Compter le nombre de réponses
    $count_answers = $conn->query("SELECT COUNT(*) as count FROM answers");
    $answers_count = $count_answers->fetch_assoc()['count'];
    echo '<p><strong>Nombre de réponses:</strong> ' . $answers_count . '</p>';
    
    // Afficher quelques réponses
    if ($answers_count > 0) {
        $sample_answers = $conn->query("SELECT id, question_id, answer_text, is_correct FROM answers LIMIT 10");
        echo '<h3>Échantillon de réponses:</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Question ID</th><th>Réponse</th><th>Correcte</th></tr>';
        while ($answer = $sample_answers->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $answer['id'] . '</td>';
            echo '<td>' . $answer['question_id'] . '</td>';
            echo '<td>' . htmlspecialchars($answer['answer_text']) . '</td>';
            echo '<td>' . ($answer['is_correct'] ? 'Oui' : 'Non') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} else {
    showMessage('error', 'La table "answers" n\'existe pas!');
}
echo '</div>';

// Section 3: Vérification des contraintes de clé étrangère
echo '<div class="section">';
echo '<h2><i class="fas fa-link"></i> Vérification des Contraintes de Clé Étrangère</h2>';

// Vérifier les contraintes de clé étrangère pour la table answers
$foreign_keys = $conn->query("
    SELECT 
        TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
        REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND (TABLE_NAME = 'answers' OR REFERENCED_TABLE_NAME = 'questions')
");

if ($foreign_keys && $foreign_keys->num_rows > 0) {
    echo '<h3>Contraintes de clé étrangère trouvées:</h3>';
    echo '<table>';
    echo '<tr><th>Table</th><th>Colonne</th><th>Nom de la contrainte</th><th>Table référencée</th><th>Colonne référencée</th></tr>';
    while ($key = $foreign_keys->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $key['TABLE_NAME'] . '</td>';
        echo '<td>' . $key['COLUMN_NAME'] . '</td>';
        echo '<td>' . $key['CONSTRAINT_NAME'] . '</td>';
        echo '<td>' . $key['REFERENCED_TABLE_NAME'] . '</td>';
        echo '<td>' . $key['REFERENCED_COLUMN_NAME'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    showMessage('warning', 'Aucune contrainte de clé étrangère trouvée pour les tables "questions" et "answers".');
}

// Vérifier si les contraintes de clé étrangère sont activées
$foreign_key_checks = $conn->query("SELECT @@FOREIGN_KEY_CHECKS as checks");
$fk_checks = $foreign_key_checks->fetch_assoc()['checks'];
echo '<p><strong>État des vérifications de clé étrangère:</strong> ' . ($fk_checks ? 'Activées' : 'Désactivées') . '</p>';

// Boutons pour activer/désactiver les contraintes de clé étrangère
echo '<form method="post" style="margin-top: 15px;">';
echo '<button type="submit" name="enable_fk" class="btn btn-success">Activer les contraintes de clé étrangère</button>';
echo '<button type="submit" name="disable_fk" class="btn btn-warning">Désactiver les contraintes de clé étrangère</button>';
echo '</form>';

// Traitement des boutons
if (isset($_POST['enable_fk'])) {
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    echo '<script>window.location.reload();</script>';
} elseif (isset($_POST['disable_fk'])) {
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    echo '<script>window.location.reload();</script>';
}
echo '</div>';

// Section 4: Test de suppression
echo '<div class="section">';
echo '<h2><i class="fas fa-trash"></i> Test de Suppression</h2>';

// Formulaire pour tester la suppression d'une question
echo '<form method="post" onsubmit="return confirm(\'Êtes-vous sûr de vouloir tester la suppression de cette question?\');">';
echo '<div style="margin-bottom: 15px;">';
echo '<label for="question_id" style="display: block; margin-bottom: 5px;"><strong>ID de la question à supprimer:</strong></label>';
echo '<input type="number" id="question_id" name="question_id" required style="padding: 8px; width: 200px;">';
echo '</div>';
echo '<button type="submit" name="test_delete" class="btn btn-danger">Tester la suppression</button>';
echo '</form>';

// Traitement du test de suppression
if (isset($_POST['test_delete']) && isset($_POST['question_id'])) {
    $test_question_id = intval($_POST['question_id']);
    
    // Vérifier si la question existe
    $check_question = $conn->prepare("SELECT id FROM questions WHERE id = ?");
    $check_question->bind_param("i", $test_question_id);
    $check_question->execute();
    $check_result = $check_question->get_result();
    
    if ($check_result->num_rows === 0) {
        showMessage('error', "La question avec l'ID $test_question_id n'existe pas.");
    } else {
        echo '<h3>Résultats du test de suppression pour la question #' . $test_question_id . ':</h3>';
        
        // Vérifier les réponses associées
        $check_answers = $conn->prepare("SELECT COUNT(*) as count FROM answers WHERE question_id = ?");
        $check_answers->bind_param("i", $test_question_id);
        $check_answers->execute();
        $answers_count = $check_answers->get_result()->fetch_assoc()['count'];
        
        echo '<p>Nombre de réponses associées: ' . $answers_count . '</p>';
        
        // Désactiver temporairement les contraintes de clé étrangère
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        
        try {
            // Supprimer d'abord les réponses
            $delete_answers = $conn->prepare("DELETE FROM answers WHERE question_id = ?");
            $delete_answers->bind_param("i", $test_question_id);
            $delete_answers_result = $delete_answers->execute();
            
            if ($delete_answers_result) {
                showMessage('success', "Les réponses associées à la question #$test_question_id ont été supprimées avec succès.");
                
                // Supprimer la question
                $delete_question = $conn->prepare("DELETE FROM questions WHERE id = ?");
                $delete_question->bind_param("i", $test_question_id);
                $delete_question_result = $delete_question->execute();
                
                if ($delete_question_result) {
                    showMessage('success', "La question #$test_question_id a été supprimée avec succès.");
                } else {
                    showMessage('error', "Erreur lors de la suppression de la question: " . $conn->error);
                }
            } else {
                showMessage('error', "Erreur lors de la suppression des réponses: " . $conn->error);
            }
        } catch (Exception $e) {
            showMessage('error', "Exception lors de la suppression: " . $e->getMessage());
        }
        
        // Réactiver les contraintes de clé étrangère
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
    }
}
echo '</div>';

// Section 5: Réparation de la base de données
echo '<div class="section">';
echo '<h2><i class="fas fa-wrench"></i> Réparation de la Base de Données</h2>';

// Boutons pour réparer la base de données
echo '<form method="post" style="margin-bottom: 15px;">';
echo '<button type="submit" name="repair_tables" class="btn btn-warning" onclick="return confirm(\'Êtes-vous sûr de vouloir réparer les tables?\');">Réparer les tables</button>';
echo '<button type="submit" name="optimize_tables" class="btn btn-success" onclick="return confirm(\'Êtes-vous sûr de vouloir optimiser les tables?\');">Optimiser les tables</button>';
echo '</form>';

// Traitement de la réparation des tables
if (isset($_POST['repair_tables'])) {
    $tables_to_repair = ['questions', 'answers'];
    $repair_results = [];
    
    foreach ($tables_to_repair as $table) {
        $repair_result = $conn->query("REPAIR TABLE $table");
        if ($repair_result) {
            $repair_info = $repair_result->fetch_assoc();
            $repair_results[] = "Table '$table': " . $repair_info['Msg_text'];
        } else {
            $repair_results[] = "Erreur lors de la réparation de la table '$table': " . $conn->error;
        }
    }
    
    echo '<h3>Résultats de la réparation:</h3>';
    echo '<ul>';
    foreach ($repair_results as $result) {
        echo '<li>' . $result . '</li>';
    }
    echo '</ul>';
}

// Traitement de l'optimisation des tables
if (isset($_POST['optimize_tables'])) {
    $tables_to_optimize = ['questions', 'answers'];
    $optimize_results = [];
    
    foreach ($tables_to_optimize as $table) {
        $optimize_result = $conn->query("OPTIMIZE TABLE $table");
        if ($optimize_result) {
            $optimize_info = $optimize_result->fetch_assoc();
            $optimize_results[] = "Table '$table': " . $optimize_info['Msg_text'];
        } else {
            $optimize_results[] = "Erreur lors de l'optimisation de la table '$table': " . $conn->error;
        }
    }
    
    echo '<h3>Résultats de l\'optimisation:</h3>';
    echo '<ul>';
    foreach ($optimize_results as $result) {
        echo '<li>' . $result . '</li>';
    }
    echo '</ul>';
}
echo '</div>';

// Bouton de retour
echo '<a href="ajouter_question.php" class="btn">Retour à la gestion des questions</a>';

// Fermer les balises HTML
echo '
    </div>
</body>
</html>
';

// Fermer la connexion à la base de données
$conn->close();
?>
