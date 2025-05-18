<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est un pilote avant d'accéder à la page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pilot') {
    header("Location: ../index.php");
    exit();
}

$pilot_id = $_SESSION['user_id'];
$pilot_section = $_SESSION['section'] ?? null;
$pilot_fonction = $_SESSION['fonction'] ?? null;

// Récupérer toutes les attestations du pilote
$query_attestations = $conn->prepare("
    SELECT a.id, a.title, a.date_emission, a.date_expiration, a.type, a.fichier, a.statut,
           f.title as formation_title, f.section, f.fonction
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.user_id = ?
    ORDER BY a.date_emission DESC
");
$query_attestations->bind_param("i", $pilot_id);
$query_attestations->execute();
$result_attestations = $query_attestations->get_result();

// Statistiques des attestations
$query_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_attestations,
        SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valid_attestations,
        SUM(CASE WHEN statut = 'expire' THEN 1 ELSE 0 END) as expired_attestations,
        SUM(CASE WHEN statut = 'bientot_expire' THEN 1 ELSE 0 END) as soon_expired_attestations,
        SUM(CASE WHEN type = 'interne' THEN 1 ELSE 0 END) as internal_attestations,
        SUM(CASE WHEN type = 'externe' THEN 1 ELSE 0 END) as external_attestations
    FROM attestations
    WHERE user_id = ?
");
$query_stats->bind_param("i", $pilot_id);
$query_stats->execute();
$stats = $query_stats->get_result()->fetch_assoc();

// Enregistrer la consultation de la page des attestations
$query_log_view = $conn->prepare("
    INSERT INTO user_activity_log (user_id, activity_type, activity_details, timestamp)
    VALUES (?, 'view_attestations', 'Consultation de la liste des attestations', NOW())
");
$query_log_view->bind_param("i", $pilot_id);
$query_log_view->execute();

// Filtrage des attestations
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Construire la requête en fonction du filtre
$filter_query = "
    SELECT a.id, a.title, a.date_emission, a.date_expiration, a.type, a.fichier, a.statut,
           f.title as formation_title, f.section, f.fonction
    FROM attestations a
    JOIN formations f ON a.formation_id = f.id
    WHERE a.user_id = ?
";

// Ajouter les conditions de filtrage
switch ($filter) {
    case 'valid':
        $filter_query .= " AND a.statut = 'valide'";
        break;
    case 'expired':
        $filter_query .= " AND a.statut = 'expire'";
        break;
    case 'soon_expired':
        $filter_query .= " AND a.statut = 'bientot_expire'";
        break;
    case 'internal':
        $filter_query .= " AND a.type = 'interne'";
        break;
    case 'external':
        $filter_query .= " AND a.type = 'externe'";
        break;
}

// Ajouter la recherche si elle existe
if (!empty($search)) {
    $filter_query .= " AND (a.title LIKE ? OR f.title LIKE ?)";
}

$filter_query .= " ORDER BY a.date_emission DESC";

$stmt = $conn->prepare($filter_query);

// Lier les paramètres en fonction de la présence de la recherche
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bind_param("iss", $pilot_id, $search_param, $search_param);
} else {
    $stmt->bind_param("i", $pilot_id);
}

$stmt->execute();
$filtered_attestations = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Attestations - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="../components/alerts.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .total-attestations {
            border-top: 4px solid #0a3d91;
        }
        
        .valid-attestations {
            border-top: 4px solid #28a745;
        }
        
        .expired-attestations {
            border-top: 4px solid #dc3545;
        }
        
        .soon-expired-attestations {
            border-top: 4px solid #ffc107;
        }
        
        .attestation-card {
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background-color: #fff;
            border-left: 4px solid #0a3d91;
        }
        
        .attestation-card.valide {
            border-left-color: #28a745;
        }
        
        .attestation-card.expire {
            border-left-color: #dc3545;
        }
        
        .attestation-card.bientot_expire {
            border-left-color: #ffc107;
        }
        
        .attestation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .attestation-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .attestation-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .attestation-body {
            padding: 20px;
        }
        
        .attestation-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        .attestation-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 15px;
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
        
        .badge-success {
            background-color: #28a745;
        }
        
        .badge-danger {
            background-color: #dc3545;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background-color: #17a2b8;
        }
        
        .badge-secondary {
            background-color: #6c757d;
        }
        
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-item {
            padding: 8px 15px;
            border-radius: 20px;
            background-color: #f8f9fa;
            color: #495057;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .filter-item:hover, .filter-item.active {
            background-color: #0a3d91;
            color: #fff;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 15px 8px 35px;
            border-radius: 20px;
            border: 1px solid #ced4da;
            font-size: 0.9rem;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .no-attestations {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .no-attestations i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .no-attestations h3 {
            margin-bottom: 10px;
            color: #343a40;
        }
        
        .no-attestations p {
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

    <?php include '../includes/pilote_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Mes Attestations</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="pilot_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Attestations</li>
            </ul>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total-attestations">
                <div class="stat-label">Total des attestations</div>
                <div class="stat-value"><?php echo $stats['total_attestations'] ?? 0; ?></div>
            </div>
            <div class="stat-card valid-attestations">
                <div class="stat-label">Attestations valides</div>
                <div class="stat-value"><?php echo $stats['valid_attestations'] ?? 0; ?></div>
            </div>
            <div class="stat-card expired-attestations">
                <div class="stat-label">Attestations expirées</div>
                <div class="stat-value"><?php echo $stats['expired_attestations'] ?? 0; ?></div>
            </div>
            <div class="stat-card soon-expired-attestations">
                <div class="stat-label">Expirant bientôt</div>
                <div class="stat-value"><?php echo $stats['soon_expired_attestations'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <a href="?filter=all" class="filter-item <?php echo $filter === 'all' ? 'active' : ''; ?>">
                Toutes
            </a>
            <a href="?filter=valid" class="filter-item <?php echo $filter === 'valid' ? 'active' : ''; ?>">
                Valides
            </a>
            <a href="?filter=expired" class="filter-item <?php echo $filter === 'expired' ? 'active' : ''; ?>">
                Expirées
            </a>
            <a href="?filter=soon_expired" class="filter-item <?php echo $filter === 'soon_expired' ? 'active' : ''; ?>">
                Expirant bientôt
            </a>
            <a href="?filter=internal" class="filter-item <?php echo $filter === 'internal' ? 'active' : ''; ?>">
                Internes
            </a>
            <a href="?filter=external" class="filter-item <?php echo $filter === 'external' ? 'active' : ''; ?>">
                Externes
            </a>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <form method="GET" action="">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <input type="text" name="search" placeholder="Rechercher une attestation..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </div>

        <!-- Attestations List -->
        <div class="row" id="attestations-container">
            <?php if ($filtered_attestations && $filtered_attestations->num_rows > 0): ?>
                <?php while ($attestation = $filtered_attestations->fetch_assoc()): ?>
                    <div class="col-md-6">
                        <div class="attestation-card <?php echo $attestation['statut']; ?>">
                            <div class="attestation-header">
                                <h3 class="attestation-title"><?php echo htmlspecialchars($attestation['title']); ?></h3>
                                <?php 
                                    $badge_class = '';
                                    $badge_text = '';
                                    
                                    switch ($attestation['statut']) {
                                        case 'valide':
                                            $badge_class = 'badge-success';
                                            $badge_text = 'Valide';
                                            break;
                                        case 'expire':
                                            $badge_class = 'badge-danger';
                                            $badge_text = 'Expirée';
                                            break;
                                        case 'bientot_expire':
                                            $badge_class = 'badge-warning';
                                            $badge_text = 'Expire bientôt';
                                            break;
                                    }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </div>
                            <div class="attestation-body">
                                <div class="attestation-info">
                                    <div class="info-item">
                                        <span class="info-label">Formation</span>
                                        <span class="info-value"><?php echo htmlspecialchars($attestation['formation_title']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Type</span>
                                        <span class="info-value"><?php echo $attestation['type'] === 'interne' ? 'Interne' : 'Externe'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Date d'émission</span>
                                        <span class="info-value"><?php echo date('d/m/Y', strtotime($attestation['date_emission'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Date d'expiration</span>
                                        <span class="info-value">
                                            <?php 
                                                if ($attestation['date_expiration']) {
                                                    echo date('d/m/Y', strtotime($attestation['date_expiration']));
                                                } else {
                                                    echo 'Non applicable';
                                                }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="attestation-actions">
                                    <?php if ($attestation['fichier']): ?>
                                        <a href="../uploads/attestations/<?php echo $attestation['fichier']; ?>" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Voir le document
                                        </a>
                                    <?php endif; ?>
                                    <a href="attestation_details.php?id=<?php echo $attestation['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-info-circle"></i> Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="no-attestations">
                        <i class="fas fa-certificate"></i>
                        <h3>Aucune attestation trouvée</h3>
                        <p>
                            <?php if (!empty($search)): ?>
                                Aucune attestation ne correspond à votre recherche "<?php echo htmlspecialchars($search); ?>".
                            <?php elseif ($filter !== 'all'): ?>
                                Aucune attestation ne correspond au filtre sélectionné.
                            <?php else: ?>
                                Vous n'avez pas encore d'attestations. Elles apparaîtront ici une fois que vous aurez complété des formations.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        // Script pour soumettre le formulaire de recherche lorsque l'utilisateur appuie sur Entrée
        document.querySelector('.search-box input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    </script>
</body>
</html>