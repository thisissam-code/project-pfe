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

// Vérifier si l'ID de la question est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Erreur: ID de question non valide.";
    exit();
}

$question_id = intval($_GET['id']);

// Afficher les informations de débogage
echo "<h2>Débogage de la suppression de la question #$question_id</h2>";

// Vérifier si la question existe
$check_query = "SELECT id FROM questions WHERE id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Erreur: La question avec l'ID $question_id n'existe pas.";
    exit();
}

echo "<p>✓ La question #$question_id existe dans la base de données.</p>";

// Vérifier les réponses associées
$check_answers = "SELECT id FROM answers WHERE question_id = ?";
$stmt = $conn->prepare($check_answers);
$stmt->bind_param("i", $question_id);
$stmt->execute();
$result_answers = $stmt->get_result();
$answer_count = $result_answers->num_rows;

echo "<p>✓ Trouvé $answer_count réponses associées à cette question.</p>";

// Vérifier s'il y a d'autres tables qui référencent cette question
echo "<p>Vérification des références potentielles à cette question dans d'autres tables...</p>";

// Liste des tables à vérifier (ajoutez d'autres tables si nécessaire)
$tables_to_check = [
    'exam_questions' => 'question_id',
    'question_categories' => 'question_id',
    'question_tags' => 'question_id',
    // Ajoutez d'autres tables et colonnes selon votre schéma
];

$references_found = false;

foreach ($tables_to_check as $table => $column) {
    // Vérifier si la table existe
    $table_check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($table_check->num_rows > 0) {
        // La table existe, vérifier les références
        $check_ref = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $stmt = $conn->prepare($check_ref);
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $ref_result = $stmt->get_result();
        $ref_count = $ref_result->fetch_assoc()['count'];
        
        if ($ref_count > 0) {
            echo "<p style='color:red;'>⚠️ Trouvé $ref_count références dans la table $table.</p>";
            $references_found = true;
        } else {
            echo "<p>✓ Aucune référence trouvée dans la table $table.</p>";
        }
    } else {
        echo "<p>✓ La table $table n'existe pas dans la base de données.</p>";
    }
}

if ($references_found) {
    echo "<p style='color:red;'>Des références à cette question ont été trouvées dans d'autres tables. Vous devez d'abord supprimer ces références.</p>";
    echo "<p><a href='ajouter_question.php'>Retour à la liste des questions</a></p>";
    exit();
}

// Tenter la suppression
echo "<h3>Tentative de suppression...</h3>";

try {
    // Désactiver temporairement les contraintes de clé étrangère si nécessaire
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    // Supprimer d'abord les réponses
    $delete_answers = "DELETE FROM answers WHERE question_id = ?";
    $stmt = $conn->prepare($delete_answers);
    $stmt->bind_param("i", $question_id);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p>✓ Réponses supprimées avec succès.</p>";
        
        // Maintenant supprimer la question
        $delete_question = "DELETE FROM questions WHERE id = ?";
        $stmt = $conn->prepare($delete_question);
        $stmt->bind_param("i", $question_id);
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p>✓ Question supprimée avec succès.</p>";
            echo "<p>Suppression réussie! <a href='ajouter_question.php?deleted=1'>Retour à la liste des questions</a></p>";
            
            // Réactiver les contraintes de clé étrangère
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            
            // Redirection automatique après 3 secondes
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'ajouter_question.php?deleted=1';
                }, 3000);
            </script>";
        } else {
            echo "<p style='color:red;'>⚠️ Erreur lors de la suppression de la question: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:red;'>⚠️ Erreur lors de la suppression des réponses: " . $conn->error . "</p>";
    }
    
    // Réactiver les contraintes de clé étrangère
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
} catch (Exception $e) {
    // Réactiver les contraintes de clé étrangère en cas d'erreur
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "<p style='color:red;'>⚠️ Exception lors de la suppression: " . $e->getMessage() . "</p>";
}

// Afficher la structure de la base de données pour référence
echo "<h3>Structure de la base de données</h3>";

// Afficher la structure de la table questions
$table_structure = $conn->query("DESCRIBE questions");
echo "<h4>Structure de la table 'questions':</h4>";
echo "<pre>";
while ($row = $table_structure->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";

// Afficher la structure de la table answers
$table_structure = $conn->query("DESCRIBE answers");
echo "<h4>Structure de la table 'answers':</h4>";
echo "<pre>";
while ($row = $table_structure->fetch_assoc()) {
    print_r($row);
}
echo "</pre>";
?>
