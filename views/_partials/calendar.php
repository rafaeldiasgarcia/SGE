<?php
/**
 * ============================================================================
 * VIEW PARCIAL: CALENDÁRIO DE AGENDAMENTOS
 * ============================================================================
 * 
 * Renderiza o calendário interativo para seleção de datas e períodos de 
 * agendamento de quadras esportivas.
 * 
 * FUNCIONAMENTO:
 * - Exibe um mês por vez com navegação entre meses
 * - Mostra disponibilidade em tempo real de cada período
 * - Aplica regra de antecedência mínima de 4 dias
 * - Bloqueia datas passadas automaticamente
 * - Dois períodos por dia: P1 (19:15-20:55) e P2 (21:10-22:50)
 * 
 * VARIÁVEIS RECEBIDAS DO CONTROLLER:
 * @var DateTime $inicio       - Primeiro dia do mês sendo exibido
 * @var array    $ocupado      - Períodos ocupados indexados por data ['Y-m-d']['P1'|'P2']
 * @var int      $diasNoMes    - Quantidade de dias no mês
 * @var int      $primeiroW    - Dia da semana do primeiro dia (0=domingo)
 * @var string   $prevMes      - String Y-m do mês anterior
 * @var string   $nextMes      - String Y-m do próximo mês
 * @var bool     $isCampeonato - Se o evento sendo agendado é um campeonato (opcional)
 * 
 * INTEGRAÇÃO:
 * - Usado em: agenda.view.php, editar-evento.view.php
 * - JavaScript: calendar.js (navegação AJAX)
 * - CSS: calendar.css, agenda.css
 * - Controller: AgendaController::calendario()
 * 
 * REGRAS DE NEGÓCIO:
 * - Antecedência mínima: 4 dias
 * - Não permite agendamento em datas passadas
 * - Badge de cor indica disponibilidade:
 *   * Verde: ambos períodos livres
 *   * Amarelo: um período ocupado
 *   * Vermelho: ambos períodos ocupados
 *   * Cinza: data indisponível (passada ou antecedência insuficiente)
 */

// ============================================================================
// FUNÇÕES AUXILIARES DE RENDERIZAÇÃO
// ============================================================================

/**
 * Determina as classes CSS para um slot (botão de período) do calendário
 * 
 * @param bool $busy         - Se o período já está ocupado
 * @param bool $dateInvalid  - Se a data é inválida (passada ou antecedência insuficiente)
 * @param bool $isPastDate   - Se a data já passou
 * @param bool $isCampeonato - Se é um campeonato (opcional)
 * @return string            - Classes CSS do Bootstrap para o botão
 */
function slotClassCal(bool $busy, bool $dateInvalid, bool $isPastDate, bool $isCampeonato = false) { 
    // Para campeonatos: apenas desabilitar datas passadas
    if ($isCampeonato) {
        if ($isPastDate) {
            return 'btn-outline-secondary disabled';
        }
        // Para campeonatos, sempre permitir seleção (mesmo ocupado)
        return 'btn-success';
    }
    
    // Para dias passados ou com antecedência insuficiente, mantém as cores originais mas desabilitados
    if ($dateInvalid) {
        return $busy ? 'btn-outline-secondary disabled' : 'btn-success disabled';
    }
    
    // Para dias válidos, comportamento normal
    return $busy ? 'btn-outline-secondary disabled' : 'btn-success'; 
}

/**
 * Determina a cor do badge de disponibilidade do dia
 * 
 * @param string $ymd                    - Data no formato Y-m-d
 * @param array  $ocupado                - Array de períodos ocupados
 * @param bool   $isPastDate             - Se a data já passou
 * @param bool   $isInsufficientAdvance  - Se falta antecedência mínima
 * @param bool   $isCampeonato           - Se é um campeonato (opcional)
 * @return string                        - Classes CSS do badge
 */
function dayBadgeCal($ymd, $ocupado, $isPastDate, $isInsufficientAdvance, $isCampeonato = false) {
    // Para campeonatos: sempre mostrar como disponível (exceto datas passadas)
    if ($isCampeonato) {
        if ($isPastDate) {
            return 'bg-light border text-dark';
        }
        return 'bg-success';
    }
    
    // Para todos os casos (passados, antecedência insuficiente ou válidos), usa as cores normais
    $p1 = !empty($ocupado[$ymd]['P1']);
    $p2 = !empty($ocupado[$ymd]['P2']);
    
    // Ambos períodos livres
    if (!$p1 && !$p2) return 'bg-success';
    
    // Apenas um período ocupado (XOR lógico)
    if ($p1 xor $p2) return 'bg-warning text-dark';
    
    // Ambos períodos ocupados
    return 'bg-danger';
}

/**
 * Verifica se uma data já passou
 * 
 * @param string $ymd  - Data no formato Y-m-d
 * @return bool        - True se a data é anterior a hoje
 */
function isDateInPast($ymd) {
    $hoje = new \DateTime();
    $dataEvento = new \DateTime($ymd);
    return $dataEvento < $hoje;
}

/**
 * Verifica se uma data tem antecedência insuficiente (menos de 4 dias) ou excede o limite de 1 mês
 * 
 * @param string $ymd          - Data no formato Y-m-d
 * @param bool   $isCampeonato - Se é um campeonato (opcional)
 * @return bool                - True se faltam menos de 4 dias ou excede 1 mês
 */
function hasInsufficientAdvance($ymd, $isCampeonato = false) {
    // Para campeonatos: SEM restrições de antecedência
    if ($isCampeonato) {
        return false;
    }
    
    $hoje = new \DateTime();
    $dataEvento = new \DateTime($ymd);
    $diferencaDias = $hoje->diff($dataEvento)->days;
    
    // Se faltam menos de 4 dias (mas não é data passada)
    if ($dataEvento >= $hoje && $diferencaDias < 4) {
        return true;
    }
    
    // Se excede 1 mês (30 dias) de antecedência
    if ($diferencaDias > 30) {
        return true;
    }
    
    return false;
}
?>

<!-- ========================================================================
     CABEÇALHO DO CALENDÁRIO
     ======================================================================== -->
<h5 class="mb-3 text-primary border-bottom pb-2 calendar-title">
    <i class="bi bi-calendar-check"></i> Selecione a Data e o Período
    <span id="regra-antecedencia" class="d-block d-sm-inline calendar-subtitle">(4 dias a 1 mês de antecedência)</span>
    <span id="regra-campeonato" class="text-success d-block d-sm-inline calendar-subtitle-campeonato">
        <i class="bi bi-trophy"></i> Campeonatos: sem restrições de data!
    </span>
</h5>

<!-- Legenda de cores do calendário -->
<div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 small mb-3 calendar-legend">
    <span><span class="badge bg-success me-1 legend-badge">&nbsp;</span> Ambos livres</span>
    <span><span class="badge bg-warning text-dark me-1 legend-badge">&nbsp;</span> Um ocupado</span>
    <span><span class="badge bg-danger me-1 legend-badge">&nbsp;</span> Ambos ocupados</span>
    <span><span class="badge bg-light border text-dark me-1 legend-badge">&nbsp;</span> Data inválida</span>
</div>

<!-- ========================================================================
     CONTAINER PRINCIPAL DO CALENDÁRIO
     Este div é o alvo das atualizações AJAX ao navegar entre meses
     ======================================================================== -->
<div id="cal" class="border rounded-3 p-2 mb-4 bg-white shadow-sm">

    <!-- Navegação de mês (anterior/próximo) -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <button type="button" class="btn btn-sm btn-outline-primary nav-cal" data-mes="<?= $prevMes; ?>">
            <i class="bi bi-chevron-left"></i>
        </button>

        <!-- Exibição do mês e ano atual -->
        <div class="fw-bold text-center calendar-month-year">
            <?php
            // Array de nomes de meses em português
            $meses = [
                1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
            ];
            $mes = (int)$inicio->format('n');
            $ano = $inicio->format('Y');
            echo $meses[$mes] . ' de ' . $ano;
            ?>
        </div>

        <button type="button" class="btn btn-sm btn-outline-primary nav-cal" data-mes="<?= $nextMes; ?>">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <!-- Cabeçalho com dias da semana -->
    <div class="calendar-header-row d-none d-md-flex">
        <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $d): ?>
            <div class="calendar-header-day"><?= $d ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Cabeçalho mobile (abreviado) -->
    <div class="calendar-header-row d-flex d-md-none">
        <?php foreach (['D','S','T','Q','Q','S','S'] as $d): ?>
            <div class="calendar-header-day"><?= $d ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Grid principal do calendário -->
    <div class="calendar-grid-wrapper">
        <?php
        // Adiciona células vazias antes do primeiro dia do mês
        for ($i=0; $i<$primeiroW; $i++): ?>
            <div class="calendar-day-cell-grid"></div>
        <?php endfor; ?>
        
        <?php 
        // Verifica se é campeonato (variável opcional)
        $isCampeonato = isset($isCampeonato) ? $isCampeonato : false;
        
        // Loop por cada dia do mês
        for ($dia=1; $dia<=$diasNoMes; $dia++):
            // Formata data no padrão Y-m-d para comparações
            $ymd = $inicio->format('Y-m') . '-' . str_pad($dia, 2, '0', STR_PAD_LEFT);
            
            // Verifica restrições de data
            $isPastDate = isDateInPast($ymd);
            $isInsufficientAdvance = hasInsufficientAdvance($ymd, $isCampeonato);
            $dateInvalid = $isPastDate || $isInsufficientAdvance;
            
            // Determina cor do badge de disponibilidade
            $badge = dayBadgeCal($ymd, $ocupado, $isPastDate, $isInsufficientAdvance, $isCampeonato);
            
            // Verifica se cada período está ocupado
            $p1busy = !empty($ocupado[$ymd]['P1']);
            $p2busy = !empty($ocupado[$ymd]['P2']);
            
            // Para campeonatos: apenas desabilitar datas passadas
            // Para outros: desabilitar períodos ocupados ou datas inválidas
            if ($isCampeonato) {
                $p1disabled = $isPastDate;
                $p2disabled = $isPastDate;
                $dayDisabled = $isPastDate;
            } else {
                $p1disabled = $p1busy || $dateInvalid;
                $p2disabled = $p2busy || $dateInvalid;
                // Dropdown só desabilitado se a data for inválida, não por períodos ocupados
                $dayDisabled = $dateInvalid;
            }
            
            // Adiciona classe CSS especial para datas indisponíveis
            $cellClass = '';
            if ($isPastDate || $isInsufficientAdvance) {
                $cellClass = 'past-date';
            }

            // ID único para o dropdown
            $dropdownId = 'dropdown-' . $ymd;
            ?>
            <div class="calendar-day-cell-grid">
                <div class="calendar-day-wrapper <?= $cellClass ?>" data-date="<?= $ymd ?>">
                    <!-- Cabeçalho do dia com número e badge -->
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="calendar-day-number"><?= $dia ?></span>
                        <span class="badge <?= $badge ?> calendar-badge">&nbsp;</span>
                    </div>

                    <!-- Botão dropdown para selecionar horário -->
                    <div class="dropdown w-100">
                        <button class="btn btn-sm btn-outline-primary w-100 dropdown-toggle calendar-day-btn <?= $dayDisabled ? 'disabled' : '' ?>"
                                type="button"
                                id="<?= $dropdownId ?>"
                                data-bs-toggle="dropdown"
                                data-date="<?= $ymd ?>"
                                aria-expanded="false"
                                <?= $dayDisabled ? 'disabled' : '' ?>>
                            <small class="d-none d-sm-inline">Horários</small>
                            <small class="d-inline d-sm-none">Hora</small>
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="<?= $dropdownId ?>">
                            <!-- Período 1 (19:15 - 20:55) -->
                            <li>
                                <a class="dropdown-item slot-item <?= $p1disabled ? 'disabled' : '' ?>"
                                   href="#"
                                   data-date="<?= $ymd ?>"
                                   data-periodo="P1"
                                   <?= $p1disabled ? 'onclick="return false;"' : '' ?>>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="bi bi-clock"></i> 19:15 - 20:55
                                        </span>
                                        <?php if ($p1busy): ?>
                                            <span class="badge bg-danger text-white">Ocupado</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white">Livre</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </li>

                            <li><hr class="dropdown-divider"></li>

                            <!-- Período 2 (21:10 - 22:50) -->
                            <li>
                                <a class="dropdown-item slot-item <?= $p2disabled ? 'disabled' : '' ?>"
                                   href="#"
                                   data-date="<?= $ymd ?>"
                                   data-periodo="P2"
                                   <?= $p2disabled ? 'onclick="return false;"' : '' ?>>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="bi bi-clock"></i> 21:10 - 22:50
                                        </span>
                                        <?php if ($p2busy): ?>
                                            <span class="badge bg-danger text-white">Ocupado</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white">Livre</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>