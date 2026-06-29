<?php
// Couche d'abstraction pour le stockage des fichiers
// En dev : stockage local. En prod : pointer CDN_BASE_URL vers Cloudflare R2 / AWS S3
// Pour activer le CDN, définir CDN_BASE_URL dans secrets.php et CDN_ENABLED=true

if (!defined('CDN_ENABLED'))   define('CDN_ENABLED',  false);
if (!defined('CDN_BASE_URL'))  define('CDN_BASE_URL', '');

class Storage {
    // Retourne l'URL publique d'un fichier (local ou CDN)
    public static function url(?string $path): string {
        if (!$path) return '';
        if (CDN_ENABLED && CDN_BASE_URL) {
            // Le chemin local uploads/posts/abc.jpg → https://cdn.example.com/uploads/posts/abc.jpg
            return rtrim(CDN_BASE_URL, '/') . '/' . ltrim($path, '/');
        }
        return $path; // URL relative locale
    }

    // Déplacer un fichier uploadé vers le bon dossier
    public static function store(array $file, string $sub_dir, array $allowed_mimes): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!isset($allowed_mimes[$mime])) return null;

        $ext      = $allowed_mimes[$mime];
        $dir      = 'uploads/' . trim($sub_dir, '/') . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

        // Si CDN activé, uploader vers le bucket (placeholder : intégrer SDK R2/S3 ici)
        if (CDN_ENABLED) {
            self::upload_to_cdn($dest, $dest);
        }

        return $dest;
    }

    // Supprimer un fichier (local + CDN si activé)
    public static function delete(?string $path): void {
        if (!$path) return;
        if (file_exists($path)) @unlink($path);
        if (CDN_ENABLED && CDN_BASE_URL) {
            // Placeholder : appeler l'API S3/R2 pour supprimer l'objet
            // self::delete_from_cdn($path);
        }
    }

    // Placeholder CDN upload (à implémenter avec AWS SDK ou Cloudflare API)
    private static function upload_to_cdn(string $local_path, string $remote_key): void {
        // Exemple AWS S3 :
        // $s3->putObject(['Bucket'=>CDN_BUCKET,'Key'=>$remote_key,'SourceFile'=>$local_path]);
    }
}

// Types MIME autorisés par catégorie
const MIME_IMAGES = [
    'image/jpeg' => 'jpg', 'image/png' => 'png',
    'image/gif'  => 'gif', 'image/webp' => 'webp',
];
const MIME_VIDEOS = [
    'video/mp4' => 'mp4', 'video/webm' => 'webm',
];
const MIME_MEDIA = MIME_IMAGES + MIME_VIDEOS;
const MIME_DOCS  = [
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
];
const MIME_AUDIO = [
    'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/ogg' => 'ogg',
];
