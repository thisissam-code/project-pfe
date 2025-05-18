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
$formation_filter = isset($_GET['formation']) ? intval($_GET['formation']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête avec les filtres
$query_exams = "
    SELECT e.id, e.title, e.description, e.date, e.duration, e.passing_score,
           f.id as formation_id, f.title as formation_title,
           COUNT(DISTINCT er.id) as result_count,
           SUM(CASE WHEN er.status = 'passed' THEN 1 ELSE 0 END) as passed_count
    FROM exams e
    JOIN formations f ON e.formation_id = f.id
    JOIN formation_instructors fi ON f.id = fi.formation_id
    LEFT JOIN exam_results er ON e.id = er.exam_id
    WHERE fi.instructor_id = ?
";

// Ajouter les filtres à la requête
$params = array($instructor_id);
$types = "i";

if ($formation_filter > 0) {
    $query_exams .= " AND f.id = ?";
    $params[] = $formation_filter;
    $types .= "i";
}

if (!empty($search_filter)) {
    $query_exams .= " AND (e.title LIKE ? OR e.description LIKE ?)";
    $search_param = "%$search_filter%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query_exams .= " GROUP BY e.id";

// Ajouter le filtre de statut après le GROUP BY
if ($status_filter === 'passed') {
    $query_exams .= " HAVING passed_count > 0";
} elseif ($status_filter === 'failed') {
    $query_exams .= " HAVING result_count > passed_count";
} elseif ($status_filter === 'no_results') {
    $query_exams .= " HAVING result_count = 0";
}

$query_exams .= " ORDER BY e.date DESC";

// Préparer et exécuter la requête
$stmt = $conn->prepare($query_exams);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result_exams = $stmt->get_result();

// Récupérer les formations de l'instructeur pour le filtre
$query_formations = $conn->prepare("
    SELECT DISTINCT f.id, f.title
    FROM formations f
    JOIN formation_instructors fi ON f.id = fi.formation_id
    WHERE fi.instructor_id = ?
    ORDER BY f.title
");
$query_formations->bind_param("i", $instructor_id);
$query_formations->execute();
$result_formations = $query_formations->get_result();

// Enregistrer l'activité
$log_query = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_exams', 'Consultation de la liste des examens', NOW())
");
$log_query->bind_param("i", $instructor_id);
$log_query->execute();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Examens - TTA</title>
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            margin-bottom: 1rem;
            color: #212529;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            vertical-align: top;
            border-top: 1px solid #dee2e6;
        }
        
        .table thead th {
            vertical-align: bottom;
            border-bottom: 2px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
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
        
        .table-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
            color: #fff;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-view {
            background-color: #0a3d91;
        }
        
        .btn-action:hover {
            opacity: 0.9;
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
        
        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #0a3d91;
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
            <h1 class="page-title">Mes Examens</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="instructor_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Examens</li>
            </ul>
        </div>

        <!-- Filter Section -->
        <div class="filter-container">
            <h3><i class="fas fa-filter"></i> Filtrer les examens</h3>
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="formation" class="form-label">Formation</label>
                    <select id="formation" name="formation" class="form-select">
                        <option value="0">Toutes les formations</option>
                        <?php while ($formation = $result_formations->fetch_assoc()): ?>
                            <option value="<?php echo $formation['id']; ?>" <?php echo ($formation_filter === $formation['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formation['title']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status" class="form-label">Statut</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="passed" <?php echo ($status_filter === 'passed') ? 'selected' : ''; ?>>Avec réussites</option>
                        <option value="failed" <?php echo ($status_filter === 'failed') ? 'selected' : ''; ?>>Avec échecs</option>
                        <option value="no_results" <?php echo ($status_filter === 'no_results') ? 'selected' : ''; ?>>Sans résultats</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Rechercher par titre ou description..." value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="instructor_exams.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>

        <!-- Exams Table -->
        <?php if ($result_exams && $result_exams->num_rows > 0): ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Formation</th>
                                    <th>Date</th>
                                    <th>Durée</th>
                                    <th>Score minimum</th>
                                    <th>Résultats</th>
                                    <th>Taux de réussite</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($exam = $result_exams->fetch_assoc()): ?>
                                    <?php 
                                        $success_rate = $exam['result_count'] > 0 ? round(($exam['passed_count'] / $exam['result_count']) * 100) : 0;
                                        $badge_class = '';
                                        if ($success_rate >= 80) {
                                            $badge_class = 'bg-success';
                                        } elseif ($success_rate >= 50) {
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $badge_class = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                        <td><?php echo htmlspecialchars($exam['formation_title']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($exam['date'])); ?></td>
                                        <td><?php echo $exam['duration']; ?> min</td>
                                        <td><?php echo $exam['passing_score']; ?>%</td>
                                        <td><?php echo $exam['result_count']; ?> (<?php echo $exam['passed_count']; ?> réussis)</td>
                                        <td>
                                            <?php if ($exam['result_count'] > 0): ?>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $success_rate; ?>%</span>
                                                    <div class="progress-bar ml-2" style="width: 100px;">
                                                        <div class="progress-fill" style="width: <?php echo $success_rate; ?>%;"></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pas de résultats</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="table-actions">
                                            <a href="instructor_exam_results.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-view">
                                                <i class="fas fa-eye"></i> Voir résultats
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <h3>Aucun examen trouvé</h3>
                <p>Aucun examen ne correspond à vos critères de recherche ou n'est associé à vos formations.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
</body>
</html>
