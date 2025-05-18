<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un instructeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header("Location: ../login.php");
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Récupérer les informations de l'instructeur
$query_instructor = $conn->prepare("
    SELECT first_name, last_name 
    FROM users 
    WHERE id = ? AND role = 'instructor'
");
$query_instructor->bind_param("i", $instructor_id);
$query_instructor->execute();
$result_instructor = $query_instructor->get_result();

if ($result_instructor->num_rows === 0) {
    header("Location: ../login.php");
    exit();
}

$instructor = $result_instructor->fetch_assoc();

// Filtres
$section_filter = isset($_GET['section']) ? $_GET['section'] : '';
$fonction_filter = isset($_GET['fonction']) ? $_GET['fonction'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête avec les filtres
$query_formations = "
    SELECT f.id, f.title, f.duration, f.section, f.fonction, f.created_at,
           COUNT(DISTINCT e.id) as exam_count,
           COUNT(DISTINCT pe.pilot_id) as pilot_count,
           COUNT(DISTINCT a.id) as attestation_count
    FROM formations f
    JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN exams e ON f.id = e.formation_id
    LEFT JOIN pilot_exams pe ON e.id = pe.exam_id
    LEFT JOIN attestations a ON f.id = a.formation_id
    WHERE fi.instructor_id = ?
";

// Ajouter les filtres à la requête
$params = array($instructor_id);
$types = "i";

if (!empty($section_filter)) {
    $query_formations .= " AND f.section = ?";
    $params[] = $section_filter;
    $types .= "s";
}

if (!empty($fonction_filter)) {
    $query_formations .= " AND f.fonction = ?";
    $params[] = $fonction_filter;
    $types .= "s";
}

if (!empty($search_filter)) {
    $query_formations .= " AND f.title LIKE ?";
    $params[] = "%$search_filter%";
    $types .= "s";
}

$query_formations .= " GROUP BY f.id ORDER BY f.created_at DESC";

// Préparer et exécuter la requête
$stmt = $conn->prepare($query_formations);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result_formations = $stmt->get_result();

// Récupérer les sections et fonctions disponibles pour les filtres
$query_sections = $conn->query("SELECT DISTINCT section FROM formations WHERE section IS NOT NULL ORDER BY section");
$query_fonctions = $conn->query("SELECT DISTINCT fonction FROM formations WHERE fonction IS NOT NULL ORDER BY fonction");

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_formations', 'Consultation de la liste des formations', NOW())
");
$log_query->bind_param("i", $instructor_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .filter-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        
        .formation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .formation-card {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .formation-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .formation-title {
            margin: 0;
            font-size: 1.1rem;
            color: #0a3d91;
        }
        
        .formation-body {
            padding: 15px 20px;
        }
        
        .formation-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .info-item {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .formation-footer {
            padding: 10px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            text-align: right;
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
        
        .bg-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .bg-info {
            background-color: #17a2b8;
        }
        
        .bg-secondary {
            background-color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .empty-state p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
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
            <h1 class="page-title">Mes Formations</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Formations</li>
            </ul>
        </div>

        <!-- Filter Section -->
        <div class="filter-container">
            <h3><i class="fas fa-filter"></i> Filtrer les formations</h3>
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="section" class="form-label">Section</label>
                    <select id="section" name="section" class="form-select">
                        <option value="">Toutes les sections</option>
                        <?php while ($section = $query_sections->fetch_assoc()): ?>
                            <option value="<?php echo $section['section']; ?>" <?php echo ($section_filter === $section['section']) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($section['section'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="fonction" class="form-label">Fonction</label>
                    <select id="fonction" name="fonction" class="form-select">
                        <option value="">Toutes les fonctions</option>
                        <?php while ($fonction = $query_fonctions->fetch_assoc()): ?>
                            <option value="<?php echo $fonction['fonction']; ?>" <?php echo ($fonction_filter === $fonction['fonction']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fonction['fonction']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Rechercher par titre..." value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="instructor_formations.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Formations Grid -->
        <?php if ($result_formations && $result_formations->num_rows > 0): ?>
            <div class="formation-grid">
                <?php while ($formation = $result_formations->fetch_assoc()): ?>
                    <div class="formation-card">
                        <div class="formation-header">
                            <h3 class="formation-title"><?php echo htmlspecialchars($formation['title']); ?></h3>
                        </div>
                        <div class="formation-body">
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-layer-group"></i> Section: <?php echo $formation['section'] ? ucfirst(htmlspecialchars($formation['section'])) : 'Toutes'; ?></span>
                                <span class="info-item"><i class="fas fa-user-tag"></i> Fonction: <?php echo $formation['fonction'] ? htmlspecialchars($formation['fonction']) : 'Toutes'; ?></span>
                            </div>
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-clock"></i> Durée: <?php echo $formation['duration']; ?> heures</span>
                                <span class="info-item"><i class="fas fa-calendar-alt"></i> Créée le: <?php echo date('d/m/Y', strtotime($formation['created_at'])); ?></span>
                            </div>
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-users"></i> Pilotes: <?php echo $formation['pilot_count']; ?></span>
                                <span class="info-item"><i class="fas fa-clipboard-check"></i> Examens: <?php echo $formation['exam_count']; ?></span>
                            </div>
                            <div class="formation-info">
                                <span class="info-item"><i class="fas fa-certificate"></i> Attestations: <?php echo $formation['attestation_count']; ?></span>
                            </div>
                        </div>
                        <div class="formation-footer">
                            <a href="instructor_formation_details.php?id=<?php echo $formation['id']; ?>" class="btn btn-primary">Voir les détails</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-graduation-cap"></i>
                <h3>Aucune formation trouvée</h3>
                <p>Vous n'êtes actuellement assigné à aucune formation correspondant à vos critères de recherche. Contactez l'administrateur pour être assigné à des formations.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
