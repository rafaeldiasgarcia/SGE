<?php
/**
 * Arquivo de Definição de Rotas (routes.php)
 * 
 * Define todos os endpoints da aplicação, mapeando URIs e métodos HTTP
 * para ações específicas nos Controllers. Este arquivo é a espinha dorsal
 * do roteamento da aplicação.
 * 
 * Estrutura:
 * Router::metodo('/uri', 'NomeDoController@nomeDoMetodo')
 * 
 * Métodos HTTP suportados:
 * - GET: Buscar dados, exibir páginas
 * - POST: Enviar formulários, criar/atualizar dados
 * - PUT: Atualizar dados (via POST com _method="PUT")
 * 
 * Parâmetros Dinâmicos:
 * Use :nome_parametro na URI para capturar valores dinâmicos
 * Exemplo: '/usuario/:id' captura o ID e passa para o método do controller
 * 
 * Organização:
 * As rotas estão organizadas por área funcional:
 * 1. Autenticação (Login, Registro, Recuperação de Senha)
 * 2. Usuário Comum (Dashboard, Perfil, Inscrições)
 * 3. Páginas Gerais (Agenda, Agendamentos)
 * 4. Admin de Atlética (Gerenciamento de Membros, Inscrições, Eventos)
 * 5. Super Admin (Gerenciamento Completo do Sistema)
 * 6. API JSON (Notificações, Endpoints AJAX)
 * 
 * Middlewares de Proteção:
 * - Rotas públicas: Não requerem autenticação (login, registro)
 * - Rotas protegidas: Chamam Auth::protect() no controller
 * - Rotas de admin: Chamam Auth::protectAdmin()
 * - Rotas de superadmin: Chamam Auth::protectSuperAdmin()
 * 
 * Este arquivo é incluído pelo public/index.php após inicialização da sessão.
 * 
 * @package Application
 */

use Application\Core\Router;

// =============================================================================
// ROTAS DE AUTENTICAÇÃO E ACESSO PÚBLICO
// =============================================================================
// Rotas que não requerem autenticação prévia. Acessíveis a qualquer visitante.

// Página inicial - Redireciona conforme autenticação (dashboard se logado, agenda se não)
Router::get('/', 'HomeController@index');

// Fluxo de Login (2 etapas: senha + código por e-mail)
Router::get('/login', 'AuthController@showLoginForm');           // Exibe formulário de login
Router::post('/login', 'AuthController@login');                  // Processa login e envia código 2FA
Router::get('/login/verify', 'AuthController@showVerifyForm');   // Exibe formulário de verificação 2FA
Router::post('/login/verify', 'AuthController@verifyCode');      // Valida código 2FA e cria sessão

// Registro de Novos Usuários
Router::get('/registro', 'AuthController@showRegistrationForm'); // Exibe formulário de cadastro
Router::post('/registro', 'AuthController@register');            // Processa cadastro de novo usuário

// Logout
Router::get('/logout', 'AuthController@logout');                 // Destrói sessão e redireciona para login

// Recuperação de Senha (via e-mail com token)
Router::get('/esqueci-senha', 'AuthController@showForgotPasswordForm');  // Formulário de recuperação
Router::post('/esqueci-senha', 'AuthController@sendRecoveryLink');       // Envia e-mail com link
Router::get('/redefinir-senha', 'AuthController@showResetPasswordForm'); // Formulário de redefinição (com token)
Router::post('/redefinir-senha', 'AuthController@resetPassword');        // Salva nova senha

// =============================================================================
// ROTAS DO PAINEL DO USUÁRIO COMUM
// =============================================================================
// Rotas protegidas para usuários autenticados (qualquer perfil exceto super admin).

// Dashboard do Usuário - Exibe próximos eventos e links rápidos
Router::get('/dashboard', 'UsuarioController@dashboard');

// Gerenciamento de Perfil
Router::get('/perfil', 'UsuarioController@perfil');              // Exibe formulário de edição
Router::post('/perfil', 'UsuarioController@updatePerfil');       // Atualiza dados pessoais e senha

// Gerenciamento de Atlética
Router::post('/perfil/solicitar-atletica', 'UsuarioController@solicitarEntradaAtletica'); // Solicitar entrada
Router::post('/perfil/sair-atletica', 'UsuarioController@sairAtletica');                  // Sair da atlética

// Solicitação de Troca de Curso
Router::post('/perfil/solicitar-troca-curso', 'UsuarioController@solicitarTrocaCurso');   // Solicitar troca de curso

// Inscrições em Modalidades Esportivas
Router::get('/inscricoes', 'UsuarioController@showInscricoes');          // Lista modalidades e inscrições
Router::post('/inscricoes/inscrever', 'UsuarioController@inscreverEmModalidade'); // Nova inscrição
Router::post('/inscricoes/cancelar', 'UsuarioController@cancelarInscricao');      // Cancelar inscrição

// =============================================================================
// ROTAS DE PÁGINAS GERAIS (AGENDA E AGENDAMENTOS)
// =============================================================================
// Rotas acessíveis a todos os usuários autenticados.

// Agenda de Eventos - Visualização do calendário com eventos aprovados
Router::get('/agenda', 'AgendaController@index');                    // Exibe calendário de eventos
Router::post('/agenda/presenca', 'AgendaController@handlePresenca'); // Marcar/desmarcar presença

// Criação de Agendamentos
Router::get('/agendar-evento', 'AgendamentoController@showForm');    // Formulário de novo agendamento
Router::post('/agendar-evento', 'AgendamentoController@create');     // Cria novo agendamento (pendente)

// Gerenciamento de Agendamentos do Usuário
Router::get('/meus-agendamentos', 'AgendamentoController@showMeusAgendamentos'); // Lista agendamentos
Router::get('/agendamento/editar/:id', 'AgendamentoController@showEditForm');    // Formulário de edição
Router::post('/agendamento/editar/:id', 'AgendamentoController@update');         // Atualiza agendamento
Router::post('/agendamento/cancelar', 'AgendamentoController@cancel');           // Cancela agendamento

// Endpoints AJAX para Calendário
Router::get('/calendario-partial', 'AgendamentoController@getCalendarPartial'); // HTML do calendário
Router::get('/agendamento/calendar-partial', 'AgendamentoController@getCalendarGrid'); // Grid do calendário para AJAX
Router::get('/agendamento/calendar-stats', 'AgendamentoController@getCalendarStats'); // Estatísticas do calendário
Router::get('/agendamento/detalhes', 'AgendamentoController@getEventDetails');  // JSON detalhes do evento

// =============================================================================
// ROTAS DO PAINEL DO ADMINISTRADOR DE ATLÉTICA
// =============================================================================
// Rotas protegidas para usuários com perfil 'admin'. Cada admin gerencia apenas
// sua própria atlética.

// Dashboard do Admin - Estatísticas e pendências da atlética
Router::get('/admin/atletica/dashboard', 'AdminAtleticaController@dashboard');

// Gerenciamento de Solicitações de Entrada (novos membros)
Router::get('/admin/atletica/membros', 'AdminAtleticaController@gerenciarMembros');        // Lista pendentes
Router::post('/admin/atletica/membros/acao', 'AdminAtleticaController@handleMembroAction'); // Aprovar/recusar

// Gerenciamento de Membros Ativos da Atlética
Router::get('/admin/atletica/gerenciar-membros', 'AdminAtleticaController@gerenciarMembrosAtletica'); // Lista membros
Router::post('/admin/atletica/gerenciar-membros/acao', 'AdminAtleticaController@handleMembroAtleticaAction'); // Promover/remover

// Gerenciamento de Inscrições em Modalidades
Router::get('/admin/atletica/inscricoes', 'AdminAtleticaController@gerenciarInscricoes');  // Lista inscrições
Router::post('/admin/atletica/inscricoes/acao', 'AdminAtleticaController@handleInscricaoAction'); // Aprovar/rejeitar

// (Removido) Gerenciamento de Eventos Esportivos – funcionalidade desativada

// =============================================================================
// ROTAS DO PAINEL DO SUPER ADMINISTRADOR
// =============================================================================
// Rotas protegidas para usuários com perfil 'superadmin'. Acesso completo ao sistema.

// Dashboard do Super Admin - Estatísticas gerais e links de gerenciamento
Router::get('/superadmin/dashboard', 'SuperAdminController@dashboard');

// Gerenciamento de Agendamentos
Router::get('/superadmin/agendamentos', 'SuperAdminController@gerenciarAgendamentos');              // Lista pendentes
Router::post('/superadmin/agendamentos/aprovar', 'SuperAdminController@aprovarAgendamento');        // Aprovar
Router::post('/superadmin/agendamentos/rejeitar', 'SuperAdminController@rejeitarAgendamento');      // Rejeitar
Router::post('/superadmin/agendamentos/update-aprovado', 'SuperAdminController@updateAgendamentoAprovado');     // Alterar
Router::post('/superadmin/agendamentos/cancelar-aprovado', 'SuperAdminController@cancelarAgendamentoAprovado'); // Cancelar

// Gerenciar Usuários
Router::get('/superadmin/usuarios', 'SuperAdminController@gerenciarUsuarios');
Router::get('/superadmin/usuario/editar', 'SuperAdminController@showEditUserForm');
Router::post('/superadmin/usuario/editar', 'SuperAdminController@updateUser');
Router::post('/superadmin/usuario/excluir', 'SuperAdminController@deleteUser');

// Gerenciar Estrutura (Página Unificada)
Router::get('/superadmin/estrutura', 'SuperAdminController@gerenciarEstrutura');

// Ações para Cursos (chamadas pela página de estrutura)
Router::post('/superadmin/cursos/criar', 'SuperAdminController@createCurso');
Router::get('/superadmin/curso/editar', 'SuperAdminController@showEditCursoForm');
Router::post('/superadmin/curso/editar', 'SuperAdminController@updateCurso');
Router::post('/superadmin/curso/excluir', 'SuperAdminController@deleteCurso');

// Ações para Atléticas (chamadas pela página de estrutura)
Router::post('/superadmin/atleticas/criar', 'SuperAdminController@createAtletica');
Router::get('/superadmin/atletica/editar', 'SuperAdminController@showEditAtleticaForm');
Router::post('/superadmin/atletica/editar', 'SuperAdminController@updateAtletica');
Router::post('/superadmin/atletica/excluir', 'SuperAdminController@deleteAtletica');

// Gerenciar Modalidades
Router::get('/superadmin/modalidades', 'SuperAdminController@gerenciarModalidades');
Router::post('/superadmin/modalidades/criar', 'SuperAdminController@createModalidade');
Router::get('/superadmin/modalidade/editar', 'SuperAdminController@showEditModalidadeForm');
Router::post('/superadmin/modalidade/editar', 'SuperAdminController@updateModalidade');
Router::post('/superadmin/modalidade/excluir', 'SuperAdminController@deleteModalidade');

// Gerenciar Admins
Router::get('/superadmin/admins', 'SuperAdminController@gerenciarAdmins');
Router::post('/superadmin/admins/promover', 'SuperAdminController@promoteAdmin');
Router::post('/superadmin/admins/rebaixar', 'SuperAdminController@demoteAdmin');

// Relatórios
Router::get('/superadmin/relatorios', 'SuperAdminController@showRelatorios');
Router::post('/superadmin/relatorios', 'SuperAdminController@gerarRelatorio');
Router::post('/superadmin/relatorios/ajax', 'SuperAdminController@gerarRelatorioAjax');
Router::post('/superadmin/relatorios/imprimir', 'SuperAdminController@imprimirRelatorio');


// Notificação Global
Router::get('/superadmin/notificacao-global', 'SuperAdminController@enviarNotificacaoGlobal');
Router::post('/superadmin/notificacao-global/enviar', 'SuperAdminController@processarNotificacaoGlobal');

// Gerenciamento de Solicitações de Troca de Curso
Router::post('/superadmin/solicitacao-troca-curso/aprovar', 'SuperAdminController@aprovarTrocaCurso');
Router::post('/superadmin/solicitacao-troca-curso/recusar', 'SuperAdminController@recusarTrocaCurso');

// =============================================================================
// ROTAS DA API JSON
// =============================================================================
// Endpoints que retornam JSON para consumo via AJAX no frontend.
// Usados para funcionalidades assíncronas sem recarregar a página.

// API de Notificações - Polling em tempo real
Router::get('/notifications', 'NotificationController@getNotifications');      // Lista notificações (JSON)
Router::post('/notifications/read', 'NotificationController@markAsRead');      // Marcar como lida (JSON) 
