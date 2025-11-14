<?php
/**
 * ============================================================================
 * VIEW: DASHBOARD DO USUÁRIO — MVP (Bootstrap-only)
 * ============================================================================
 * Mantém:
 *  - Cards de atalhos (condicionais por role/permissão)
 *  - Lista de próximos eventos com presença marcada
 * Remove:
 *  - Banners / Carrossel
 *  - Calendários / Widgets
 * ============================================================================
 * VARIÁVEIS:
 *  @var array $user
 *  @var array $eventos_presenca
 */
?>

<section class="py-4">
  <div class="container">

    <!-- Título simples (opcional) -->
    <header class="mb-4 text-center text-md-start">
      <h1 class="h1 fw-bold m-0 text-center gradient-title">BEM-VINDO À NOSSA SALA DE EVENTOS</h1>
      <p class="text-muted h5 fw-bold mb-0 text-center gradient-title">Acesse os principais atalhos e acompanhe seus próximos eventos.</p>
    </header>

    <?php
      $role           = $user['role'] ?? '';
      $is_coordenador = $user['is_coordenador'] ?? 0;
    ?>

    <!-- Atalhos do Dashboard -->
    <div class="row g-3">
      <!-- Agenda dos Eventos (todos) -->
      <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex flex-column shadow-lg rounded card-config border-1">
            <h5 class="card-title d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-calendar-check-fill text-primary"></i>
              AGENDA DOS EVENTOS
            </h5>
            <p class="card-text text-muted">Confira os próximos eventos e marque sua presença.</p>
            <a href="/agenda" class="btn btn-primary mt-auto">Ver Agenda</a>
          </div>
        </div>
      </div>

      <!-- Solicitar Agendamento (admin/superadmin/coordenador) -->
      <?php if ($role === 'admin' || $role === 'superadmin' || $is_coordenador == 1): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex flex-column shadow-lg rounded card-config border-1">
            <h5 class="card-title d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-calendar-plus text-warning"></i>
              Solicitar Agendamento
            </h5>
            <p class="card-text text-muted">Solicite o uso da quadra esportiva para suas atividades.</p>
            <a href="/agendar-evento" class="btn btn-warning text-dark mt-auto">Solicitar Aluguel</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Meus Agendamentos (admin/superadmin/coordenador) -->
      <?php if ($role === 'admin' || $role === 'superadmin' || $is_coordenador == 1): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body d-flex flex-column shadow-lg rounded card-config border-1">
            <h5 class="card-title d-flex align-items-center gap-2 mb-2">
              <i class="bi bi-journal-text text-success"></i>
              Meus Agendamentos
            </h5>
            <p class="card-text text-muted">Acompanhe o status das suas solicitações.</p>
            <a href="/meus-agendamentos" class="btn btn-success mt-auto">Ver Solicitações</a>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Painel da Atlética (admin com atlética vinculada) -->
      <?php if ($role === 'admin'): ?>
        <?php $atleticaId = $_SESSION['atletica_id'] ?? null; ?>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card h-100 shadow-sm border-0">
            <div class="card-body d-flex flex-column shadow-lg rounded card-config border-1">
              <?php if ($atleticaId): ?>
                <h5 class="card-title d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-people-fill text-info"></i>
                  Painel da Atlética
                </h5>
                <p class="card-text text-muted">Gerencie membros, inscrições e participações.</p>
                <a href="/admin/atletica/dashboard" class="btn btn-info text-white mt-auto">Acessar Painel</a>
              <?php else: ?>
                <h5 class="card-title d-flex align-items-center gap-2 mb-2">
                  <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                  Configuração Pendente
                </h5>
                <p class="card-text text-muted mb-1">
                  Sua conta de administrador ainda não foi associada a uma atlética.
                </p>
                <small class="text-muted">Contate o super administrador.</small>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <!-- /Atalhos -->

    <!-- Próximos eventos com presença marcada -->
    <div class="mt-4 borda-config">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-text-config text-white">
          <h5 class="mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-calendar-check"></i>
            Próximos Eventos (Presença Marcada)
          </h5>
        </div>
        <div class="card-body">
          <?php if (empty($eventos_presenca)): ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-calendar-x fs-1"></i>
              <p class="mt-2 mb-0">Você ainda não marcou presença em nenhum evento futuro.</p>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <!-- Esportivos -->
              <div class="col-12 col-lg-6">
                <h6 class="fw-bold d-flex align-items-center gap-2 mb-3">
                  <i class="bi bi-trophy trophy-config"></i>
                  Próximo evento esportivo
                </h6>
                <?php
                  $eventosEsportivos = array_filter(
                    $eventos_presenca,
                    fn($e) => ($e['tipo_agendamento'] ?? '') === 'esportivo'
                  );
                ?>
                <?php if (empty($eventosEsportivos)): ?>
                  <div class="text-muted small">Nenhum evento esportivo confirmado</div>
                <?php else: ?>
                  <?php foreach ($eventosEsportivos as $evento): ?>
                    <div class="card mb-3 border-primary shadow-sm event-clickable card-presence-config" data-event-id="<?php echo $evento['id']; ?>" style="cursor:pointer;">
                      <div class="card-body">
                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($evento['titulo']); ?></h6>
                        <?php if (!empty($evento['esporte_tipo'])): ?>
                          <small class="text-muted d-block mb-2">
                            <?php echo htmlspecialchars($evento['esporte_tipo']); ?>
                          </small>
                        <?php endif; ?>
                        <p class="card-text mb-0">
                          <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_agendamento'])); ?><br>
                          <strong>Horário:</strong> <?php echo htmlspecialchars($evento['horario_periodo']); ?><br>
                          <strong>Responsável:</strong> <?php echo htmlspecialchars($evento['responsavel']); ?>
                        </p>
                        <div class="mt-2" onclick="event.stopPropagation();">
                          <form action="/agenda/presenca" method="post" class="d-inline" onsubmit="return confirm('Deseja desmarcar sua presença neste evento?');">
                            <input type="hidden" name="agendamento_id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="action" value="desmarcar">
                            <button type="submit" class="btn btn-sm btn-danger">
                              <i class="bi bi-x-circle"></i> Desmarcar Presença
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Não esportivos -->
              <div class="col-12 col-lg-6">
                <h6 class="fw-bold d-flex align-items-center gap-2 mb-3">
                  <i class="bi bi-calendar-event text-warning"></i>
                  Próximo evento não esportivo
                </h6>
                <?php
                  $eventosNaoEsportivos = array_filter(
                    $eventos_presenca,
                    fn($e) => ($e['tipo_agendamento'] ?? '') !== 'esportivo'
                  );
                ?>
                <?php if (empty($eventosNaoEsportivos)): ?>
                  <div class="text-muted small">Nenhum evento não esportivo confirmado</div>
                <?php else: ?>
                  <?php foreach ($eventosNaoEsportivos as $evento): ?>
                    <div class="card mb-3 border-warning shadow-sm event-clickable card-presence-config-naoesportivo" data-event-id="<?php echo $evento['id']; ?>" style="cursor:pointer;">
                      <div class="card-body">
                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($evento['titulo']); ?></h6>
                        <p class="card-text mb-0">
                          <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($evento['data_agendamento'])); ?><br>
                          <strong>Horário:</strong> <?php echo htmlspecialchars($evento['horario_periodo']); ?><br>
                          <strong>Responsável:</strong> <?php echo htmlspecialchars($evento['responsavel']); ?>
                        </p>
                        <div class="mt-2" onclick="event.stopPropagation();">
                          <form action="/agenda/presenca" method="post" class="d-inline" onsubmit="return confirm('Deseja desmarcar sua presença neste evento?');">
                            <input type="hidden" name="agendamento_id" value="<?php echo $evento['id']; ?>">
                            <input type="hidden" name="action" value="desmarcar">
                            <button type="submit" class="btn btn-sm btn-danger">
                              <i class="bi bi-x-circle"></i> Desmarcar Presença
                            </button>
                          </form>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="text-center mt-3">
              <a href="/agenda" class="btn btn-outline-primary">
                <i class="bi bi-calendar3"></i> Ver Agenda Completa
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</section>
