# 🚀 SGE - Como Usar

## ⚡ Início Rápido

### 📋 Pré-requisitos
- **Docker Desktop** (versão 20.10+)
- **Git**
- Portas livres: **80**, **3306**, **8080**

---

## 📦 Instalação e Inicialização

### 1. Clone o Repositório

```bash
git clone https://github.com/seu-usuario/sge.git
cd sge
```

### 2. Inicie os Containers

```bash
docker-compose up -d
```

**O que acontece automaticamente:**
- ✅ Container PHP inicia na porta **80**
- ✅ Container MySQL inicia na porta **3306**
- ✅ Container phpMyAdmin inicia na porta **8080**
- ✅ Composer instala dependências automaticamente via `entrypoint.sh`
- ✅ MySQL cria a estrutura do banco de dados

### 3. Popular o Banco de Dados

⚠️ **IMPORTANTE:** O banco é criado vazio. Você **precisa** popular com dados de exemplo.

#### Windows PowerShell:
```powershell
Get-Content assets/seeds/db_populate.sql | docker exec -i mysql mysql -uroot -prootpass application
```

#### Linux/Mac:
```bash
docker exec -i mysql mysql -uroot -prootpass application < assets/seeds/db_populate.sql
```

#### Alternativa via phpMyAdmin:
1. Acesse http://localhost:8080
2. Login: `root` / Senha: `rootpass`
3. Selecione o banco `application`
4. Vá em "SQL"
5. Copie o conteúdo de `assets/seeds/db_populate.sql` e execute

### 4. Acesse a Aplicação

🎉 **Pronto!** Acesse: **http://localhost**

---

## 🔐 Credenciais de Login

### Super Admin (Acesso Total)
```
Email/RA: sadmin
Senha: sadmin
Permissões: Acesso total ao sistema
```

### Admin de Atlética
```
Email: admin.atletica@sge.com
Senha: sadmin
Permissões: Gestão da atlética
```

### Usuário Comum (Aluno)
```
Email: aluno@sge.com
Senha: sadmin
Permissões: Agendamentos, presença, perfil
```

### Professor
```
Email: carlos.andrade@prof.sge.com
Senha: sadmin
Permissões: Pode agendar eventos
```

### Membro de Atlética
```
Email: membro@sge.com
Senha: sadmin
Permissões: Participar da atlética
```

**Nota:** Todos os usuários têm a senha `sadmin` por padrão.

---

## 🌐 Portas Disponíveis

| Serviço | Porta | URL |
|---------|-------|-----|
| **Aplicação** | 80 | http://localhost |
| **phpMyAdmin** | 8080 | http://localhost:8080 |
| **MySQL** | 3306 | localhost:3306 |

---

## 🛠️ Comandos Úteis

### Parar os Containers
```bash
docker-compose down
```

### Reiniciar os Containers
```bash
docker-compose restart
```

### Ver Logs
```bash
# Logs do PHP
docker logs php

# Logs do MySQL
docker logs mysql
```

### Executar Comandos no Container
```bash
# Entrar no container PHP
docker exec -it php bash

# Executar Composer
docker exec php composer install
```

---

## 🐛 Solução de Problemas

### Erro "vendor/autoload.php not found"
✅ **Resolvido!** O entrypoint instala automaticamente. Se persistir:

```bash
docker-compose restart apache
```

### Erro de Conexão com Banco de Dados
Certifique-se que o MySQL está rodando:
```bash
docker ps | grep mysql
```

### Porta já em uso
Altere as portas no `docker-compose.yml`:
```yaml
ports:
  - '8080:80'  # Altere 80 para outra porta
```

---

## 📚 Próximos Passos

- 📖 Leia o [README.md](./README.md) completo para mais detalhes
- 🔧 Configure o e-mail no [src/Core/EmailService.php](./src/Core/EmailService.php)
- 🎨 Personalize o tema em [public/css/](./public/css/)
- 📝 Veja a [documentação técnica](./TECHNICAL_ARCHITECTURE.md)

---

## 🆘 Precisa de Ajuda?

1. Verifique os logs: `docker logs php`
2. Reinicie os containers: `docker-compose restart`
3. Reconstrua a imagem: `docker-compose up -d --build`
4. Consulte as [Issues no GitHub](https://github.com/seu-usuario/sge/issues) 
