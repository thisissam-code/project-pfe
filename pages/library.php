<?php
session_start();
require '../includes/bd.php'; // Connexion à la base de données

// Vérifier si l'utilisateur est connecté et est un admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Gestion de la suppression
if (isset($_POST['delete_document'])) {
    $document_id = intval($_POST['document_id']);
    
    // Récupérer le chemin du fichier avant de supprimer l'enregistrement
    $query_file = $conn->prepare("SELECT file_path FROM documents WHERE id = ?");
    $query_file->bind_param("i", $document_id);
    $query_file->execute();
    $file_result = $query_file->get_result();
    
    if ($file_data = $file_result->fetch_assoc()) {
        $file_path = '../' . $file_data['file_path'];
        
        // Supprimer le fichier physique s'il existe
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Supprimer l'enregistrement de la base de données
    $query_delete = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $query_delete->bind_param("i", $document_id);
    
    if ($query_delete->execute()) {
        $success_message = "Document supprimé avec succès.";
    } else {
        $error_message = "Erreur lors de la suppression du document.";
    }
}

// Récupérer tous les documents avec le nom de l'utilisateur qui l'a téléchargé
$query = "
    SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploader_name
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    ORDER BY d.upload_date DESC
";
$result = $conn->query($query);

// Récupérer les catégories distinctes pour le filtre
// $categories_query = "SELECT DISTINCT category FROM documents WHERE category IS NOT NULL AND category != '' ORDER BY category";
// $categories_result = $conn->query($categories_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliothèque de Documents - TTA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/admin_dashboard.css">
    <style>
        .document-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-select, .search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .document-card {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .document-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .document-title {
            margin: 0;
            font-size: 1.25rem;
            color: #0a3d91;
        }
        
        .document-body {
            padding: 20px;
        }
        
        .document-info {
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
        
        .document-description {
            margin-bottom: 20px;
            color: #495057;
        }
        
        .document-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .file-type-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .pdf-icon {
            color: #dc3545;
        }
        
        .docx-icon {
            color: #0a3d91;
        }
        
        .pptx-icon {
            color: #ff9800;
        }
        
        .image-icon {
            color: #28a745;
        }
        
        .other-icon {
            color: #6c757d;
        }
        
        .category-badge {
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
            background-color: #17a2b8;
            margin-left: 10px;
        }
        
        .add-document-btn {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: background-color 0.2s ease;
        }
        
        .add-document-btn:hover {
            background-color: #218838;
        }
        
        .no-documents {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        
        .no-documents-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
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
            <h1 class="page-title">Bibliothèque de Documents</h1>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php" class="breadcrumb-link">Accueil</a></li>
                <li class="breadcrumb-item">Bibliothèque</li>
            </ul>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <a href="add_library.php" class="add-document-btn">
            <i class="fas fa-plus"></i> Ajouter un document
        </a>

        <!-- Filters -->
        <div class="document-filters">
            <div class="filter-group">
                <label for="search-document" class="filter-label">Rechercher</label>
                <input type="text" id="search-document" class="search-input" placeholder="Titre, description...">
            </div>
        </div>

        <!-- Documents List -->
        <div class="row" id="documents-container">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($document = $result->fetch_assoc()): ?>
                    <div class="col-md-6 document-item" 
                         data-category="<?php echo htmlspecialchars($document['category'] ?? ''); ?>" 
                         data-type="<?php echo htmlspecialchars($document['file_type']); ?>">
                        <div class="document-card">
                            <div class="document-header">
                                <h3 class="document-title">
                                    <?php echo htmlspecialchars($document['title']); ?>
                                    <?php if (!empty($document['category'])): ?>
                                        <span class="category-badge"><?php echo htmlspecialchars($document['category']); ?></span>
                                    <?php endif; ?>
                                </h3>
                            </div>
                            <div class="document-body">
                                <div class="document-info">
                                    <div class="info-item">
                                        <?php
                                        $icon_class = 'other-icon';
                                        $icon = 'fa-file';
                                        
                                        switch (strtolower($document['file_type'])) {
                                            case 'pdf':
                                                $icon_class = 'pdf-icon';
                                                $icon = 'fa-file-pdf';
                                                break;
                                            case 'docx':
                                            case 'doc':
                                                $icon_class = 'docx-icon';
                                                $icon = 'fa-file-word';
                                                break;
                                            case 'pptx':
                                            case 'ppt':
                                                $icon_class = 'pptx-icon';
                                                $icon = 'fa-file-powerpoint';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif':
                                                $icon_class = 'image-icon';
                                                $icon = 'fa-file-image';
                                                break;
                                        }
                                        ?>
                                        <div class="file-type-icon <?php echo $icon_class; ?>">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <span class="info-value"><?php echo strtoupper(htmlspecialchars($document['file_type'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Téléchargé par</span>
                                        <span class="info-value"><?php echo htmlspecialchars($document['uploader_name'] ?? 'Inconnu'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Date</span>
                                        <span class="info-value"><?php echo date('d/m/Y', strtotime($document['upload_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($document['description'])): ?>
                                <div class="document-description">
                                    <?php echo htmlspecialchars($document['description']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="document-actions">
                                    <a href="../<?php echo htmlspecialchars($document['file_path']); ?>" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> Télécharger
                                    </a>
                                    <a href="edit_library.php?id=<?php echo $document['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <form action="library.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                        <button type="submit" name="delete_document" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce document ?')">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="no-documents">
                        <div class="no-documents-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3>Aucun document disponible</h3>
                        <p>Commencez par ajouter des documents à la bibliothèque.</p>
                        <a href="add_library.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter un document
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../scripts/admin_dashboard.js"></script>
    <script>
        // Filtrage des documents
        function filterDocuments() {
            const searchValue = document.getElementById('search-document').value.toLowerCase();
            
            const documentItems = document.querySelectorAll('.document-item');
            
            documentItems.forEach(item => {
                const title = item.querySelector('.document-title').textContent.toLowerCase();
                const description = item.querySelector('.document-description')?.textContent.toLowerCase() || '';
                
                const searchMatches = title.includes(searchValue) || description.includes(searchValue);
                
                if (searchMatches) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        document.getElementById('search-document').addEventListener('keyup', filterDocuments);
    </script>
</body>
</html>
