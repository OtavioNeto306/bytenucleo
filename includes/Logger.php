<?php
class Logger {
    private $logFile;
    
    public function __construct($filename = 'app.log') {
        // Tentar usar o diretório logs primeiro
        $logDir = __DIR__ . '/../logs/';
        $this->logFile = $logDir . $filename;
        
        // Se não conseguir criar o diretório logs, usar diretório temporário
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                // Usar diretório temporário como fallback
                $tempDir = sys_get_temp_dir() . '/wowdash_logs/';
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                $this->logFile = $tempDir . $filename;
            }
        }
        
        // Verificar se o diretório final é gravável
        $finalLogDir = dirname($this->logFile);
        if (!is_writable($finalLogDir)) {
            // Tentar usar o diretório atual como último recurso
            $this->logFile = __DIR__ . '/' . $filename;
        }
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        try {
            // Verificar se o diretório existe
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                if (!mkdir($logDir, 0755, true)) {
                    throw new Exception("Não foi possível criar o diretório: " . $logDir);
                }
            }
            
            // Verificar se o diretório é gravável
            if (!is_writable($logDir)) {
                throw new Exception("Diretório não é gravável: " . $logDir);
            }
            
            // Tentar escrever o log
            $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            if ($result === false) {
                throw new Exception("Falha ao escrever no arquivo de log: " . $this->logFile);
            }
            
        } catch (Exception $e) {
            // Se falhar, tentar escrever no log de erro do PHP
            error_log("Logger Error: " . $e->getMessage());
            error_log("Tentando escrever: " . $logEntry);
        }
    }
    
    public function info($message) {
        $this->log($message, 'INFO');
    }
    
    public function error($message) {
        $this->log($message, 'ERROR');
    }
    
    public function debug($message) {
        $this->log($message, 'DEBUG');
    }
    
    public function warning($message) {
        $this->log($message, 'WARNING');
    }
    
    public function getLogs($lines = 50) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $logs = file($this->logFile);
        return array_slice($logs, -$lines);
    }
    
    public function clearLogs() {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
    
    public function getLogPath() {
        return $this->logFile;
    }
    
    public function getLogDir() {
        return dirname($this->logFile);
    }
}
?>
