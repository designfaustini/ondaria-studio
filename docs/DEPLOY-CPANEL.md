# Publicar no cPanel (PHP 8.5 + MySQL)

Requisitos da hospedagem: PHP 8.3 ou mais novo (testado no 8.5), extensões `pdo_mysql`, `mbstring`, `fileinfo` e `openssl`, MySQL 8 ou MariaDB 10.6+, `mod_rewrite` ligado e HTTPS (sem HTTPS os navegadores bloqueiam microfone, escolha de placa de som e transmissão).

## 1. Banco de dados — **no cPanel**

1. **Bancos de dados MySQL** → crie o banco (ex.: `usuario_ondaria`) e um usuário com senha forte.
2. Dê ao usuário **TODOS OS PRIVILÉGIOS** nesse banco.

## 2. Arquivos — **no PC (PowerShell) e no Gerenciador de Arquivos do cPanel**

O ideal é o código ficar **fora** do `public_html` e o domínio apontar para a pasta `public/`:

```
/home/usuario/ondaria/            ← app, bin, database, storage, views, .env
/home/usuario/ondaria/public/     ← raiz do (sub)domínio
```

1. No PC, compacte a pasta `ondaria-studio` **sem** o arquivo `.env` e sem `storage/database.sqlite`.
2. No cPanel → **Gerenciador de Arquivos**, envie o .zip para `/home/usuario/` e extraia como `ondaria`.
3. No cPanel → **Domínios** (ou Subdomínios), crie/edite o domínio e aponte a **raiz do documento** para `ondaria/public`.

Se a hospedagem não deixar mudar a raiz (domínio principal preso ao `public_html`): extraia o projeto inteiro dentro de `public_html`. O `.htaccess` da raiz do projeto encaminha tudo para `public/` e bloqueia `app/`, `storage/`, `.env` etc.

## 3. Configuração — **no Gerenciador de Arquivos do cPanel**

Copie `.env.example` para `.env` (na pasta `ondaria`, não na `public`) e preencha:

```
APP_ENV=production
APP_URL=https://radio.seudominio.com.br
APP_DEBUG=false
DB_DRIVER=mysql
DB_HOST=localhost
DB_NAME=usuario_ondaria
DB_USER=usuario_ondaria
DB_PASS=a senha criada no passo 1
```

## 4. Tabelas e administrador — **no Terminal do cPanel** (ou SSH)

```bash
cd ~/ondaria
php bin/console.php migrate
php bin/console.php superadmin seu-email@dominio.com.br "Seu Nome"
```

O segundo comando pede a senha do administrador da plataforma. Sem Terminal: importe `database/schema.sql` pelo **phpMyAdmin** e use o Terminal depois só para o superadmin (ou peça ao suporte da hospedagem).

Se o comando `php` do terminal for de outra versão, use o caminho completo, ex.: `/opt/cpanel/ea-php85/root/usr/bin/php`.

## 5. Limites do PHP — **no cPanel → Selecionar versão do PHP / MultiPHP INI Editor**

O envio de áudio é feito em partes de 1 MB, então os limites padrão já funcionam. Recomendado:

| Opção | Valor |
|---|---|
| `upload_max_filesize` | 8M |
| `post_max_size` | 8M |
| `max_execution_time` | 120 |
| `memory_limit` | 256M |

## 6. Tarefa agendada — **no cPanel → Cron Jobs**

Uma vez por dia, para apagar uploads interrompidos e registros antigos:

```
30 4 * * * cd /home/usuario/ondaria && php bin/console.php cleanup >/dev/null 2>&1
```

## 7. Primeiro acesso

1. Abra `https://radio.seudominio.com.br/entrar` com o superadmin → painel **Plataforma** (rádios e planos).
2. Cadastre uma rádio em `/cadastro` (ou desligue o cadastro público com `SIGNUP_ENABLED=false`).
3. Na rádio: **Biblioteca** → envie músicas e os arquivos de hora certa (`HRS00`…`HRS23`, `HRSxx_O`, `MIN00`…`MIN59`) na pasta "Hora certa".
4. **Estúdio** → clique em "Entrar no estúdio" (o navegador só libera o som após um clique).

## Espaço em disco

Os áudios ficam em `storage/media/<id-da-rádio>/`. Some as cotas dos planos vendidos e confira se a conta do cPanel comporta. Para muitas rádios, considere uma VPS.

## Atualizar uma versão

Envie os arquivos novos por cima, **sem** sobrescrever `.env` e `storage/`. Mudanças de banco virão em `database/` com instruções próprias.
