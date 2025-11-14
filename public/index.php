<?php
/**
 * ============================================================================
 * FRONT CONTROLLER - PONTO DE ENTRADA ÚNICO DA APLICAÇÃO
 * ============================================================================
 * 
 * Sistema de Gerenciamento de Eventos - UNIFIO
 * 
 * Este arquivo é o ÚNICO ponto de entrada de toda a aplicação.
 * Todas as requisições HTTP passam por aqui graças ao arquivo .htaccess
 * que redireciona todas as URLs para este arquivo.
 * 
 * FLUXO DE EXECUÇÃO:
 * 
 * 1. CONFIGURAÇÃO DA SESSÃO
 *    - Inicia sessão PHP com parâmetros de segurança
 *    - Sessão expira ao fechar o navegador (lifetime = 0)
 *    - Cookies com flags httponly e secure (se HTTPS)
 *    - SameSite=Lax para proteção contra CSRF
 * 
 * 2. CONFIGURAÇÃO DO AMBIENTE
 *    - Define timezone para America/Sao_Paulo (GMT-3)
 *    - Define constante ROOT_PATH para referências de arquivos
 * 
 * 3. AUTOLOADING
 *    - Carrega autoloader do Composer (PSR-4)
 *    - Permite uso de namespaces sem require manual
 * 
 * 4. CARREGAMENTO DE ROTAS
 *    - Inclui arquivo src/routes.php com todas as rotas
 *    - Rotas definem mapeamento URI → Controller@metodo
 * 
 * 5. ROTEAMENTO
 *    - Extrai URL da requisição via $_GET['url'] (injetado pelo .htaccess)
 *    - Normaliza URL (adiciona / inicial, remove / final)
 *    - Identifica método HTTP (GET, POST, PUT, etc)
 *    - Despacha para Router que encontra e executa o controller apropriado
 * 
 * 6. TRATAMENTO DE ERROS
 *    - Try-catch global captura exceções não tratadas
 *    - Diferencia requisições AJAX de requisições normais
 *    - Retorna JSON para AJAX, HTML para navegação normal
 *    - Exibe detalhes do erro (arquivo, linha, trace)
 * 
 * PADRÃO ARQUITETURAL:
 * Este arquivo implementa o padrão Front Controller, que:
 * - Centraliza o processamento de requisições
 * - Facilita aplicação de middlewares globais
 * - Permite roteamento dinâmico
 * - Simplifica configuração de segurança
 * 
 * SEGURANÇA:
 * - Cookies de sessão com httponly (previne XSS)
 * - Cookies secure quando HTTPS disponível
 * - SameSite=Lax (previne ataques CSRF)
 * - Tratamento de erros sem expor dados sensíveis
 * 
 * OBSERVAÇÃO:
 * O arquivo .htaccess no mesmo diretório redireciona todas as requisições
 * (exceto arquivos estáticos como CSS, JS, imagens) para este index.php,
 * passando o caminho solicitado via $_GET['url'].
 * 
 * @package Application
 * @version 1.0
 */

// ============================================================================
// 1. CONFIGURAÇÃO DA SESSÃO
// ============================================================================
// Configura a sessão para expirar quando o navegador for fechado
if (session_status() === PHP_SESSION_NONE) {
    // Define o cookie de sessão para expirar ao fechar o navegador (lifetime = 0)
    // e torna-o mais seguro com httponly e secure (se HTTPS estiver disponível)
    session_set_cookie_params([
        'lifetime' => 0,  // Sessão expira ao fechar o navegador
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================================================
// 2. CONFIGURAÇÃO DO AMBIENTE
// ============================================================================
// Define o fuso horário padrão da aplicação para horário de Brasília
date_default_timezone_set('America/Sao_Paulo');

// Define constante ROOT_PATH apontando para o diretório raiz do projeto
// Usada para incluir arquivos de forma absoluta (views, configs, etc)
define('ROOT_PATH', dirname(__DIR__));

// Adicionar comentário depois
define('VIEW_PATH', __DIR__ . '/../views');

// ============================================================================
// 3. AUTOLOADING E CARREGAMENTO DE DEPENDÊNCIAS
// ============================================================================
// Carrega o autoloader do Composer que permite uso de classes sem require manual
// O Composer gerencia o autoload seguindo o padrão PSR-4 definido em composer.json
require_once ROOT_PATH . '/vendor/autoload.php';

// ============================================================================
// 4. CARREGAMENTO DAS ROTAS
// ============================================================================
// Inclui o arquivo que define todas as rotas da aplicação
// Cada rota mapeia uma URI para um Controller@metodo
require_once ROOT_PATH . '/src/routes.php';

// ============================================================================
// 5. ROTEAMENTO E DESPACHO
// ============================================================================
// Extrai a URL da requisição (injetada pelo .htaccess via query string)
$url = $_GET['url'] ?? '/';
// Normaliza a URL: garante barra inicial e remove barra final
if ($url !== '/') {
    $url = '/' . rtrim($url, '/');
}

// Identifica o método HTTP da requisição (GET, POST, PUT, etc)
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Despacha a requisição para o Router
    // O Router encontra a rota correspondente, instancia o controller
    // e executa o método apropriado
    Application\Core\Router::dispatch($url, $method);
    
} catch (Exception $e) {
    // ========================================================================
    // 6. TRATAMENTO GLOBAL DE ERROS
    // ========================================================================
    // Em caso de erro, verificar se é uma requisição AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Ou verificar se a URL é de uma API/endpoint JSON
    $isJsonEndpoint = strpos($url, '/agendamento/detalhes') !== false;

    if ($isAjax || $isJsonEndpoint) {
        // Retornar erro em JSON
        if (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro no servidor',
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]);
    } else {
        // Retornar erro em HTML
        if (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo "<h1>Erro na Aplicação</h1>";
        echo "<p>Ocorreu um erro inesperado. Detalhes:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    }
} 