# 🏗️ SGE - Arquitetura Técnica e Funcionamento Interno

> Documentação técnica detalhada do Sistema de Gerenciamento de Eventos UNIFIO - Como o código funciona por dentro

---

## 📋 Índice

- [Visão Geral da Arquitetura](#-visão-geral-da-arquitetura)
- [Fluxo de Requisições](#-fluxo-de-requisições)
- [Camadas da Aplicação](#-camadas-da-aplicação)
- [Sistema de Roteamento](#-sistema-de-roteamento)
- [Autenticação e Autorização](#-autenticação-e-autorização)
- [Padrão Repository](#-padrão-repository)
- [Sistema de Notificações](#-sistema-de-notificações)
- [Gerenciamento de Sessões](#-gerenciamento-de-sessões)
- [Estrutura do Banco de Dados](#-estrutura-do-banco-de-dados)
- [Padrões de Design Utilizados](#-padrões-de-design-utilizados)
- [Fluxos de Negócio](#-fluxos-de-negócio)
- [Configurações e Dependências](#-configurações-e-dependências)

---

## 🎯 Visão Geral da Arquitetura

O SGE implementa uma **arquitetura MVC (Model-View-Controller)** moderna com separação clara de responsabilidades e padrões de design bem definidos.

### Princípios Arquiteturais

- **Separação de Responsabilidades**: Cada camada tem uma função específica
- **Inversão de Dependência**: Controllers dependem de abstrações (Repositories)
- **Single Responsibility**: Cada classe tem uma única responsabilidade
- **DRY (Don't Repeat Yourself)**: Código reutilizável através de helpers e base classes
- **SOLID**: Princípios de design orientado a objetos aplicados

### Stack Tecnológica

```
┌─────────────────────────────────────────────────────────┐
│                    FRONTEND                             │
├─────────────────────────────────────────────────────────┤
│  HTML5 + CSS3 + JavaScript Vanilla + AJAX              │
│  • Interface responsiva e moderna                      │
│  • Comunicação assíncrona com backend                  │
│  • Componentes modulares (calendar, notifications)     │
└─────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────┐
│                    BACKEND                              │
├─────────────────────────────────────────────────────────┤
│  PHP 8.2 + Apache + MySQL 9.4                         │
│  • Arquitetura MVC com PSR-4 autoloading              │
│  • Padrão Repository para acesso a dados              │
│  • Sistema de roteamento customizado                  │
│  • Autenticação 2FA com PHPMailer                     │
└─────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────┐
│                 INFRAESTRUTURA                          │
├─────────────────────────────────────────────────────────┤
│  Docker + Docker Compose                               │
│  • Containerização completa                            │
│  • Ambiente de desenvolvimento consistente             │
│  • Deploy simplificado                                 │
└─────────────────────────────────────────────────────────┘
```

---

## 🔄 Fluxo de Requisições

### 1. Front Controller (`public/index.php`)

**Ponto de entrada único** - Todas as requisições passam por aqui:

```php
// 1. Configuração da sessão com parâmetros de segurança
session_start([
    'cookie_lifetime' => 0,           // Expira ao fechar navegador
    'cookie_httponly' => true,        // Previne XSS
    'cookie_secure' => isset($_SERVER['HTTPS']), // HTTPS se disponível
    'cookie_samesite' => 'Lax'        // Previne CSRF
]);

// 2. Configuração do ambiente
date_default_timezone_set('America/Sao_Paulo');
define('ROOT_PATH', __DIR__);

// 3. Autoloading PSR-4
require_once ROOT_PATH . '/vendor/autoload.php';

// 4. Carregamento de rotas
require_once ROOT_PATH . '/src/routes.php';

// 5. Roteamento e despacho
try {
    $url = $_GET['url'] ?? '/';
    $method = $_SERVER['REQUEST_METHOD'];
    Router::dispatch($url, $method);
} catch (Exception $e) {
    // Tratamento global de erros
    if (is_ajax_request()) {
        json_error($e->getMessage(), 500);
    } else {
        // Exibe página de erro
    }
}
```

### 2. Sistema de Roteamento (`src/Core/Router.php`)

**Mapeamento dinâmico** de URLs para Controllers:

```php
// Registro de rotas
Router::get('/usuario/:id', 'UsuarioController@show');
Router::post('/agendamento', 'AgendamentoController@store');
Router::put('/agendamento/:id', 'AgendamentoController@update');

// Despacho da requisição
public static function dispatch(string $uri, string $method): void
{
    // 1. Verifica method override (PUT via POST)
    if ($method === 'POST' && $_POST['_method'] === 'PUT') {
        $method = 'PUT';
    }
    
    // 2. Busca rota correspondente
    foreach (self::$routes[$method] as $route => $action) {
        $params = self::extractParams($route, $uri);
        if ($params !== null) {
            // 3. Instancia controller e chama método
            [$controllerName, $methodName] = explode('@', $action);
            $controllerClass = "Application\\Controller\\" . $controllerName;
            $controller = new $controllerClass();
            call_user_func_array([$controller, $methodName], array_values($params));
            return;
        }
    }
    
    throw new Exception("Rota não encontrada: [{$method}] {$uri}");
}
```

### 3. Controllers (`src/Controller/`)

**Orquestração da lógica de negócio**:

```php
class AgendamentoController extends BaseController
{
    public function store()
    {
        // 1. Autenticação obrigatória
        Auth::protect();
        
        // 2. Validação de dados
        $this->requireFieldsOrRedirect(['titulo', 'data'], $_POST, function() {
            $this->setErrorAndRedirect('Campos obrigatórios', '/agendar-evento');
        });
        
        // 3. Lógica de negócio
        $repo = $this->repository('AgendamentoRepository');
        $agendamentoId = $repo->create($_POST, Auth::id());
        
        // 4. Notificação automática
        $notificationService = new NotificationService();
        $notificationService->notifyAgendamentoAprovado($agendamentoId);
        
        // 5. Resposta
        $this->setSuccessAndRedirect('Agendamento criado com sucesso!', '/meus-agendamentos');
    }
}
```

---

## 🏗️ Camadas da Aplicação

### 1. Camada de Apresentação (Views)

**Templates organizados por funcionalidade**:

```
views/
├── _partials/           # Componentes reutilizáveis
│   ├── header.php       # Navegação, notificações, menu
│   ├── footer.php       # Scripts JavaScript
│   └── calendar.php     # Componente de calendário
├── auth/                # Autenticação
├── usuario/             # Painel do usuário
├── pages/               # Páginas gerais
├── admin_atletica/      # Painel admin atlética
└── super_admin/         # Painel super admin
```

**Sistema de Layout Automático**:

```php
// Helper view() em src/Core/helpers.php
function view(string $view, array $data = [])
{
    extract($data); // Torna dados acessíveis na view
    
    // Renderiza view em buffer
    ob_start();
    require ROOT_PATH . '/views/' . $view . '.view.php';
    $content = ob_get_clean();
    
    // Entrega para layout principal
    require ROOT_PATH . '/views/layout.php';
}
```

### 2. Camada de Controle (Controllers)

**BaseController** - Funcionalidades compartilhadas:

```php
abstract class BaseController
{
    // Factory method para repositories
    protected function repository(string $repositoryName)
    {
        $className = "Application\\Repository\\" . $repositoryName;
        return new $className();
    }
    
    // Dados do usuário logado
    protected function getUserData()
    {
        return [
            'nome' => Auth::name(),
            'email' => Auth::get('email'),
            'role' => Auth::role(),
            'atletica_id' => Auth::get('atletica_id')
        ];
    }
    
    // Helpers de validação e redirecionamento
    protected function requireFieldsOrRedirect(array $fields, array $source, callable $onError)
    protected function setErrorAndRedirect(string $message, string $redirectTo)
    protected function setSuccessAndRedirect(string $message, string $redirectTo)
}
```

### 3. Camada de Dados (Repositories)

**Padrão Repository** - Abstração do acesso a dados:

```php
class UsuarioRepository
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = Connection::getInstance(); // Singleton
    }
    
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
    
    public function create(array $data): int
    {
        $sql = "INSERT INTO usuarios (nome, email, senha, ra, curso_id) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['nome'],
            $data['email'],
            password_hash($data['senha'], PASSWORD_DEFAULT),
            $data['ra'],
            $data['curso_id']
        ]);
        return $this->pdo->lastInsertId();
    }
}
```

### 4. Camada de Serviços (Core)

**Lógica de negócio complexa**:

```php
class NotificationService
{
    private $notificationRepo;
    private $agendamentoRepo;
    
    public function notifyAgendamentoAprovado(int $agendamentoId): bool
    {
        $agendamento = $this->agendamentoRepo->findById($agendamentoId);
        if (!$agendamento) return false;
        
        $titulo = "Agendamento Aprovado! ✅";
        $mensagem = "Seu agendamento '{$agendamento['titulo']}' foi aprovado.";
        
        return $this->notificationRepo->create(
            $agendamento['usuario_id'],
            $titulo,
            $mensagem,
            'agendamento_aprovado',
            $agendamentoId
        );
    }
}
```

---

## 🛣️ Sistema de Roteamento

### Estrutura de Rotas

**Definição em `src/routes.php`**:

```php
// Rotas públicas (não requerem autenticação)
Router::get('/', 'HomeController@index');
Router::get('/login', 'AuthController@login');
Router::post('/login', 'AuthController@authenticate');

// Rotas protegidas (requerem login)
Router::get('/dashboard', 'UsuarioController@dashboard');
Router::get('/agenda', 'AgendaController@index');
Router::post('/agendamento', 'AgendamentoController@store');

// Rotas de admin (requerem role 'admin')
Router::get('/admin/atletica/dashboard', 'AdminAtleticaController@dashboard');
Router::post('/admin/atletica/aprovar-membro', 'AdminAtleticaController@aprovarMembro');

// Rotas de super admin (requerem role 'superadmin')
Router::get('/superadmin/dashboard', 'SuperAdminController@dashboard');
Router::post('/superadmin/aprovar-agendamento', 'SuperAdminController@aprovarAgendamento');
```

### Parâmetros Dinâmicos

**Suporte a parâmetros na URL**:

```php
// Rota com parâmetro
Router::get('/usuario/:id', 'UsuarioController@show');

// Extração de parâmetros
private static function extractParams(string $route, string $uri): ?array
{
    // Converte :id para regex (\d+)
    $pattern = preg_replace('/:(\w+)/', '(\d+)', $route);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $uri, $matches)) {
        array_shift($matches); // Remove match completo
        return $matches;
    }
    
    return null;
}

// Uso no controller
public function show(int $id)
{
    $repo = $this->repository('UsuarioRepository');
    $usuario = $repo->findById($id);
    view('usuario/detalhes', ['usuario' => $usuario]);
}
```

### Method Override

**Suporte a PUT via POST** (necessário para formulários HTML):

```php
// No formulário HTML
<form method="POST">
    <input type="hidden" name="_method" value="PUT">
    <!-- campos do formulário -->
</form>

// No Router
if ($method === 'POST' && $_POST['_method'] === 'PUT') {
    $method = 'PUT';
}
```

---

## 🔐 Autenticação e Autorização

### Sistema de Autenticação 2FA

**Fluxo de login em duas etapas**:

```php
class AuthController
{
    public function authenticate()
    {
        // Etapa 1: Verificar email/senha
        $email = $_POST['email'];
        $senha = $_POST['senha'];
        
        $usuario = $this->repository('UsuarioRepository')->findByEmail($email);
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            $this->setErrorAndRedirect('Credenciais inválidas', '/login');
        }
        
        // Gerar código 2FA
        $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->repository('UsuarioRepository')->setLoginCode($usuario['id'], $codigo);
        
        // Enviar por email
        $emailService = new EmailService();
        $emailService->sendVerificationCode($usuario['email'], $usuario['nome'], $codigo);
        
        // Redirecionar para verificação
        $_SESSION['pending_user_id'] = $usuario['id'];
        redirect('/login-verify');
    }
    
    public function verifyCode()
    {
        $codigo = $_POST['codigo'];
        $userId = $_SESSION['pending_user_id'];
        
        $usuario = $this->repository('UsuarioRepository')->findById($userId);
        if (!$usuario || $usuario['login_code'] !== $codigo) {
            $this->setErrorAndRedirect('Código inválido', '/login-verify');
        }
        
        // Login bem-sucedido
        $this->createSession($usuario);
        redirect('/dashboard');
    }
}
```

### Middlewares de Autorização

**Controle de acesso por roles**:

```php
class Auth
{
    // Verificação básica de login
    public static function protect()
    {
        if (!self::check()) {
            $_SESSION['error_message'] = 'Você precisa estar logado.';
            redirect('/login');
        }
    }
    
    // Verificação de admin de atlética
    public static function protectAdmin()
    {
        self::protect(); // Primeiro verifica login
        if (self::role() !== 'admin') {
            http_response_code(403);
            die('Acesso negado. Área restrita para Administradores.');
        }
    }
    
    // Verificação de super admin
    public static function protectSuperAdmin()
    {
        self::protect();
        if (self::role() !== 'superadmin') {
            http_response_code(403);
            die('Acesso negado. Área restrita para Super Administradores.');
        }
    }
}
```

### Gerenciamento de Sessão

**Dados armazenados na sessão**:

```php
private function createSession(array $usuario)
{
    $_SESSION['loggedin'] = true;
    $_SESSION['id'] = $usuario['id'];
    $_SESSION['nome'] = $usuario['nome'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['role'] = $usuario['role'];
    $_SESSION['atletica_id'] = $usuario['atletica_id'];
    $_SESSION['curso_id'] = $usuario['curso_id'];
    $_SESSION['tipo_usuario_detalhado'] = $usuario['tipo_usuario_detalhado'];
    $_SESSION['is_coordenador'] = $usuario['is_coordenador'];
}
```

---

## 🗄️ Padrão Repository

### Implementação do Padrão

**Abstração da camada de dados**:

```php
// Interface comum (implícita)
interface RepositoryInterface
{
    public function findById(int $id): ?array;
    public function findAll(): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}

// Implementação concreta
class AgendamentoRepository
{
    private $pdo;
    
    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }
    
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.nome as usuario_nome, c.nome as curso_nome 
            FROM agendamentos a
            LEFT JOIN usuarios u ON a.usuario_id = u.id
            LEFT JOIN cursos c ON u.curso_id = c.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
    
    public function create(array $data, int $usuarioId): int
    {
        $sql = "INSERT INTO agendamentos (
            usuario_id, titulo, tipo_agendamento, data_agendamento, 
            periodo, descricao, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pendente')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $data['titulo'],
            $data['tipo_agendamento'],
            $data['data_agendamento'],
            $data['periodo'],
            $data['descricao']
        ]);
        
        return $this->pdo->lastInsertId();
    }
}
```

### Queries Complexas

**Relatórios e agregações**:

```php
class RelatorioRepository
{
    public function getRelatorioGeral(string $dataInicio, string $dataFim): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_eventos,
                COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as eventos_aprovados,
                COUNT(CASE WHEN tipo_agendamento = 'esportivo' THEN 1 END) as eventos_esportivos,
                COUNT(CASE WHEN tipo_agendamento = 'nao_esportivo' THEN 1 END) as eventos_nao_esportivos,
                SUM(estimativa_participantes) as total_participantes_estimados
            FROM agendamentos 
            WHERE data_agendamento BETWEEN ? AND ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dataInicio, $dataFim]);
        return $stmt->fetch();
    }
    
    public function getPresencasByAgendamento(int $agendamentoId): array
    {
        $sql = "
            SELECT p.*, u.nome, u.email, u.telefone
            FROM presencas p
            JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.agendamento_id = ?
            ORDER BY p.data_presenca DESC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agendamentoId]);
        return $stmt->fetchAll();
    }
}
```

---

## 🔔 Sistema de Notificações

### Arquitetura do Sistema

**Notificações em tempo real via AJAX**:

```php
class NotificationController
{
    public function getUnreadCount()
    {
        Auth::protect();
        
        $repo = $this->repository('NotificationRepository');
        $count = $repo->getUnreadCount(Auth::id());
        
        json_success(['count' => $count]);
    }
    
    public function markAsRead()
    {
        Auth::protect();
        
        $notificationId = (int)$_POST['notification_id'];
        $repo = $this->repository('NotificationRepository');
        
        if ($repo->markAsRead($notificationId, Auth::id())) {
            json_success();
        } else {
            json_error('Erro ao marcar notificação como lida');
        }
    }
}
```

### Tipos de Notificações

**Sistema tipado de notificações**:

```php
class NotificationService
{
    public function notifyAgendamentoAprovado(int $agendamentoId): bool
    {
        $agendamento = $this->agendamentoRepo->findById($agendamentoId);
        if (!$agendamento) return false;
        
        $titulo = "Agendamento Aprovado! ✅";
        $mensagem = "Seu agendamento '{$agendamento['titulo']}' foi aprovado.";
        
        return $this->notificationRepo->create(
            $agendamento['usuario_id'],
            $titulo,
            $mensagem,
            'agendamento_aprovado',  // Tipo específico
            $agendamentoId
        );
    }
    
    public function notifyPresencaConfirmada(int $userId, int $agendamentoId): bool
    {
        $agendamento = $this->agendamentoRepo->findById($agendamentoId);
        
        $titulo = "Presença Confirmada! ✅";
        $mensagem = "Você marcou presença no evento '{$agendamento['titulo']}'.";
        
        return $this->notificationRepo->create(
            $userId,
            $titulo,
            $mensagem,
            'presenca_confirmada',
            $agendamentoId
        );
    }
}
```

### Interface Frontend

**JavaScript para notificações em tempo real**:

```javascript
// public/js/notifications.js
class NotificationManager {
    constructor() {
        this.updateInterval = 30000; // 30 segundos
        this.init();
    }
    
    init() {
        this.updateUnreadCount();
        this.bindEvents();
        setInterval(() => this.updateUnreadCount(), this.updateInterval);
    }
    
    async updateUnreadCount() {
        try {
            const response = await fetch('/api/notifications/count');
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.count);
            }
        } catch (error) {
            console.error('Erro ao atualizar contador:', error);
        }
    }
    
    updateBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            badge.textContent = count > 0 ? count : '';
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }
}
```

---

## 🗃️ Estrutura do Banco de Dados

### Padrão de Nomenclatura

**Convenções utilizadas**:

- **Tabelas**: Nome no plural (`usuarios`, `agendamentos`, `atleticas`)
- **Chaves primárias**: `id` (INT AUTO_INCREMENT)
- **Chaves estrangeiras**: `{tabela}_id` (ex: `usuario_id`, `curso_id`)
- **Campos de auditoria**: `data_criacao`, `data_atualizacao`
- **Status**: ENUM com valores descritivos (`'aprovado'`, `'pendente'`, `'rejeitado'`)

### Relacionamentos Principais

```sql
-- Estrutura simplificada das principais tabelas

-- Usuários do sistema
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    ra VARCHAR(20) UNIQUE,
    telefone VARCHAR(20),
    data_nascimento DATE,
    curso_id INT,
    atletica_id INT,
    role ENUM('usuario', 'admin', 'superadmin') DEFAULT 'usuario',
    tipo_usuario_detalhado ENUM('Aluno', 'Professor', 'Membro das Atleticas', 'Comunidade Externa'),
    is_coordenador TINYINT(1) DEFAULT 0,
    atletica_join_status ENUM('none', 'pendente', 'aprovado') DEFAULT 'none',
    login_code VARCHAR(6),
    login_code_expires DATETIME,
    reset_token VARCHAR(255),
    reset_token_expires DATETIME,
    FOREIGN KEY (curso_id) REFERENCES cursos(id),
    FOREIGN KEY (atletica_id) REFERENCES atleticas(id)
);

-- Agendamentos de eventos
CREATE TABLE agendamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    tipo_agendamento ENUM('esportivo', 'nao_esportivo') NOT NULL,
    esporte_tipo VARCHAR(100),
    data_agendamento DATE NOT NULL,
    periodo ENUM('primeiro', 'segundo') NOT NULL,
    descricao TEXT,
    status ENUM('aprovado', 'pendente', 'rejeitado', 'cancelado', 'finalizado') DEFAULT 'pendente',
    motivo_rejeicao TEXT,
    data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Sistema de presenças
CREATE TABLE presencas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    agendamento_id INT NOT NULL,
    data_presenca TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_presence (usuario_id, agendamento_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
);

-- Notificações do sistema
CREATE TABLE notificacoes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    tipo ENUM('agendamento_aprovado', 'agendamento_rejeitado', 'presenca_confirmada', 'lembrete_evento', 'info', 'aviso', 'sistema') NOT NULL,
    agendamento_id INT NULL,
    lida TINYINT(1) DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
);
```

### Índices e Performance

**Otimizações implementadas**:

```sql
-- Índices para consultas frequentes
CREATE INDEX idx_agendamentos_data ON agendamentos(data_agendamento);
CREATE INDEX idx_agendamentos_status ON agendamentos(status);
CREATE INDEX idx_agendamentos_usuario ON agendamentos(usuario_id);
CREATE INDEX idx_presencas_agendamento ON presencas(agendamento_id);
CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id);
CREATE INDEX idx_notificacoes_lida ON notificacoes(lida);

-- Índices compostos para consultas complexas
CREATE INDEX idx_agendamentos_data_status ON agendamentos(data_agendamento, status);
CREATE INDEX idx_notificacoes_usuario_lida ON notificacoes(usuario_id, lida);
```

---

## 🎨 Padrões de Design Utilizados

### 1. Singleton (Connection)

**Uma única instância de conexão com o banco**:

```php
class Connection
{
    private static ?PDO $instance = null;
    
    private function __construct() {} // Construtor privado
    private function __clone() {}     // Previne clonagem
    
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = new PDO($dsn, $username, $password, $options);
        }
        return self::$instance;
    }
}
```

### 2. Factory Method (BaseController)

**Criação dinâmica de repositories**:

```php
abstract class BaseController
{
    protected function repository(string $repositoryName)
    {
        $className = "Application\\Repository\\" . $repositoryName;
        return new $className();
    }
}
```

### 3. Template Method (Layout System)

**Estrutura comum para todas as views**:

```php
// views/layout.php
<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'SGE UNIFIO' ?></title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="/css/default.css">
</head>
<body>
    <?php if (!$isAuthPage): ?>
        <?php include ROOT_PATH . '/views/_partials/header.php'; ?>
    <?php endif; ?>
    
    <main>
        <?= $content ?>
    </main>
    
    <?php if (!$isAuthPage): ?>
        <?php include ROOT_PATH . '/views/_partials/footer.php'; ?>
    <?php endif; ?>
</body>
</html>
```

### 4. Strategy Pattern (Notification Types)

**Diferentes tipos de notificações**:

```php
class NotificationService
{
    public function notifyAgendamentoAprovado(int $agendamentoId): bool
    {
        return $this->createNotification($agendamentoId, 'agendamento_aprovado');
    }
    
    public function notifyAgendamentoRejeitado(int $agendamentoId, string $motivo): bool
    {
        return $this->createNotification($agendamentoId, 'agendamento_rejeitado', $motivo);
    }
    
    private function createNotification(int $agendamentoId, string $tipo, string $extra = ''): bool
    {
        // Lógica comum para criação de notificações
    }
}
```

---

## 🔄 Fluxos de Negócio

### 1. Fluxo de Agendamento

```mermaid
graph TD
    A[Usuário acessa /agendar-evento] --> B[Auth::protect verifica login]
    B --> C[Exibe formulário de agendamento]
    C --> D[Usuário preenche e submete]
    D --> E[AgendamentoController@store]
    E --> F[Validação de dados]
    F --> G[Verificação de conflitos]
    G --> H[AgendamentoRepository@create]
    H --> I[Status: 'pendente']
    I --> J[NotificationService notifica Super Admin]
    J --> K[Redireciona para /meus-agendamentos]
```

### 2. Fluxo de Aprovação

```mermaid
graph TD
    A[Super Admin acessa /superadmin/agendamentos] --> B[Auth::protectSuperAdmin]
    B --> C[Lista agendamentos pendentes]
    C --> D[Super Admin clica em 'Aprovar']
    D --> E[SuperAdminController@aprovarAgendamento]
    E --> F[AgendamentoRepository@updateStatus]
    F --> G[Status: 'aprovado']
    G --> H[NotificationService notifica usuário]
    H --> I[Evento aparece na agenda pública]
```

### 3. Fluxo de Presença

```mermaid
graph TD
    A[Usuário acessa /agenda] --> B[AgendaController@index]
    B --> C[Exibe calendário com eventos aprovados]
    C --> D[Usuário clica em 'Marcar Presença']
    D --> E[AJAX: /api/presenca/marcar]
    E --> F[AgendaController@marcarPresenca]
    F --> G[Verifica se evento é aprovado]
    G --> H[PresencaRepository@create]
    H --> I[NotificationService notifica confirmação]
    I --> J[Atualiza contador de presenças]
```

---

## ⚙️ Configurações e Dependências

### Autoloading PSR-4

**Configuração no `composer.json`**:

```json
{
    "autoload": {
        "psr-4": {
            "Application\\": "src/"
        },
        "files": [
            "src/Core/helpers.php"
        ]
    }
}
```

**Estrutura de namespaces**:

```
Application\
├── Controller\     # Controllers da aplicação
├── Repository\     # Camada de acesso a dados
├── Core\          # Classes principais (Router, Auth, Connection)
└── Models\        # Modelos de dados (se necessário)
```

### Configuração de Sessão

**Parâmetros de segurança**:

```php
session_start([
    'cookie_lifetime' => 0,                    // Expira ao fechar navegador
    'cookie_httponly' => true,                 // Previne XSS
    'cookie_secure' => isset($_SERVER['HTTPS']), // HTTPS se disponível
    'cookie_samesite' => 'Lax',                // Previne CSRF
    'use_strict_mode' => true,                 // Modo estrito de sessão
    'use_only_cookies' => true                 // Apenas cookies, não URL
]);
```

### Configuração do Banco

**Connection Singleton**:

```php
// Configurações do banco
$host = 'db';              // Nome do serviço Docker
$dbname = 'application';   // Nome do banco
$username = 'appuser';     // Usuário
$password = 'apppass';     // Senha
$charset = 'utf8mb4';      // Charset completo

// Opções PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
```

### Configuração de E-mail

**PHPMailer com Gmail**:

```php
class EmailService
{
    private function configureSMTP(): bool
    {
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('SMTP_EMAIL') ?: 'seu-email@gmail.com';
        $this->mailer->Password = getenv('SMTP_PASSWORD') ?: 'sua-senha-app';
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
        $this->mailer->CharSet = 'UTF-8';
        
        return true;
    }
}
```

---

## 🚀 Performance e Otimizações

### 1. Lazy Loading

**Conexão com banco criada apenas quando necessário**:

```php
public static function getInstance(): PDO
{
    if (self::$instance === null) {  // Lazy initialization
        self::$instance = new PDO($dsn, $username, $password, $options);
    }
    return self::$instance;
}
```

### 2. Prepared Statements

**Prevenção de SQL Injection**:

```php
public function findByEmail(string $email): ?array
{
    $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);  // Parâmetros seguros
    return $stmt->fetch() ?: null;
}
```

### 3. Índices de Banco

**Otimização de consultas frequentes**:

```sql
-- Consultas otimizadas com índices
CREATE INDEX idx_agendamentos_data_status ON agendamentos(data_agendamento, status);
CREATE INDEX idx_notificacoes_usuario_lida ON notificacoes(usuario_id, lida);
```

### 4. Cache de Sessão

**Dados do usuário em sessão**:

```php
// Evita consultas repetidas ao banco
$_SESSION['user_data'] = [
    'id' => $usuario['id'],
    'nome' => $usuario['nome'],
    'role' => $usuario['role']
];
```

---

## 🔧 Manutenção e Debugging

### Logs de Erro

**Tratamento global de exceções**:

```php
// public/index.php
try {
    Router::dispatch($url, $method);
} catch (Exception $e) {
    error_log("Erro na aplicação: " . $e->getMessage());
    
    if (is_ajax_request()) {
        json_error($e->getMessage(), 500);
    } else {
        // Exibe página de erro amigável
        view('errors/500', ['error' => $e->getMessage()]);
    }
}
```

### Debug Mode

**Desenvolvimento vs Produção**:

```php
// Configuração de ambiente
if (getenv('APP_ENV') === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
```

### Validação de Dados

**Sanitização e validação**:

```php
// Helpers de validação
function post_string(string $key, string $default = ''): string
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    $value = is_string($_POST[$key]) ? $_POST[$key] : $default;
    return trim($value);  // Remove espaços
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}
```

---

## 📊 Métricas e Monitoramento

### Queries de Performance

**Análise de uso do sistema**:

```sql
-- Eventos mais populares
SELECT titulo, COUNT(*) as total_agendamentos
FROM agendamentos 
WHERE status = 'aprovado'
GROUP BY titulo 
ORDER BY total_agendamentos DESC;

-- Taxa de aprovação
SELECT 
    COUNT(*) as total_solicitacoes,
    COUNT(CASE WHEN status = 'aprovado' THEN 1 END) as aprovadas,
    ROUND(COUNT(CASE WHEN status = 'aprovado' THEN 1 END) * 100.0 / COUNT(*), 2) as taxa_aprovacao
FROM agendamentos;

-- Uso por período
SELECT 
    periodo,
    COUNT(*) as total_eventos
FROM agendamentos 
WHERE status = 'aprovado'
GROUP BY periodo;
```

### Logs de Auditoria

**Rastreamento de ações importantes**:

```php
// Log de ações administrativas
private function logAdminAction(string $action, array $data): void
{
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => Auth::id(),
        'action' => $action,
        'data' => $data
    ];
    
    error_log("Admin Action: " . json_encode($log));
}
```

---

## 🎯 Conclusão

O SGE implementa uma arquitetura robusta e escalável, seguindo boas práticas de desenvolvimento e padrões de design estabelecidos. A separação clara de responsabilidades, o uso de padrões como Repository e Singleton, e a implementação de um sistema de roteamento flexível tornam o código maintível e extensível.

### Pontos Fortes da Arquitetura

- ✅ **Separação clara de responsabilidades** (MVC)
- ✅ **Padrões de design bem implementados** (Repository, Singleton, Factory)
- ✅ **Sistema de autenticação robusto** (2FA)
- ✅ **Roteamento flexível e dinâmico**
- ✅ **Tratamento de erros centralizado**
- ✅ **Código reutilizável** (BaseController, helpers)
- ✅ **Segurança implementada** (prepared statements, validação)

### Áreas de Melhoria

- 🔄 **Implementar cache** para consultas frequentes
- 🔄 **Adicionar testes automatizados** (PHPUnit)
- 🔄 **Implementar logs estruturados** (Monolog)
- 🔄 **Adicionar validação de entrada** mais robusta
- 🔄 **Implementar rate limiting** para APIs

Esta arquitetura fornece uma base sólida para futuras expansões e melhorias do sistema, mantendo a qualidade e a manutenibilidade do código.

---

<div align="center">

**SGE - Sistema de Gerenciamento de Eventos UNIFIO**

*Documentação Técnica | Versão 1.0 | Outubro 2025*

</div>

 