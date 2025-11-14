<?php
use Application\Core\Auth;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema completo para gerenciamento de quadras da atlética: agendamentos, controle de uso, reservas online e organização eficiente em um só lugar.">
    <title><?= isset($title) ? htmlspecialchars($title) : 'Sistema de Atléticas' ?></title>

    <!-- CSS de terceiros (CDNs): Bootstrap, Bootstrap Icons e Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">

    <!-- CSS global do projeto. Importa componentes/páginas via @import dentro de /css/default.css -->
    <link rel="stylesheet" href="/css/default.css">

    <!-- CSS adicionais específicos da página (injetados pelo controller via 'additional_styles') -->
    <?php if (!empty($additional_styles) && is_array($additional_styles)): ?>
        <?php foreach ($additional_styles as $style): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($style) ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Exposição do papel do usuário para o front-end quando autenticado -->
    <?php if (Auth::check()): ?>
    <script src="/js/app.js"></script>
    <script>
        window.userRole = '<?= Auth::role(); ?>';
    </script>
    <?php endif; ?>
</head>
<body class="<?= !empty($isAuthPage) ? 'auth-body' : 'd-flex flex-column min-vh-100' ?>">
    <!-- Fundo/tema específico para páginas de autenticação -->
    <?php if (!empty($isAuthPage)): ?>
    <div class="auth-background"></div>
    <?php endif; ?>

    <header>
        <!-- Cabeçalho global (parcial) -->
        <?php include ROOT_PATH . "/views/_partials/header.php"; ?>
    </header>

    <!-- Container principal: layout difere entre páginas de autenticação e demais páginas -->
    <?php if (!empty($isAuthPage)): ?>
    <main class="auth-container">
    <?php else: ?>
    <main class="<?= !empty($isFluidPage) ? 'container-fluid px-3 mt-4 flex-grow-1' : 'container mt-4 flex-grow-1' ?>">
    <?php endif; ?>
        <?= $content ?>
    </main>

    <footer>
        <!-- Rodapé global (parcial) -->
        <?php include ROOT_PATH . '/views/_partials/footer.php'; ?>
    </footer>
    <!-- JS de terceiros (Bootstrap Bundle com Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JS global do cabeçalho (somente para páginas não-auth) -->
    <?php if (empty($isAuthPage)): ?>
    <script src="/js/modules/_partials/calendar.js"></script>
    <script src="/js/modules/events/event-form.js"></script>
    <script src="/js/modules/_partials/header.js"></script>
    <script src="/js/modules/_partials/dashboard-calendar.js"></script>
    <?php endif; ?>
    <!-- Notificações em tempo real/UX: carregadas apenas para usuários autenticados -->
    <?php if (Auth::check()): ?>
    <script src="/js/modules/_partials/notifications.js"></script>
    <?php endif; ?>
    
    <!-- Scripts específicos do Super Admin -->
    <?php if (Auth::check() && Auth::role() === 'super_admin'): ?>
    <script src="/js/modules/super_admin/relatorios.js"></script>
    <?php endif; ?>

    <?php if (!empty($additional_scripts) && is_array($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
    <script src="<?= htmlspecialchars($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Scripts específicos da página -->
    <?php if (isset($eventos_presenca)): ?>
    <script>
        // Passar dados do PHP para o JavaScript (dashboard)
        window.eventosPresenca = <?php echo json_encode($eventos_presenca ?? []); ?>;
    </script>
    <?php endif; ?>
    
    <?php if (isset($todos_eventos)): ?>
    <script>
        // Passar todos os eventos aprovados para o JavaScript (dashboard)
        window.todosEventos = <?php echo json_encode($todos_eventos ?? []); ?>;
    </script>
    <?php endif; ?>
</body>
</html> 