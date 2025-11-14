
# SGE - Documentação da Arquitetura UML

Este documento fornece uma análise detalhada dos diagramas UML (Unified Modeling Language) criados para o **SGE (Sistema de Gerenciamento de Eventos)**. O objetivo é oferecer uma compreensão clara da arquitetura, funcionalidades e fluxos de interação do sistema através de três perspectivas visuais: Casos de Uso, Classes e Sequência.

## 1. Diagrama de Casos de Uso

O Diagrama de Casos de Uso descreve as funcionalidades do sistema do ponto de vista do usuário. Ele ilustra quem pode fazer o quê, definindo os atores e as ações que eles podem executar.

<p align="center">
  <img src="diagrama-de-caso-de-uso.jpg" alt="Diagrama de Casos de Uso do SGE" width="800"/>
</p>

### Atores

O sistema possui três atores principais, cada um com um conjunto específico de permissões e responsabilidades:

*   **Usuário Comum:** Representa o nível de acesso mais básico. Este ator pode ser um aluno, professor ou membro da comunidade externa. Suas principais interações são:
    *   **Autenticar no Sistema:** Efetuar login e registro.
    *   **Gerenciar Próprio Perfil:** Atualizar suas informações pessoais.
    *   **Ver Agenda Pública:** Visualizar o calendário de eventos aprovados.
    *   **Marcar Presença:** Confirmar participação em um evento.
    *   **Solicitar Entrada em Atlética:** Enviar um pedido para se tornar membro de uma atlética.

*   **Admin de Atlética:** Um usuário com privilégios para administrar uma atlética específica. Ele herda todas as capacidades do *Usuário Comum* e, adicionalmente, pode:
    *   **Gerenciar Próprios Agendamentos:** Criar, editar e cancelar os eventos que solicitou.
    *   **Agendar Evento:** Solicitar o uso de espaços para atividades da sua atlética.
    *   **Gerenciar Membros da Atlética:** Aprovar, rejeitar e remover membros.
    *   **Gerenciar Eventos da Atlética:** Administrar os eventos relacionados à sua entidade.

*   **Super Admin:** O ator com controle total sobre a plataforma. Ele possui todas as permissões dos outros atores, além de acesso a funcionalidades administrativas críticas:
    *   **Aprovar/Rejeitar Agendamentos:** Moderar todas as solicitações de agendamento.
    *   **Gerenciar Usuários (CRUD):** Criar, visualizar, editar e remover qualquer conta de usuário.
    *   **Gerenciar Estrutura Acadêmica:** Administrar os registros de cursos e atléticas.
    *   **Gerar Relatórios:** Extrair dados e estatísticas de uso do sistema.
    *   **Enviar Notificações Globais:** Disparar avisos para todos os usuários.

---

## 2. Diagrama de Classes

O Diagrama de Classes oferece uma visão estática da arquitetura do sistema, detalhando as classes principais, seus atributos, métodos e os relacionamentos entre elas. É um mapa do código-fonte que ajuda a entender a organização lógica e a estrutura de dados.

<p align="center">
  <img src="diagrama-de-classes.jpg" alt="Diagrama de Classes do SGE" width="800"/>
</p>

### Componentes Estruturais

*   **Controllers (`AgendamentoController`, `SuperAdminController`):** Orquestram o fluxo da aplicação. Recebem requisições HTTP do `Router`, processam a lógica de negócio (muitas vezes delegando para `Services`) e interagem com os `Repositories` para manipular os dados.
*   **Repositories (`AgendamentoRepository`, `UsuarioRepository`):** Formam a camada de acesso a dados (Data Access Layer). Eles abstraem a lógica de consulta ao banco de dados, fornecendo uma interface clara para buscar e persistir entidades.
*   **Services (`Auth`, `NotificationService`):** Encapsulam lógicas de negócio específicas e reutilizáveis. O `Auth` gerencia a autenticação e autorização (atuando como um middleware), enquanto o `NotificationService` centraliza a criação de notificações.
*   **Entidades (`Usuario`, `Agendamento`):** Representam os objetos de domínio do sistema. A classe `Usuario` se relaciona com `Agendamento`, indicando que um usuário é o solicitante de um ou mais agendamentos.
*   **Infraestrutura (`Router`, `Connection`):** Componentes centrais do framework. O `Router` mapeia URLs para ações de controllers. A `Connection` é uma classe singleton que gerencia a conexão PDO com o banco de dados MySQL, sendo utilizada por todos os repositórios.

### Relacionamentos Chave

*   **Dependência:** Os `Controllers` dependem dos `Services` para executar tarefas como verificação de permissões (`Auth`).
*   **Uso:** Os `Controllers` utilizam os `Repositories` para acessar e manipular dados.
*   **Associação:** Existe uma associação de um-para-muitos (1..*) entre `Usuario` e `Agendamento`, significando que um usuário pode solicitar múltiplos agendamentos.
*   **Comunicação:** Os `Repositories` se conectam ao `Banco de Dados` através da instância `Connection`.

---

## 3. Diagrama de Sequência

O Diagrama de Sequência descreve a interação dinâmica entre objetos ao longo do tempo para um cenário específico. Ele mostra, passo a passo, a troca de mensagens entre as diferentes classes e componentes para realizar uma tarefa.

O cenário ilustrado abaixo é a **aprovação de um agendamento por um Super Admin**.

<p align="center">
  <img src="diagrama-de-sequencia.jpg" alt="Diagrama de Sequência do SGE" width="800"/>
</p>

### Fluxo de Interação

1.  **Ação do Usuário:** O `Super Admin` clica em "Aprovar" na interface (`Browser`). Uma requisição `POST` é enviada para a rota `/agendamentos/aprovar`.
2.  **Roteamento:** O `Router` recebe a requisição e a direciona para o método `approve(id)` do `SuperAdminController`.
3.  **Atualização de Status:** O `SuperAdminController` invoca o método `updateStatus(id, 'aprovado')` no `AgendamentoRepository`.
4.  **Persistência no Banco:** O `AgendamentoRepository` executa um comando `UPDATE` na tabela `agendamentos` no `Banco de Dados`. O banco confirma que a operação foi bem-sucedida.
5.  **Confirmação:** O `AgendamentoRepository` retorna `true` para o `SuperAdminController`, confirmando que o status foi atualizado.
6.  **Envio de Notificação:** O `SuperAdminController` então chama o `NotificationService`, acionando o método `notifyAgendamentoAprovado(id)`.
7.  **Criação da Notificação:** O `NotificationService` executa um comando `INSERT` na tabela `notificacoes` para registrar o aviso para o usuário solicitante.
8.  **Retorno Final:** Após todas as operações serem concluídas com sucesso, o `SuperAdminController` envia uma resposta `HTTP 200 OK` de volta ao `Browser`.
9.  **Feedback Visual:** O `Browser` recebe a resposta e exibe uma mensagem de sucesso para o Super Admin. 
