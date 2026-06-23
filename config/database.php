<?php
/**
 * Configuración de la Base de Datos MySQL
 * Sistema de Gestión de Inventario con Lotes (FIFO)
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gestion_inventario');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

/**
 * Obtener conexión a la base de datos
 * @return PDO
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Error de conexión DB: " . $e->getMessage());
            die("Error de conexión a la base de datos. Por favor, verifique la configuración.");
        }
    }
    
    return $pdo;
}

/**
 * Verificar si la base de datos está configurada
 * @return bool
 */
function checkDBConnection() {
    try {
        getDB();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Inicializar la base de datos desde el archivo SQL
 */
function initDatabase() {
    $db = getDB();
    $sql = file_get_contents(__DIR__ . '/../database_completa.sql');
    
    // Separar las sentencias SQL
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                // Ignorar errores si la tabla ya existe o el usuario ya existe
                if (strpos($e->getMessage(), 'Duplicate entry') === false && 
                    strpos($e->getMessage(), 'already exists') === false) {
                    error_log("Error ejecutando SQL: " . $e->getMessage());
                }
            }
        }
    }
}
